<?php
/**
 * Master Admin Dashboard
 * Real MySQL aggregation queries. Absolutely zero mock data or placeholder numbers.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/round_engine.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

// Real Database Queries
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$activeUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active'")->fetchColumn();
$totalBalance = (float)$db->query("SELECT COALESCE(SUM(balance), 0) FROM wallets")->fetchColumn();
$totalLocked = (float)$db->query("SELECT COALESCE(SUM(locked_balance), 0) FROM wallets")->fetchColumn();

$totalDeposits = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'deposit' AND status = 'approved'")->fetchColumn();
$totalWithdrawals = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'withdrawal' AND status = 'approved'")->fetchColumn();
$totalTransactionsCount = (int)$db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();

$totalBetsCount = (int)$db->query("SELECT COUNT(*) FROM bets")->fetchColumn();
$totalBetAmount = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM bets")->fetchColumn();

$activeRoundsCount = (int)$db->query("SELECT COUNT(*) FROM rounds WHERE status = 'active'")->fetchColumn();
$completedRoundsCount = (int)$db->query("SELECT COUNT(*) FROM rounds WHERE status = 'completed'")->fetchColumn();

// Active Round info
$activeRound = RoundEngine::getActiveRound();
$countdown = RoundEngine::getCountdownData($activeRound);

// Recent Users
$recentUsers = $db->query("
    SELECT u.*, w.balance 
    FROM users u 
    LEFT JOIN wallets w ON u.id = w.user_id 
    ORDER BY u.id DESC LIMIT 5
")->fetchAll();

// Recent Transactions
$recentTransactions = $db->query("
    SELECT t.*, u.username 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.id DESC LIMIT 5
")->fetchAll();

// Recent Admin Activity Logs
$recentActivities = $db->query("
    SELECT a.*, u.username as admin_user 
    FROM admin_activity_logs a
    LEFT JOIN users u ON a.admin_id = u.id
    ORDER BY a.id DESC LIMIT 6
")->fetchAll();

renderAdminHeader('Overview Dashboard', 'dashboard');
?>
<div class="space-y-8">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">OPERATIONAL COMMAND</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Platform Master Overview</h1>
        </div>
        <div class="flex items-center gap-3">
            <a href="/admin/rounds.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                Manage Rounds
            </a>
            <a href="/admin/live_bets.php" class="px-4 py-2 bg-[#0f1422] hover:bg-slate-800 text-slate-200 border border-slate-700 text-xs font-semibold rounded-lg transition">
                Live Bet Monitor
            </a>
        </div>
    </div>

    <!-- Active Sequential Round Live Banner -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-blue-900/50 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-xs font-mono text-blue-400 uppercase tracking-wider">ACTIVE SEQUENTIAL ROUND</span>
            </div>
            <div class="text-3xl font-black font-mono text-white">
                ROUND #<?= $countdown['round_number'] ?: '---' ?>
            </div>
            <div class="text-xs font-mono text-slate-400 mt-1">
                Started: <?= substr($activeRound['start_time'] ?? '', 11) ?> UTC · Closes: <?= substr($activeRound['end_time'] ?? '', 11) ?> UTC
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div class="text-center p-3.5 rounded-xl bg-[#07090e] border border-slate-800 min-w-[140px]">
                <div class="text-[10px] font-mono text-slate-500 uppercase">Server Countdown</div>
                <div id="admin-dash-timer" class="text-3xl font-black font-mono text-blue-400 tabular-nums">
                    <?= sprintf('%02d:%02d', floor($countdown['seconds_remaining'] / 60), $countdown['seconds_remaining'] % 60) ?>
                </div>
            </div>

            <div class="space-y-1.5">
                <div class="px-3 py-1 rounded-md text-xs font-mono font-bold uppercase <?= $countdown['betting_open'] ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-red-950 text-red-400 border border-red-800' ?>">
                    <?= $countdown['betting_open'] ? 'Betting Open' : 'Betting Closed' ?>
                </div>
                <a href="/admin/live_bets.php" class="text-xs text-blue-400 hover:underline block font-mono text-center">
                    Inspect Bets &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- 8 Metric Cards: Real Database Aggregations -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Users -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Total Users</span>
            <div class="text-2xl font-bold font-mono text-white tabular-nums mt-1"><?= number_format($totalUsers) ?></div>
            <span class="text-[11px] text-slate-500">Active Players: <strong class="text-blue-400 font-mono"><?= number_format($activeUsers) ?></strong></span>
        </div>

        <!-- 2. Wallet Balance -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Platform Wallet Liability</span>
            <div class="text-2xl font-bold font-mono text-emerald-400 tabular-nums mt-1">$<?= number_format($totalBalance, 2) ?></div>
            <span class="text-[11px] text-slate-500">Escrow Locked: <strong class="text-slate-300 font-mono">$<?= number_format($totalLocked, 2) ?></strong></span>
        </div>

        <!-- 3. Total Deposits -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Approved Deposits</span>
            <div class="text-2xl font-bold font-mono text-emerald-400 tabular-nums mt-1">$<?= number_format($totalDeposits, 2) ?></div>
            <span class="text-[11px] text-slate-500">Verified On-Chain & Bank</span>
        </div>

        <!-- 4. Total Withdrawals -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Approved Withdrawals</span>
            <div class="text-2xl font-bold font-mono text-slate-200 tabular-nums mt-1">$<?= number_format($totalWithdrawals, 2) ?></div>
            <span class="text-[11px] text-slate-500">Released Payouts</span>
        </div>

        <!-- 5. Total Transactions -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Total Transactions</span>
            <div class="text-2xl font-bold font-mono text-white tabular-nums mt-1"><?= number_format($totalTransactionsCount) ?></div>
            <span class="text-[11px] text-slate-500">Deposit, Payout, Bonus</span>
        </div>

        <!-- 6. Total Bets & Volume -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Total Bets Placed</span>
            <div class="text-2xl font-bold font-mono text-blue-400 tabular-nums mt-1"><?= number_format($totalBetsCount) ?></div>
            <span class="text-[11px] text-slate-500">Turnover: <strong class="text-white font-mono">$<?= number_format($totalBetAmount, 2) ?></strong></span>
        </div>

        <!-- 7. Active Rounds -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Active Rounds</span>
            <div class="text-2xl font-bold font-mono text-white tabular-nums mt-1"><?= number_format($activeRoundsCount) ?></div>
            <span class="text-[11px] text-slate-500">Currently in execution</span>
        </div>

        <!-- 8. Completed Rounds -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Settled Rounds</span>
            <div class="text-2xl font-bold font-mono text-emerald-400 tabular-nums mt-1"><?= number_format($completedRoundsCount) ?></div>
            <span class="text-[11px] text-slate-500">Locked & Ledger Audited</span>
        </div>
    </div>

    <!-- 2 Column Grids: Recent Users & Recent Transactions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Recent Users -->
        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Recently Registered Users</h3>
                <a href="/admin/users.php" class="text-xs text-blue-400 hover:text-blue-300">View All Users &rarr;</a>
            </div>

            <div class="space-y-2.5 font-mono text-xs">
                <?php foreach ($recentUsers as $u): ?>
                    <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-white"><?= e($u['username']) ?></span>
                            <span class="text-[10px] text-slate-500 ml-2">(<?= e($u['role']) ?>)</span>
                            <div class="text-[11px] text-slate-400"><?= e($u['email']) ?></div>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-emerald-400">$<?= number_format((float)$u['balance'], 2) ?></span>
                            <span class="block text-[10px] uppercase <?= $u['status'] === 'active' ? 'text-emerald-400' : 'text-red-400' ?>"><?= e($u['status']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Recent Platform Transactions</h3>
                <a href="/admin/deposits.php" class="text-xs text-blue-400 hover:text-blue-300">Manage Financials &rarr;</a>
            </div>

            <div class="space-y-2.5 font-mono text-xs">
                <?php foreach ($recentTransactions as $t): ?>
                    <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-white"><?= e($t['transaction_ref']) ?></span>
                                <span class="text-[10px] uppercase font-bold <?= $t['type'] === 'deposit' ? 'text-emerald-400' : 'text-blue-400' ?>"><?= e($t['type']) ?></span>
                            </div>
                            <span class="text-[11px] text-slate-500"><?= e($t['username']) ?> · <?= e($t['payment_method']) ?></span>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-white">$<?= number_format((float)$t['amount'], 2) ?></span>
                            <span class="block text-[10px] uppercase <?= 
                                $t['status'] === 'approved' ? 'text-emerald-400 font-bold' : 
                                ($t['status'] === 'pending' ? 'text-amber-400 font-bold' : 'text-red-400') 
                            ?>"><?= e($t['status']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Admin Activity Audit Trail -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider">Recent Administrative Audit Logs</h3>
            <a href="/admin/audit_logs.php" class="text-xs text-blue-400 hover:text-blue-300">Full Audit History &rarr;</a>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">Log ID</th>
                        <th class="py-3 px-4">Administrator</th>
                        <th class="py-3 px-4">Action Dispatched</th>
                        <th class="py-3 px-4">Entity</th>
                        <th class="py-3 px-4">Details</th>
                        <th class="py-3 px-4 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($recentActivities)): ?>
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">No admin activities logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $a): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 text-slate-400">#<?= $a['id'] ?></td>
                                <td class="py-3 px-4 font-bold text-blue-400"><?= e($a['admin_user'] ?: 'System') ?></td>
                                <td class="py-3 px-4 uppercase font-bold text-white"><?= e($a['action']) ?></td>
                                <td class="py-3 px-4 text-slate-400"><?= e($a['target_entity']) ?> (<?= e($a['target_id']) ?>)</td>
                                <td class="py-3 px-4 text-slate-400 max-w-xs truncate"><?= e($a['details']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($a['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    var timerEl = document.getElementById('admin-dash-timer');
    function sync() {
        fetch('/api/countdown.php')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success && res.data && timerEl) {
                    var rem = res.data.seconds_remaining;
                    var m = Math.floor(rem / 60);
                    var s = rem % 60;
                    timerEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                }
            });
    }
    setInterval(sync, 1000);
})();
</script>

<?php renderAdminFooter(); ?>
