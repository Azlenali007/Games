<?php
/**
 * Admin Notifications & Broadcasts
 * Dispatch individual or platform-wide broadcast alerts with history logs.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Handle Notification Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_notification') {
    Security::requireCsrf();
    $target = trim($_POST['target'] ?? 'broadcast');
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $type = trim($_POST['type'] ?? 'system');

    if (empty($title) || empty($message)) {
        $error = 'Both notification title and message body are required.';
    } else {
        $targetUserId = null;
        if ($target !== 'broadcast') {
            $uStmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $uStmt->execute([$target]);
            $targetUserId = $uStmt->fetchColumn();
            if (!$targetUserId) {
                $error = "Target username '{$target}' not found.";
            }
        }

        if (!$error) {
            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$targetUserId, $title, $message, $type]);

            $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'SEND_NOTIFICATION', 'notifications', ?, ?, ?)")
               ->execute([Auth::id(), $target, "Dispatched $type notification: $title", Security::getClientIp()]);

            Security::setFlash('success', "Notification dispatched successfully to " . ($targetUserId ? "user @$target" : "all platform users (broadcast)"));
            header("Location: /admin/notifications.php");
            exit;
        }
    }
}

// Fetch notification history
$notifications = $db->query("
    SELECT n.*, u.username 
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    ORDER BY n.id DESC LIMIT 50
")->fetchAll();

renderAdminHeader('Broadcasts & Alerts', 'notifications');
?>
<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">COMMUNICATIONS & DISPATCH</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Notification Center</h1>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-red-950/40 border border-red-800 text-red-300 text-xs"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Send Form -->
        <div class="lg:col-span-5 p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Dispatch Notification</h3>
            <form method="POST" action="/admin/notifications.php" class="space-y-4">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="send_notification">

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Target Audience</label>
                    <select name="target" id="notif-target" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white">
                        <option value="broadcast">Global Broadcast (All Players)</option>
                        <option value="player1">Specific User: player1</option>
                        <option value="vip_gamer">Specific User: vip_gamer</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Notification Type</label>
                    <select name="type" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white">
                        <option value="system">System Announcement</option>
                        <option value="transaction">Transaction Notice</option>
                        <option value="round">Round / Gameplay Event</option>
                        <option value="account">Account Alert</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Title</label>
                    <input type="text" name="title" placeholder="e.g. Scheduled Engine Optimization" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Message Content</label>
                    <textarea name="message" rows="4" required placeholder="Type the message here..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"></textarea>
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase rounded-lg shadow-sm transition">
                    Broadcast Notification &rarr;
                </button>
            </form>
        </div>

        <!-- History Table -->
        <div class="lg:col-span-7 p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Notification Dispatch History</h3>
            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Title</th>
                            <th class="py-3 px-4">Recipient</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($notifications as $n): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4">
                                    <span class="font-bold text-white block"><?= e($n['title']) ?></span>
                                    <span class="text-[11px] text-slate-400 line-clamp-1"><?= e($n['message']) ?></span>
                                </td>
                                <td class="py-3 px-4 text-blue-400 font-bold"><?= $n['user_id'] ? e($n['username']) : 'BROADCAST' ?></td>
                                <td class="py-3 px-4 uppercase text-slate-400"><?= e($n['type']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($n['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
