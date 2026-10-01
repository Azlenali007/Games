<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_user.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::getInstance()->getConnection();

// Handle Mark All Read
if (isset($_GET['mark_all'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL")->execute([$userId]);
    Security::setFlash('success', 'All notifications marked as read.');
    header("Location: /user/notifications.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY id DESC LIMIT 50");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

renderUserHeader('Notifications', 'dashboard');
?>
<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex items-center justify-between pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">ACTIVITY LOGS & ALERTS</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">System & Account Notifications</h1>
        </div>
        <a href="/user/notifications.php?mark_all=1" class="text-xs text-blue-400 hover:text-blue-300 font-medium">Mark all as read</a>
    </div>

    <div class="space-y-3 font-mono text-xs">
        <?php if (empty($notifications)): ?>
            <div class="p-8 text-center bg-[#0a0d14] rounded-xl border border-slate-800 text-slate-500">
                No notifications received yet.
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="p-4 rounded-xl border transition <?= $n['is_read'] ? 'bg-[#0a0d14] border-slate-800/80 text-slate-400' : 'bg-blue-950/20 border-blue-800/60 text-slate-200' ?>">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="font-bold text-white uppercase text-xs"><?= e($n['title']) ?></span>
                        <span class="text-[10px] text-slate-500"><?= e($n['created_at']) ?></span>
                    </div>
                    <p class="text-xs leading-relaxed text-slate-300 font-sans"><?= e($n['message']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
<?php renderUserFooter(); ?>
