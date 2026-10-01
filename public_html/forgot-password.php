<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/layout_user.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your account email.';
    } else {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // For security, always show success to prevent email enumeration
        $message = "If an account matches that email, a password reset link has been dispatched.";
    }
}

renderUserHeader('Forgot Password', 'login');
?>
<main class="flex-1 flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full">
        <div class="bg-[#0a0d14] border border-slate-800 rounded-2xl p-8 shadow-2xl">
            <h1 class="text-2xl font-bold text-white tracking-tight mb-2">Reset Password</h1>
            <p class="text-xs text-slate-400 mb-6">Enter your registered email address to receive password reset instructions.</p>

            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg bg-blue-950/40 border border-blue-800 text-blue-300 text-xs">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-950/40 border border-red-800 text-red-300 text-xs">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/forgot-password.php" class="space-y-4">
                <?= Security::csrfField() ?>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Email Address</label>
                    <input type="email" name="email" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition">
                    Send Reset Link &rarr;
                </button>
            </form>

            <div class="mt-4 text-center text-xs">
                <a href="/login.php" class="text-slate-400 hover:text-white">&larr; Return to Sign In</a>
            </div>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
