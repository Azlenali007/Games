<?php
/**
 * Sequential Game Round Engine
 * Manages active sequential rounds, server-authoritative countdowns,
 * betting open/close thresholds, result declaration, locking, and settlement.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/wallet.php';

class RoundEngine {
    // Standard outcomes for common gaming round infrastructure
    public const OUTCOMES = ['ALPHA_1', 'BETA_2', 'GAMMA_3', 'DELTA_9'];

    /**
     * Get current authoritative active round
     */
    public static function getActiveRound(): ?array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("
            SELECT * FROM rounds 
            WHERE status = 'active' 
            ORDER BY round_number ASC 
            LIMIT 1
        ");
        $round = $stmt->fetch();

        // If no active round exists, trigger progression to activate or create next sequential round
        if (!$round) {
            self::advanceRounds();
            $stmt = $db->query("
                SELECT * FROM rounds 
                WHERE status = 'active' 
                ORDER BY round_number ASC 
                LIMIT 1
            ");
            $round = $stmt->fetch();
        }

        return $round;
    }

    /**
     * Get upcoming sequential rounds
     */
    public static function getUpcomingRounds(int $limit = 5): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id, round_number, start_time, end_time, betting_closed_at, status, betting_status
            FROM rounds 
            WHERE status = 'upcoming' 
            ORDER BY round_number ASC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get completed rounds history (SAFE: only declared results are included)
     */
    public static function getCompletedRounds(int $limit = 10, int $offset = 0): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id, round_number, start_time, end_time, declared_result, result_status, settlement_status, created_at
            FROM rounds 
            WHERE status = 'completed' AND result_status IN ('declared', 'locked', 'settled')
            ORDER BY round_number DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get a specific round by ID (Sanitized: hides declared_result if not yet completed)
     */
    public static function getRoundById(int $roundId, bool $isAdmin = false): ?array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM rounds WHERE id = ? LIMIT 1");
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if ($round && !$isAdmin && $round['status'] !== 'completed') {
            // Strict compliance: Never leak unreleased results to players
            $round['declared_result'] = null;
        }

        return $round;
    }

    /**
     * Compute server-synchronized countdown metrics
     */
    public static function getCountdownData(?array $round = null): array {
        if (!$round) {
            $round = self::getActiveRound();
        }

        $now = time();
        if (!$round) {
            return [
                'server_time' => $now,
                'has_active_round' => false,
                'round_id' => 0,
                'round_number' => 0,
                'betting_open' => false,
                'seconds_remaining' => 0,
                'betting_seconds_remaining' => 0,
            ];
        }

        $endTime = strtotime($round['end_time']);
        $bettingClosedAt = strtotime($round['betting_closed_at']);

        $secondsRemaining = max(0, $endTime - $now);
        $bettingSecondsRemaining = max(0, $bettingClosedAt - $now);
        $isBettingOpen = ($round['betting_status'] === 'open') && ($bettingSecondsRemaining > 0);

        return [
            'server_time' => $now,
            'has_active_round' => true,
            'round_id' => (int)$round['id'],
            'round_number' => (int)$round['round_number'],
            'status' => $round['status'],
            'betting_status' => $isBettingOpen ? 'open' : 'closed',
            'betting_open' => $isBettingOpen,
            'seconds_remaining' => $secondsRemaining,
            'betting_seconds_remaining' => $bettingSecondsRemaining,
            'start_time' => $round['start_time'],
            'end_time' => $round['end_time'],
            'betting_closed_at' => $round['betting_closed_at']
        ];
    }

    /**
     * Advance sequential rounds, enforce betting close, process results and settlements.
     * Uses atomic DB locking to prevent race conditions or duplicate settlements.
     */
    public static function advanceRounds(): array {
        $db = Database::getInstance()->getConnection();
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $actionsTaken = [];

        // 1. Acquire mutex lock via cron_locks table
        $lockKey = 'round_progression_lock';
        $lockAcquired = false;

        try {
            $db->prepare("INSERT INTO cron_locks (lock_key, locked_by) VALUES (?, 'round_engine')")->execute([$lockKey]);
            $lockAcquired = true;
        } catch (Exception $e) {
            // Check if lock is stale (> 30s)
            $stmt = $db->prepare("SELECT locked_at FROM cron_locks WHERE lock_key = ?");
            $stmt->execute([$lockKey]);
            $lockTime = $stmt->fetchColumn();

            if ($lockTime && (strtotime($lockTime) < $now - 30)) {
                $db->prepare("UPDATE cron_locks SET locked_at = NOW(), locked_by = 'round_engine_override' WHERE lock_key = ?")
                   ->execute([$lockKey]);
                $lockAcquired = true;
            } else {
                return ['status' => 'locked', 'message' => 'Progression currently locked by another worker.'];
            }
        }

        try {
            // Step 1: Enforce betting close on active rounds past betting_closed_at
            $closeStmt = $db->prepare("
                UPDATE rounds 
                SET betting_status = 'closed', updated_at = NOW() 
                WHERE status = 'active' AND betting_status = 'open' AND betting_closed_at <= ?
            ");
            $closeStmt->execute([$nowStr]);
            if ($closeStmt->rowCount() > 0) {
                $actionsTaken[] = "Betting closed for active rounds past cutoff.";
            }

            // Step 2: Identify expired active rounds (end_time <= now)
            $expStmt = $db->prepare("SELECT * FROM rounds WHERE status = 'active' AND end_time <= ? ORDER BY round_number ASC");
            $expStmt->execute([$nowStr]);
            $expiredRounds = $expStmt->fetchAll();

            foreach ($expiredRounds as $r) {
                $roundId = (int)$r['id'];

                // Handle result determination
                if ($r['result_mode'] === 'auto' && $r['result_status'] === 'pending') {
                    // Seed deterministic/pseudo-random outcome from round number & timestamp
                    $outcomeIndex = abs(crc32($r['round_number'] . '_' . $r['start_time'])) % count(self::OUTCOMES);
                    $result = self::OUTCOMES[$outcomeIndex];

                    // Lock result
                    $db->prepare("
                        UPDATE rounds 
                        SET status = 'completed', betting_status = 'closed', declared_result = ?, result_status = 'locked', updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$result, $roundId]);

                    // Audit record
                    $db->prepare("
                        INSERT INTO round_audit_logs (round_id, admin_id, action, previous_state, new_state, reason)
                        VALUES (?, NULL, 'AUTO_RESULT_DECLARED', 'pending', ?, 'Automated round completion')
                    ")->execute([$roundId, $result]);

                    // Settle bets for this round
                    self::settleRoundBets($roundId, $result);

                    $actionsTaken[] = "Round #{$r['round_number']} completed with result $result and settled.";
                } elseif ($r['result_mode'] === 'manual') {
                    // In manual mode, mark completed and betting closed, awaiting admin result declaration
                    $db->prepare("
                        UPDATE rounds 
                        SET status = 'completed', betting_status = 'closed', updated_at = NOW() 
                        WHERE id = ?
                    ")->execute([$roundId]);
                    $actionsTaken[] = "Round #{$r['round_number']} marked completed, awaiting manual result entry.";
                }
            }

            // Step 3: Check if there is an active round now
            $activeCount = $db->query("SELECT COUNT(*) FROM rounds WHERE status = 'active'")->fetchColumn();
            if ($activeCount == 0) {
                // Find next upcoming round
                $nextStmt = $db->prepare("SELECT * FROM rounds WHERE status = 'upcoming' ORDER BY round_number ASC LIMIT 1");
                $nextStmt->execute();
                $nextRound = $nextStmt->fetch();

                $duration = ROUND_DURATION_DEFAULT;
                $closeLead = BETTING_CLOSE_LEAD_DEFAULT;

                if ($nextRound) {
                    // Activate this upcoming round, resetting timing to start immediately
                    $newStart = $nowStr;
                    $newEnd = date('Y-m-d H:i:s', $now + $duration);
                    $newClose = date('Y-m-d H:i:s', $now + $duration - $closeLead);

                    $db->prepare("
                        UPDATE rounds 
                        SET status = 'active', betting_status = 'open', start_time = ?, end_time = ?, betting_closed_at = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$newStart, $newEnd, $newClose, $nextRound['id']]);

                    $actionsTaken[] = "Upcoming Round #{$nextRound['round_number']} activated.";
                } else {
                    // No upcoming rounds exist, create next sequential round!
                    $latestNumber = (int)$db->query("SELECT MAX(round_number) FROM rounds")->fetchColumn();
                    $newRoundNumber = ($latestNumber > 0) ? ($latestNumber + 1) : 321;

                    $newStart = $nowStr;
                    $newEnd = date('Y-m-d H:i:s', $now + $duration);
                    $newClose = date('Y-m-d H:i:s', $now + $duration - $closeLead);

                    $db->prepare("
                        INSERT INTO rounds (round_number, start_time, end_time, betting_closed_at, status, betting_status, result_status, settlement_status, result_mode)
                        VALUES (?, ?, ?, ?, 'active', 'open', 'pending', 'unsettled', 'auto')
                    ")->execute([$newRoundNumber, $newStart, $newEnd, $newClose]);

                    $actionsTaken[] = "Created and activated next sequential Round #$newRoundNumber.";
                }
            }

            // Step 4: Ensure at least 3 upcoming sequential rounds exist in database
            $upcomingCount = (int)$db->query("SELECT COUNT(*) FROM rounds WHERE status = 'upcoming'")->fetchColumn();
            $duration = ROUND_DURATION_DEFAULT;
            $closeLead = BETTING_CLOSE_LEAD_DEFAULT;

            while ($upcomingCount < 3) {
                // Find latest round_number in system
                $latestRound = $db->query("SELECT round_number, end_time FROM rounds ORDER BY round_number DESC LIMIT 1")->fetch();
                $latestNum = (int)($latestRound['round_number'] ?? 320);
                $nextNum = $latestNum + 1;

                $prevEnd = !empty($latestRound['end_time']) ? strtotime($latestRound['end_time']) : $now;
                $startTime = max($now, $prevEnd);
                $endTime = $startTime + $duration;
                $closeTime = $endTime - $closeLead;

                $db->prepare("
                    INSERT INTO rounds (round_number, start_time, end_time, betting_closed_at, status, betting_status, result_status, settlement_status, result_mode)
                    VALUES (?, ?, ?, ?, 'upcoming', 'open', 'pending', 'unsettled', 'auto')
                ")->execute([
                    $nextNum,
                    date('Y-m-d H:i:s', $startTime),
                    date('Y-m-d H:i:s', $endTime),
                    date('Y-m-d H:i:s', $closeTime)
                ]);

                $upcomingCount++;
                $actionsTaken[] = "Generated future sequential Round #$nextNum.";
            }

        } finally {
            // Release lock
            if ($lockAcquired) {
                $db->prepare("DELETE FROM cron_locks WHERE lock_key = ?")->execute([$lockKey]);
            }
        }

        return ['status' => 'success', 'actions' => $actionsTaken];
    }

    /**
     * Settle bets for a completed round with idempotency protection
     */
    public static function settleRoundBets(int $roundId, string $declaredResult): bool {
        $db = Database::getInstance()->getConnection();

        // Check settlement state
        $rStmt = $db->prepare("SELECT id, settlement_status, round_number FROM rounds WHERE id = ? FOR UPDATE");
        $db->beginTransaction();
        $rStmt->execute([$roundId]);
        $round = $rStmt->fetch();

        if (!$round || $round['settlement_status'] === 'settled') {
            $db->commit();
            return false; // Already settled, prevents duplicate credit
        }

        // Mark processing
        $db->prepare("UPDATE rounds SET settlement_status = 'processing' WHERE id = ?")->execute([$roundId]);

        // Fetch placed bets
        $bStmt = $db->prepare("SELECT * FROM bets WHERE round_id = ? AND status = 'placed'");
        $bStmt->execute([$roundId]);
        $bets = $bStmt->fetchAll();

        foreach ($bets as $b) {
            $betId = (int)$b['id'];
            $userId = (int)$b['user_id'];
            $amount = (float)$b['amount'];
            $optionKey = $b['option_key'];

            if ($optionKey === $declaredResult) {
                // Winner! Standard payout is 2.0x (or potential_payout)
                $multiplier = 2.0;
                $payout = ($b['potential_payout'] > 0) ? (float)$b['potential_payout'] : ($amount * $multiplier);

                // Update bet status
                $db->prepare("UPDATE bets SET status = 'won', payout_amount = ?, settled_at = NOW() WHERE id = ?")
                   ->execute([$payout, $betId]);

                // Credit user wallet
                Wallet::credit(
                    $userId,
                    $payout,
                    'bet_won',
                    (string)$b['bet_ref'],
                    'bet',
                    "Round #{$round['round_number']} winning settlement on option $optionKey",
                    $db
                );
            } else {
                // Lost
                $db->prepare("UPDATE bets SET status = 'lost', payout_amount = 0.0000, settled_at = NOW() WHERE id = ?")
                   ->execute([$betId]);
            }
        }

        // Finalize settlement
        $db->prepare("UPDATE rounds SET settlement_status = 'settled', result_status = 'settled' WHERE id = ?")->execute([$roundId]);
        $db->commit();

        return true;
    }

    /**
     * Place bet on the active round
     */
    public static function placeBet(int $userId, int $roundId, string $optionKey, float $amount): array {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Bet amount must be greater than zero.'];
        }

        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            // 1. Verify round state with row lock
            $rStmt = $db->prepare("SELECT * FROM rounds WHERE id = ? FOR UPDATE");
            $rStmt->execute([$roundId]);
            $round = $rStmt->fetch();

            if (!$round) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Selected round was not found.'];
            }

            if ($round['status'] !== 'active') {
                $db->rollBack();
                return ['success' => false, 'message' => 'Round is not currently active for betting.'];
            }

            // Server-authoritative time check
            $now = time();
            $closeTime = strtotime($round['betting_closed_at']);
            if ($now >= $closeTime || $round['betting_status'] !== 'open') {
                $db->rollBack();
                return ['success' => false, 'message' => 'Betting has closed for Round #' . $round['round_number'] . '.'];
            }

            // 2. Debit wallet balance with ledger record
            $betRef = 'BET-' . $round['round_number'] . '-' . strtoupper(substr(uniqid(), -6));
            $debitResult = Wallet::debit(
                $userId,
                $amount,
                'bet_placed',
                $betRef,
                'round_bet',
                "Placed bet of $" . number_format($amount, 2) . " on Round #{$round['round_number']} option $optionKey",
                $db
            );

            if (!$debitResult['success']) {
                $db->rollBack();
                return $debitResult;
            }

            // 3. Insert bet record
            $potentialPayout = $amount * 2.0; // standard 2x base multiplier
            $bStmt = $db->prepare("
                INSERT INTO bets (bet_ref, user_id, round_id, option_key, amount, potential_payout, status)
                VALUES (?, ?, ?, ?, ?, ?, 'placed')
            ");
            $bStmt->execute([$betRef, $userId, $roundId, $optionKey, $amount, $potentialPayout]);

            $db->commit();

            return [
                'success' => true,
                'message' => "Bet placed successfully on Round #{$round['round_number']}!",
                'bet_ref' => $betRef,
                'amount' => $amount,
                'option' => $optionKey,
                'new_balance' => $debitResult['balance_after']
            ];

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'message' => 'Failed to place bet: ' . $e->getMessage()];
        }
    }
}
