<?php
/**
 * Admin Live Bet Monitoring Infrastructure
 * Real-time exposure calculations, option-wise bet totals, highest bets, individual bet records.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/round_engine.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$activeRound = RoundEngine::getActiveRound();
$countdown = RoundEngine::getCountdownData($activeRound);

$roundId = (int)($activeRound['id'] ?? 0);

// Total bets & volume on active round
$totalBets = 0;
$totalBetAmount = 0.0000;
$highestBet = 0.0000;
$optionStats = [];
$individualBets = [];
$maxExposure = 0.0000;

if ($roundId > 0) {
    // Aggregation query
    $stmt = $db->prepare("SELECT COUNT(*) as total_bets, COALESCE(SUM(amount), 0) as total_amount, COALESCE(MAX(amount), 0) as highest_bet FROM bets WHERE round_id = ?");
    $stmt->execute([$roundId]);
    $stats = $stmt->fetch();
    $totalBets = (int)$stats['total_bets'];
    $totalBetAmount = (float)$stats['total_amount'];
    $highestBet = (float)$stats['highest_bet'];

    // Option-wise breakdown
    $optStmt = $db->prepare("
        SELECT option_key, COUNT(*) as bet_count, COALESCE(SUM(amount), 0) as total_amount 
        FROM bets 
        WHERE round_id = ? 
        GROUP BY option_key
    ");
    $optStmt->execute([$roundId]);
    $rawOpts = $optStmt->fetchAll();

    foreach (RoundEngine::OUTCOMES as $outcome) {
        $optionStats[$outcome] = ['count' => 0, 'amount' => 0.0000, 'potential_payout' => 0.0000];
    }
    foreach ($rawOpts as $ro) {
        $k = $ro['option_key'];
        $amt = (float)$ro['total_amount'];
        $optionStats[$k] = [
            'count' => (int)$ro['bet_count'],
            'amount' => $amt,
            'potential_payout' => $amt * 2.0 // 2x multiplier exposure
        ];
        if (($amt * 2.0) > $maxExposure) {
            $maxExposure = $amt * 2.0;
        }
    }

    // Individual bet records
    $betStmt = $db->prepare("
        SELECT b.*, u.username, u.email 
        FROM bets b
        JOIN users u ON b.user_id = u.id
        WHERE b.round_id = ?
        ORDER BY b.id DESC LIMIT 50
    ");
    $betStmt->execute([$roundId]);
    $individualBets = $betStmt->fetchAll();
}

renderAdminHeader('Live Bet Monitoring', 'live_bets');
?>
<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">REAL-TIME RISK & EXPOSURE MONITORING</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Live Bet Monitoring</h1>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs font-mono text-slate-400">Monitoring Active Round:</span>
            <span class="text-base font-bold font-mono text-blue-400 px-3 py-1 rounded-lg bg-[#0a0d14] border border-blue-900/60">
                #<?= $countdown['round_number'] ?: '---' ?>
            </span>
        </div>
    </div>

    <!-- Active Round Master Telemetry Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 font-mono text-xs">
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-slate-500 uppercase text-[10px] block">Total Bets Placed</span>
            <span class="text-3xl font-bold text-white mt-1 block tabular-nums"><?= number_format($totalBets) ?></span>
            <span class="text-[11px] text-slate-500">Active Participants</span>
        </div>

        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-slate-500 uppercase text-[10px] block">Round Total Turnover</span>
            <span class="text-3xl font-bold text-emerald-400 mt-1 block tabular-nums">$<?= number_format($totalBetAmount, 2) ?></span>
            <span class="text-[11px] text-slate-500">Gross Pool Placed</span>
        </div>

        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-slate-500 uppercase text-[10px] block">Max Risk / Exposure</span>
            <span class="text-3xl font-bold text-amber-400 mt-1 block tabular-nums">$<?= number_format($maxExposure, 2) ?></span>
            <span class="text-[11px] text-slate-500">Worst-Case Payout Liability</span>
        </div>

        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-slate-500 uppercase text-[10px] block">Highest Individual Bet</span>
            <span class="text-3xl font-bold text-purple-400 mt-1 block tabular-nums">$<?= number_format($highestBet, 2) ?></span>
            <span class="text-[11px] text-slate-500">Max Single Slip Value</span>
        </div>
    </div>

    <!-- Option-Wise Distribution Analysis -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div>
            <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Option-Wise Channel Exposure Breakdown</h3>
            <p class="text-xs text-slate-400">Total bets and potential liabilities aggregated in real time per outcome channel.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 font-mono text-xs">
            <?php foreach (RoundEngine::OUTCOMES as $opt): ?>
                <?php $stats = $optionStats[$opt] ?? ['count' => 0, 'amount' => 0, 'potential_payout' => 0]; ?>
                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800/80 space-y-2">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <span class="text-base font-bold text-white"><?= $opt ?></span>
                        <span class="text-[10px] text-blue-400 font-bold uppercase">2.00x Odds</span>
                    </div>

                    <div class="flex justify-between text-slate-400">
                        <span>Slip Count:</span>
                        <span class="text-white font-bold"><?= number_format($stats['count']) ?></span>
                    </div>

                    <div class="flex justify-between text-slate-400">
                        <span>Total Wagered:</span>
                        <span class="text-emerald-400 font-bold">$<?= number_format($stats['amount'], 2) ?></span>
                    </div>

                    <div class="flex justify-between text-slate-400 border-t border-slate-800/60 pt-1.5">
                        <span>Max Liability:</span>
                        <span class="text-amber-400 font-bold">$<?= number_format($stats['potential_payout'], 2) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Individual Bet Slips Table -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Individual Live Bet Records</h3>
                <p class="text-xs text-slate-400">Every placed bet record recorded in the MySQL database.</p>
            </div>
            <span class="text-xs font-mono text-slate-500">Auto-Refreshed via Database</span>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">Bet Ref</th>
                        <th class="py-3 px-4">User</th>
                        <th class="py-3 px-4">Selected Channel</th>
                        <th class="py-3 px-4">Wager Amount</th>
                        <th class="py-3 px-4">Potential Return</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($individualBets)): ?>
                        <tr><td colspan="7" class="py-8 text-center text-slate-500">No bets placed on active Round #<?= $countdown['round_number'] ?> yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($individualBets as $b): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 font-bold text-white"><?= e($b['bet_ref']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="text-blue-400 font-bold"><?= e($b['username']) ?></span>
                                    <span class="text-[10px] text-slate-500 block"><?= e($b['email']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded bg-blue-950 text-blue-400 font-bold border border-blue-800">
                                        <?= e($b['option_key']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-bold text-white">$<?= number_format((float)$b['amount'], 2) ?></td>
                                <td class="py-3 px-4 font-bold text-emerald-400">$<?= number_format((float)$b['potential_payout'], 2) ?></td>
                                <td class="py-3 px-4 uppercase text-blue-400 font-bold"><?= e($b['status']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($b['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
