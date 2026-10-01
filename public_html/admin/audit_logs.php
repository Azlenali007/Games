<?php
/**
 * Admin Audit & Security Logs
 * Complete system audit trail: administrative actions, round settlements, and security events.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$tab = $_GET['tab'] ?? 'admin'; // 'admin', 'rounds', 'logins'

if ($tab === 'rounds') {
    $logs = $db->query("
        SELECT a.*, r.round_number, u.username as admin_user 
        FROM round_audit_logs a
        JOIN rounds r ON a.round_id = r.id
        LEFT JOIN users u ON a.admin_id = u.id
        ORDER BY a.id DESC LIMIT 100
    ")->fetchAll();
} elseif ($tab === 'logins') {
    $logs = $db->query("
        SELECT l.*, u.username, u.email 
        FROM user_logins l
        JOIN users u ON l.user_id = u.id
        ORDER BY l.id DESC LIMIT 100
    ")->fetchAll();
} else {
    $logs = $db->query("
        SELECT a.*, u.username as admin_user 
        FROM admin_activity_logs a
        LEFT JOIN users u ON a.admin_id = u.id
        ORDER BY a.id DESC LIMIT 100
    ")->fetchAll();
}

renderAdminHeader('Audit & Security Logs', 'audit');
?>
<div class="space-y-6 font-mono text-xs">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-[11px] text-slate-500 uppercase">SYSTEM GOVERNANCE & PROVENANCE</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Security Audit Logs</h1>
        </div>

        <div class="flex items-center gap-1.5 p-1 bg-slate-900 border border-slate-800 rounded-xl">
            <a href="/admin/audit_logs.php?tab=admin" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'admin' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Admin Actions</a>
            <a href="/admin/audit_logs.php?tab=rounds" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'rounds' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Round Audits</a>
            <a href="/admin/audit_logs.php?tab=logins" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'logins' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Authentication</a>
        </div>
    </div>

    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs">
                <?php if ($tab === 'admin'): ?>
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Log ID</th>
                            <th class="py-3 px-4">Admin Operator</th>
                            <th class="py-3 px-4">Action</th>
                            <th class="py-3 px-4">Entity Target</th>
                            <th class="py-3 px-4">Audit Details</th>
                            <th class="py-3 px-4">IP Address</th>
                            <th class="py-3 px-4 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($logs as $l): ?>
                            <tr class="hover:bg-slate-900/30">
                                <td class="py-3 px-4 text-slate-500">#<?= $l['id'] ?></td>
                                <td class="py-3 px-4 text-blue-400 font-bold"><?= e($l['admin_user'] ?: 'System') ?></td>
                                <td class="py-3 px-4 uppercase font-bold text-white"><?= e($l['action']) ?></td>
                                <td class="py-3 px-4 text-slate-400"><?= e($l['target_entity']) ?> (<?= e($l['target_id']) ?>)</td>
                                <td class="py-3 px-4 text-slate-300 max-w-sm truncate"><?= e($l['details']) ?></td>
                                <td class="py-3 px-4 text-slate-400"><?= e($l['ip_address']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($l['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php elseif ($tab === 'rounds'): ?>
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Audit ID</th>
                            <th class="py-3 px-4">Round #</th>
                            <th class="py-3 px-4">Action</th>
                            <th class="py-3 px-4">Previous State</th>
                            <th class="py-3 px-4">New State</th>
                            <th class="py-3 px-4">Operator</th>
                            <th class="py-3 px-4">Audit Reason</th>
                            <th class="py-3 px-4 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($logs as $l): ?>
                            <tr class="hover:bg-slate-900/30">
                                <td class="py-3 px-4 text-slate-500">#<?= $l['id'] ?></td>
                                <td class="py-3 px-4 text-white font-bold">#<?= $l['round_number'] ?></td>
                                <td class="py-3 px-4 uppercase text-blue-400 font-bold"><?= e($l['action']) ?></td>
                                <td class="py-3 px-4 text-slate-500"><?= e($l['previous_state'] ?: 'None') ?></td>
                                <td class="py-3 px-4 font-bold text-emerald-400"><?= e($l['new_state']) ?></td>
                                <td class="py-3 px-4 text-slate-400"><?= e($l['admin_user'] ?: 'Cron Automator') ?></td>
                                <td class="py-3 px-4 text-slate-300"><?= e($l['reason']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($l['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php elseif ($tab === 'logins'): ?>
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Log ID</th>
                            <th class="py-3 px-4">User</th>
                            <th class="py-3 px-4">IP Address</th>
                            <th class="py-3 px-4">User Agent</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($logs as $l): ?>
                            <tr class="hover:bg-slate-900/30">
                                <td class="py-3 px-4 text-slate-500">#<?= $l['id'] ?></td>
                                <td class="py-3 px-4 font-bold text-white"><?= e($l['username']) ?></td>
                                <td class="py-3 px-4 text-slate-300"><?= e($l['ip_address']) ?></td>
                                <td class="py-3 px-4 text-slate-500 max-w-sm truncate"><?= e($l['user_agent']) ?></td>
                                <td class="py-3 px-4 uppercase font-bold <?= $l['status'] === 'success' ? 'text-emerald-400' : 'text-red-400' ?>"><?= e($l['status']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($l['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
