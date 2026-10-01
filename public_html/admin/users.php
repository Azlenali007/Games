<?php
/**
 * Admin User Management
 * View, Search, Filter, Inspect Wallet, Block/Unblock Users with Audit Logging.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$inspectId = (int)($_GET['inspect'] ?? 0);

// Handle Block/Unblock action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Security::requireCsrf();
    $targetUserId = (int)$_POST['user_id'];
    $act = $_POST['action'];

    if ($targetUserId === Auth::id()) {
        Security::setFlash('danger', 'You cannot alter your own administrative account status.');
    } else {
        $newStatus = ($act === 'block') ? 'blocked' : 'active';
        $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $targetUserId]);

        // Audit log
        $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, ?, 'user', ?, ?, ?)")
           ->execute([Auth::id(), strtoupper($act) . '_USER', $targetUserId, "User status changed to $newStatus", Security::getClientIp()]);

        Security::setFlash('success', "User #$targetUserId status updated to $newStatus.");
        header("Location: /admin/users.php?search=" . urlencode($search));
        exit;
    }
}

// Build query with filters
$query = "
    SELECT u.*, w.balance, w.locked_balance,
           (SELECT COUNT(*) FROM bets WHERE user_id = u.id) as total_bets,
           (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = u.id AND type='deposit' AND status='approved') as total_deposited
    FROM users u
    LEFT JOIN wallets w ON u.id = w.user_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $query .= " AND u.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY u.id DESC LIMIT 50";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Inspect single user details
$inspectedUser = null;
$inspectedLedger = [];
$inspectedLogins = [];
if ($inspectId > 0) {
    $iStmt = $db->prepare("
        SELECT u.*, w.balance, w.locked_balance 
        FROM users u 
        LEFT JOIN wallets w ON u.id = w.user_id 
        WHERE u.id = ? LIMIT 1
    ");
    $iStmt->execute([$inspectId]);
    $inspectedUser = $iStmt->fetch();

    if ($inspectedUser) {
        $lStmt = $db->prepare("SELECT * FROM wallet_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 15");
        $lStmt->execute([$inspectId]);
        $inspectedLedger = $lStmt->fetchAll();

        $loginStmt = $db->prepare("SELECT * FROM user_logins WHERE user_id = ? ORDER BY id DESC LIMIT 5");
        $loginStmt->execute([$inspectId]);
        $inspectedLogins = $loginStmt->fetchAll();
    }
}

renderAdminHeader('User Management', 'users');
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">ACCOUNTS DIRECTORY</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Platform Users</h1>
        </div>

        <!-- Search & Filter Controls -->
        <form method="GET" action="/admin/users.php" class="flex flex-wrap items-center gap-2">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search username / email..." class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500">
            <select name="status" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500">
                <option value="">All Statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                <option value="blocked" <?= $statusFilter === 'blocked' ? 'selected' : '' ?>>Blocked Only</option>
            </select>
            <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm">
                Filter
            </button>
            <?php if ($search || $statusFilter): ?>
                <a href="/admin/users.php" class="text-xs text-slate-400 hover:text-white px-2">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- User Inspection Modal/Drawer -->
    <?php if ($inspectedUser): ?>
        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-blue-900/60 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div>
                    <span class="text-xs font-mono text-blue-400">INSPECTING USER ACCOUNT #<?= $inspectedUser['id'] ?></span>
                    <h3 class="text-xl font-bold text-white"><?= e($inspectedUser['username']) ?> (<?= e($inspectedUser['email']) ?>)</h3>
                </div>
                <a href="/admin/users.php" class="text-xs text-slate-400 hover:text-white font-mono">&times; Close Inspection</a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800">
                    <span class="text-slate-500 text-xs font-mono uppercase block">Wallet Balance</span>
                    <span class="text-xl font-bold font-mono text-emerald-400">$<?= number_format((float)$inspectedUser['balance'], 2) ?></span>
                </div>
                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800">
                    <span class="text-slate-500 text-xs font-mono uppercase block">Locked Escrow</span>
                    <span class="text-xl font-bold font-mono text-slate-300">$<?= number_format((float)$inspectedUser['locked_balance'], 2) ?></span>
                </div>
                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800">
                    <span class="text-slate-500 text-xs font-mono uppercase block">Account Role</span>
                    <span class="text-xl font-bold font-mono text-blue-400 uppercase"><?= e($inspectedUser['role']) ?></span>
                </div>
                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800">
                    <span class="text-slate-500 text-xs font-mono uppercase block">Account Status</span>
                    <span class="text-xl font-bold font-mono uppercase <?= $inspectedUser['status'] === 'active' ? 'text-emerald-400' : 'text-red-400' ?>"><?= e($inspectedUser['status']) ?></span>
                </div>
            </div>

            <!-- User's Recent Ledger -->
            <div>
                <h4 class="text-xs font-mono font-bold uppercase text-slate-400 mb-2">Recent Ledger Mutations</h4>
                <div class="overflow-x-auto rounded-lg border border-slate-800 bg-[#07090e]">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-slate-900/60 border-b border-slate-800 text-slate-400 uppercase text-[10px]">
                            <tr>
                                <th class="p-2.5">ID</th>
                                <th class="p-2.5">Type</th>
                                <th class="p-2.5">Amount</th>
                                <th class="p-2.5">Before</th>
                                <th class="p-2.5">After</th>
                                <th class="p-2.5">Description</th>
                                <th class="p-2.5 text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($inspectedLedger as $il): ?>
                                <tr>
                                    <td class="p-2.5">#<?= $il['id'] ?></td>
                                    <td class="p-2.5 uppercase font-bold text-blue-400"><?= e($il['type']) ?></td>
                                    <td class="p-2.5 font-bold <?= (float)$il['amount'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">$<?= number_format((float)$il['amount'], 2) ?></td>
                                    <td class="p-2.5 text-slate-500">$<?= number_format((float)$il['balance_before'], 2) ?></td>
                                    <td class="p-2.5 text-white font-bold">$<?= number_format((float)$il['balance_after'], 2) ?></td>
                                    <td class="p-2.5 text-slate-400"><?= e($il['description']) ?></td>
                                    <td class="p-2.5 text-right text-slate-500"><?= substr($il['created_at'], 11) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">User ID</th>
                        <th class="py-3 px-4">Identity</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Wallet Balance</th>
                        <th class="py-3 px-4">Total Bets</th>
                        <th class="py-3 px-4">Total Deposited</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="py-8 text-center text-slate-500">No users match your criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 font-bold text-slate-400">#<?= $u['id'] ?></td>
                                <td class="py-3 px-4">
                                    <span class="font-bold text-white block"><?= e($u['username']) ?></span>
                                    <span class="text-[11px] text-slate-500"><?= e($u['email']) ?></span>
                                </td>
                                <td class="py-3 px-4 uppercase text-slate-400 font-semibold"><?= e($u['role']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $u['status'] === 'active' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-red-950 text-red-400 border border-red-800' ?>">
                                        <?= e($u['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-bold text-emerald-400">$<?= number_format((float)$u['balance'], 2) ?></td>
                                <td class="py-3 px-4 text-slate-300"><?= number_format($u['total_bets']) ?></td>
                                <td class="py-3 px-4 text-slate-300">$<?= number_format((float)$u['total_deposited'], 2) ?></td>
                                <td class="py-3 px-4 text-right space-x-2">
                                    <a href="/admin/users.php?inspect=<?= $u['id'] ?>" class="text-blue-400 hover:text-blue-300 underline font-medium">Inspect</a>
                                    
                                    <?php if ($u['id'] != Auth::id()): ?>
                                        <form method="POST" action="/admin/users.php" class="inline-block">
                                            <?= Security::csrfField() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <?php if ($u['status'] === 'active'): ?>
                                                <input type="hidden" name="action" value="block">
                                                <button type="submit" onclick="return confirm('Suspend user account #<?= $u['id'] ?>?')" class="text-red-400 hover:text-red-300 font-medium">Block</button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="unblock">
                                                <button type="submit" class="text-emerald-400 hover:text-emerald-300 font-medium">Unblock</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
