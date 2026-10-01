<?php
/**
 * User Profile & Security Settings
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_user.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    Security::requireCsrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$fullName, $phone, $userId]);
    Security::setFlash('success', 'Profile details updated.');
    header("Location: /user/profile.php");
    exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    Security::requireCsrf();
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPass, $user['password_hash'])) {
        $error = 'Your current password was incorrect.';
    } elseif (strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New passwords do not match.';
    } else {
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $userId]);
        Security::setFlash('success', 'Your password has been securely changed.');
        header("Location: /user/profile.php");
        exit;
    }
}

// Fetch user login activity logs
$loginStmt = $db->prepare("SELECT * FROM user_logins WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$loginStmt->execute([$userId]);
$logins = $loginStmt->fetchAll();

renderUserHeader('Account Security & Profile', 'dashboard');
?>
<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="pb-6 border-b border-slate-800">
        <div class="text-xs font-mono text-slate-500 uppercase">PLAYER CREDENTIALS</div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Security & Profile</h1>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-red-950/40 border border-red-800 text-red-300 text-xs">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Profile Info -->
        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h3 class="text-base font-bold text-white tracking-tight">Account Information</h3>
            <form method="POST" action="/user/profile.php" class="space-y-4">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="update_profile">

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Username (Immutable)</label>
                    <input type="text" value="<?= e($user['username']) ?>" disabled class="w-full bg-slate-900/60 border border-slate-800 rounded-lg px-3 py-2 text-xs font-mono text-slate-400 cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Email Address</label>
                    <input type="email" value="<?= e($user['email']) ?>" disabled class="w-full bg-slate-900/60 border border-slate-800 rounded-lg px-3 py-2 text-xs font-mono text-slate-400 cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Full Legal Name</label>
                    <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Phone Number</label>
                    <input type="text" name="phone" value="<?= e($user['phone']) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    Save Profile Changes
                </button>
            </form>
        </div>

        <!-- Password Change -->
        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h3 class="text-base font-bold text-white tracking-tight">Update Password</h3>
            <form method="POST" action="/user/profile.php" class="space-y-4">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="change_password">

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Current Password</label>
                    <input type="password" name="current_password" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">New Password (Min 8 Chars)</label>
                    <input type="password" name="new_password" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold rounded-lg transition">
                    Update Security Password
                </button>
            </form>
        </div>
    </div>

    <!-- Login Activity Log -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <h3 class="text-base font-bold text-white tracking-tight">Recent Login Sessions & Security Audits</h3>
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">Session ID</th>
                        <th class="py-3 px-4">IP Address</th>
                        <th class="py-3 px-4">User Agent / Device</th>
                        <th class="py-3 px-4">Authentication Status</th>
                        <th class="py-3 px-4 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($logins as $l): ?>
                        <tr class="hover:bg-slate-900/30 transition">
                            <td class="py-3 px-4 text-slate-400">#<?= $l['id'] ?></td>
                            <td class="py-3 px-4 text-white"><?= e($l['ip_address']) ?></td>
                            <td class="py-3 px-4 text-slate-400 max-w-xs truncate"><?= e($l['user_agent']) ?></td>
                            <td class="py-3 px-4 uppercase font-bold <?= $l['status'] === 'success' ? 'text-emerald-400' : 'text-red-400' ?>"><?= e($l['status']) ?></td>
                            <td class="py-3 px-4 text-right text-slate-500"><?= e($l['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
