<?php
/**
 * Wallet & Financial Ledger Engine
 * Authoritative financial mutation logic with ACID transaction integrity.
 * Strictly forbids modifying balance without atomic ledger entries.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class Wallet {
    /**
     * Get user wallet details
     */
    public static function getWallet(int $userId): ?array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();

        if (!$wallet) {
            // Auto-create wallet if missing
            $db->prepare("INSERT INTO wallets (user_id, balance, locked_balance, currency) VALUES (?, 0.0000, 0.0000, 'USD')")
               ->execute([$userId]);
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch();
        }

        return $wallet;
    }

    /**
     * Get current liquid balance
     */
    public static function getBalance(int $userId): float {
        $wallet = self::getWallet($userId);
        return (float)($wallet['balance'] ?? 0.0000);
    }

    /**
     * Credit funds to a user wallet with atomic ledger logging
     */
    public static function credit(
        int $userId,
        float $amount,
        string $type,
        string $referenceId = '',
        string $referenceType = '',
        string $description = '',
        ?PDO $existingPdo = null
    ): array {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Credit amount must be positive.'];
        }

        $pdo = $existingPdo ?? Database::getInstance()->getConnection();
        $isLocalTx = false;

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $isLocalTx = true;
        }

        try {
            // Select FOR UPDATE to lock row against race conditions
            $stmt = $pdo->prepare("SELECT balance, locked_balance FROM wallets WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if (!$row) {
                // Insert wallet
                $pdo->prepare("INSERT INTO wallets (user_id, balance, locked_balance, currency) VALUES (?, 0.0000, 0.0000, 'USD')")
                    ->execute([$userId]);
                $balanceBefore = 0.0000;
            } else {
                $balanceBefore = (float)$row['balance'];
            }

            $balanceAfter = $balanceBefore + $amount;

            // 1. Update wallet balance
            $upStmt = $pdo->prepare("UPDATE wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?");
            $upStmt->execute([$balanceAfter, $userId]);

            // 2. Insert into wallet_ledger
            $lStmt = $pdo->prepare("
                INSERT INTO wallet_ledger (user_id, amount, balance_before, balance_after, type, reference_id, reference_type, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $lStmt->execute([
                $userId,
                $amount,
                $balanceBefore,
                $balanceAfter,
                $type,
                $referenceId,
                $referenceType,
                $description
            ]);

            if ($isLocalTx) {
                $pdo->commit();
            }

            return [
                'success' => true,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'credited' => $amount
            ];

        } catch (Exception $e) {
            if ($isLocalTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Wallet credit failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Financial ledger transaction failed: ' . $e->getMessage()];
        }
    }

    /**
     * Debit funds from a user wallet with atomic ledger logging and balance sufficiency check
     */
    public static function debit(
        int $userId,
        float $amount,
        string $type,
        string $referenceId = '',
        string $referenceType = '',
        string $description = '',
        ?PDO $existingPdo = null
    ): array {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Debit amount must be positive.'];
        }

        $pdo = $existingPdo ?? Database::getInstance()->getConnection();
        $isLocalTx = false;

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $isLocalTx = true;
        }

        try {
            // Row lock
            $stmt = $pdo->prepare("SELECT balance, locked_balance FROM wallets WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if (!$row) {
                throw new Exception("User wallet not found.");
            }

            $balanceBefore = (float)$row['balance'];

            if ($balanceBefore < $amount) {
                if ($isLocalTx) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'message' => 'Insufficient wallet balance. Available: $' . number_format($balanceBefore, 2)];
            }

            $balanceAfter = $balanceBefore - $amount;

            // 1. Update wallet balance
            $upStmt = $pdo->prepare("UPDATE wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?");
            $upStmt->execute([$balanceAfter, $userId]);

            // 2. Insert into wallet_ledger (negative amount for debits in description, positive magnitude recorded)
            $lStmt = $pdo->prepare("
                INSERT INTO wallet_ledger (user_id, amount, balance_before, balance_after, type, reference_id, reference_type, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $lStmt->execute([
                $userId,
                -$amount,
                $balanceBefore,
                $balanceAfter,
                $type,
                $referenceId,
                $referenceType,
                $description
            ]);

            if ($isLocalTx) {
                $pdo->commit();
            }

            return [
                'success' => true,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'debited' => $amount
            ];

        } catch (Exception $e) {
            if ($isLocalTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Wallet debit failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Financial ledger debit failed: ' . $e->getMessage()];
        }
    }

    /**
     * Lock funds for pending withdrawal
     */
    public static function lockFunds(int $userId, float $amount, ?PDO $existingPdo = null): bool {
        $pdo = $existingPdo ?? Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("
            UPDATE wallets 
            SET balance = balance - ?, locked_balance = locked_balance + ?
            WHERE user_id = ? AND balance >= ?
        ");
        $stmt->execute([$amount, $amount, $userId, $amount]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Unlock/release funds back to active balance
     */
    public static function unlockFunds(int $userId, float $amount, ?PDO $existingPdo = null): bool {
        $pdo = $existingPdo ?? Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("
            UPDATE wallets 
            SET balance = balance + ?, locked_balance = locked_balance - ?
            WHERE user_id = ? AND locked_balance >= ?
        ");
        $stmt->execute([$amount, $amount, $userId, $amount]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Burn locked funds when withdrawal is finalized
     */
    public static function finalizeLockedFunds(int $userId, float $amount, ?PDO $existingPdo = null): bool {
        $pdo = $existingPdo ?? Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("
            UPDATE wallets 
            SET locked_balance = locked_balance - ?
            WHERE user_id = ? AND locked_balance >= ?
        ");
        $stmt->execute([$amount, $userId, $amount]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get user ledger history
     */
    public static function getLedger(int $userId, int $limit = 50, int $offset = 0): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT * FROM wallet_ledger 
            WHERE user_id = ? 
            ORDER BY id DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
