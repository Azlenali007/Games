<?php
/**
 * Admin Comprehensive Analytics & Financial Reports
 * Real MySQL aggregation queries with Date, User, Status, Type, and Round filters.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$reportType = $_GET['type'] ?? 'transactions'; // 'transactions', 'deposits', 'withdrawals', 'bets', 'rounds', 'settlements', 'referrals'
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$userFilter = trim($_GET['user'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$roundFilter = (int)($_GET['round_id'] ?? 0);

$results = [];
$summary = ['total_count' => 0, 'total_amount' => 0.0000];

// 1. Transaction Reports
if ($reportType === 'transactions' || $reportType === 'deposits' || $reportType === 'withdrawals') {
    $sql = "
        SELECT t.*, u.username, u.email 
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE DATE(t.created_at) >= ? AND DATE(t.created_at) <= ?
    ";
    $params = [$dateFrom, $dateTo];

    if ($reportType === 'deposits') {
        $sql .= " AND t.type = 'deposit'";
    } elseif ($reportType === 'withdrawals') {
        $sql .= " AND t.type = 'withdrawal'";
    }

    if ($userFilter !== '') {
        $sql .= " AND u.username LIKE ?";
        $params[] = "%$userFilter%";
    }
    if ($statusFilter !== '') {
        $sql .= " AND t.status = ?";
        $params[] = $statusFilter;
    }

    $sql .= " ORDER BY t.id DESC LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    foreach ($results as $r) {
        $summary['total_count']++;
        $summary['total_amount'] += (float)$r['amount'];
    }
}

// 2. Betting Reports
if ($reportType === 'bets') {
    $sql = "
        SELECT b.*, u.username, r.round_number 
        FROM bets b
        JOIN users u ON b.user_id = u.id
        JOIN rounds r ON b.round_id = r.id
        WHERE DATE(b.created_at) >= ? AND DATE(b.created_at) <= ?
    ";
    $params = [$dateFrom, $dateTo];

    if ($userFilter !== '') {
        $sql .= " AND u.username LIKE ?";
        $params[] = "%$userFilter%";
    }
    if ($statusFilter !== '') {
        $sql .= " AND b.status = ?";
        $params[] = $statusFilter;
    }
    if ($roundFilter > 0) {
        $sql .= " AND r.round_number = ?";
        $params[] = $roundFilter;
    }

    $sql .= " ORDER BY b.id DESC LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    foreach ($results as $r) {
        $summary['total_count']++;
        $summary['total_amount'] += (float)$r['amount'];
    }
}

// 3. Round Reports & Settlements
if ($reportType === 'rounds' || $reportType === 'settlements') {
    $sql = "
        SELECT r.*,
               (SELECT COUNT(*) FROM bets WHERE round_id = r.id) as bet_count,
               (SELECT COALESCE(SUM(amount), 0) FROM bets WHERE round_id = r.id) as total_volume,
               (SELECT COALESCE(SUM(payout_amount), 0) FROM bets WHERE round_id = r.id AND status = 'won') as total_payout
        FROM rounds r
        WHERE DATE(r.start_time) >= ? AND DATE(r.start_time) <= ?
    ";
    $params = [$dateFrom, $dateTo];

    if ($statusFilter !== '') {
        $sql .= " AND r.status = ?";
        $params[] = $statusFilter;
    }
    if ($roundFilter > 0) {
        $sql .= " AND r.round_number = ?";
        $params[] = $roundFilter;
    }

    $sql .= " ORDER BY r.round_number DESC LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    foreach ($results as $r) {
        $summary['total_count']++;
        $summary['total_amount'] += (float)$r['total_volume'];
    }
}

renderAdminHeader('Analytics & Reports', 'reports');
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">ENTERPRISE AUDIT & REPORTING</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Database Reports</h1>
        </div>

        <!-- Report Type Tabs -->
        <div class="flex flex-wrap items-center gap-1 p-1 bg-slate-900 border border-slate-800 rounded-xl text-xs font-mono">
            <a href="/admin/reports.php?type=transactions" class="px-2.5 py-1 rounded-lg <?= $reportType === 'transactions' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Transactions</a>
            <a href="/admin/reports.php?type=deposits" class="px-2.5 py-1 rounded-lg <?= $reportType === 'deposits' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Deposits</a>
            <a href="/admin/reports.php?type=withdrawals" class="px-2.5 py-1 rounded-lg <?= $reportType === 'withdrawals' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Withdrawals</a>
            <a href="/admin/reports.php?type=bets" class="px-2.5 py-1 rounded-lg <?= $reportType === 'bets' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Betting</a>
            <a href="/admin/reports.php?type=rounds" class="px-2.5 py-1 rounded-lg <?= $reportType === 'rounds' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Rounds</a>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="p-5 rounded-2xl bg-[#0a0d14] border border-slate-800">
        <form method="GET" action="/admin/reports.php" class="grid grid-cols-1 sm:grid-cols-5 gap-3 text-xs font-mono">
            <input type="hidden" name="type" value="<?= e($reportType) ?>">

            <div>
                <label class="block text-[11px] text-slate-400 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white">
            </div>

            <div>
                <label class="block text-[11px] text-slate-400 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white">
            </div>

            <div>
                <label class="block text-[11px] text-slate-400 mb-1">Filter User</label>
                <input type="text" name="user" value="<?= e($userFilter) ?>" placeholder="Username..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white">
            </div>

            <div>
                <label class="block text-[11px] text-slate-400 mb-1">Status</label>
                <input type="text" name="status" value="<?= e($statusFilter) ?>" placeholder="e.g. approved / won" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-1.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg shadow-sm">
                    Execute Query
                </button>
                <a href="/admin/reports.php?type=<?= e($reportType) ?>" class="py-1.5 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-center">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Summary Box -->
    <div class="grid grid-cols-2 gap-4 font-mono text-xs">
        <div class="p-4 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-slate-500 block uppercase">Matching Records Count</span>
            <span class="text-2xl font-bold text-white mt-1 block"><?= number_format($summary['total_count']) ?></span>
        </div>
        <div class="p-4 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-slate-500 block uppercase">Matching Aggregate Volume</span>
            <span class="text-2xl font-bold text-emerald-400 mt-1 block">$<?= number_format($summary['total_amount'], 2) ?></span>
        </div>
    </div>

    <!-- Report Table -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <?php if ($reportType === 'transactions' || $reportType === 'deposits' || $reportType === 'withdrawals'): ?>
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Ref #</th>
                            <th class="py-3 px-4">User</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Method</th>
                            <th class="py-3 px-4">Gross Amount</th>
                            <th class="py-3 px-4">Fee</th>
                            <th class="py-3 px-4">Net Amount</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($results as $row): ?>
                            <tr class="hover:bg-slate-900/30">
                                <td class="py-3 px-4 font-bold text-white"><?= e($row['transaction_ref']) ?></td>
                                <td class="py-3 px-4 text-blue-400"><?= e($row['username']) ?></td>
                                <td class="py-3 px-4 uppercase"><?= e($row['type']) ?></td>
                                <td class="py-3 px-4 text-slate-300"><?= e($row['payment_method']) ?></td>
                                <td class="py-3 px-4 text-white font-bold">$<?= number_format((float)$row['amount'], 2) ?></td>
                                <td class="py-3 px-4 text-slate-500">$<?= number_format((float)$row['fee'], 2) ?></td>
                                <td class="py-3 px-4 text-emerald-400 font-bold">$<?= number_format((float)$row['net_amount'], 2) ?></td>
                                <td class="py-3 px-4 uppercase font-bold text-[10px]"><?= e($row['status']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php elseif ($reportType === 'bets'): ?>
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Bet Ref</th>
                            <th class="py-3 px-4">Round #</th>
                            <th class="py-3 px-4">User</th>
                            <th class="py-3 px-4">Channel</th>
                            <th class="py-3 px-4">Wager</th>
                            <th class="py-3 px-4">Payout</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($results as $row): ?>
                            <tr class="hover:bg-slate-900/30">
                                <td class="py-3 px-4 font-bold text-white"><?= e($row['bet_ref']) ?></td>
                                <td class="py-3 px-4 font-bold text-blue-400">#<?= $row['round_number'] ?></td>
                                <td class="py-3 px-4"><?= e($row['username']) ?></td>
                                <td class="py-3 px-4 font-bold"><?= e($row['option_key']) ?></td>
                                <td class="py-3 px-4 text-white">$<?= number_format((float)$row['amount'], 2) ?></td>
                                <td class="py-3 px-4 text-emerald-400 font-bold">$<?= number_format((float)$row['payout_amount'], 2) ?></td>
                                <td class="py-3 px-4 uppercase font-bold"><?= e($row['status']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php elseif ($reportType === 'rounds'): ?>
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Round ID</th>
                            <th class="py-3 px-4">Result</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Settlement</th>
                            <th class="py-3 px-4">Bets Placed</th>
                            <th class="py-3 px-4">Volume Pool</th>
                            <th class="py-3 px-4">Total Payouts</th>
                            <th class="py-3 px-4 text-right">Start Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($results as $row): ?>
                            <tr class="hover:bg-slate-900/30">
                                <td class="py-3 px-4 font-bold text-white">#<?= $row['round_number'] ?></td>
                                <td class="py-3 px-4 font-bold text-blue-400"><?= e($row['declared_result'] ?: 'Pending') ?></td>
                                <td class="py-3 px-4 uppercase"><?= e($row['status']) ?></td>
                                <td class="py-3 px-4 uppercase"><?= e($row['settlement_status']) ?></td>
                                <td class="py-3 px-4"><?= number_format($row['bet_count']) ?></td>
                                <td class="py-3 px-4 text-white font-bold">$<?= number_format((float)$row['total_volume'], 2) ?></td>
                                <td class="py-3 px-4 text-emerald-400 font-bold">$<?= number_format((float)$row['total_payout'], 2) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($row['start_time']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
