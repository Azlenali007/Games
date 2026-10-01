<?php
/**
 * User Login Page
 * Secure authentication with rate limiting and CSRF protection.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/layout_user.php';

if (Auth::check()) {
    header("Location: /user/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $login = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = 'Please provide both your username/email and password.';
    } else {
        $result = Auth::attempt($login, $password);
        if ($result['success']) {
            $user = $result['user'];
            Security::setFlash('success', 'Welcome back, ' . $user['username'] . '!');

            $intended = $_SESSION['intended_url'] ?? null;
            unset($_SESSION['intended_url']);

            if ($user['role'] === 'admin') {
                header("Location: /admin/index.php");
            } else {
                header("Location: " . ($intended ?: '/user/dashboard.php'));
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

renderUserHeader('Sign In', 'login');
?>
<main class="flex-1 flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full">
        <!-- Card -->
        <div class="bg-[#0a0d14] border border-slate-800 rounded-2xl p-8 shadow-2xl">
            <div class="text-center mb-6">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-600/30 mx-auto mb-3">
                    A
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Player Sign In</h1>
                <p class="text-xs text-slate-400 mt-1">Access your platform balance, sequential rounds, and bet history.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-5 p-3 rounded-lg bg-red-950/50 border border-red-800 text-red-300 text-xs">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login.php" class="space-y-4">
                <?= Security::csrfField() ?>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Username or Email</label>
                    <input type="text" name="username_or_email" value="<?= e($_POST['username_or_email'] ?? '') ?>" required autofocus class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 transition">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-medium text-slate-400">Password</label>
                        <a href="/forgot-password.php" class="text-[11px] text-blue-400 hover:text-blue-300">Forgot?</a>
                    </div>
                    <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 transition">
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition">
                    Sign In to Account &rarr;
                </button>
            </form>

            <!-- Demo Credentials Helper -->
            <div class="mt-6 pt-5 border-t border-slate-800/80">
                <div class="text-[11px] font-mono text-slate-500 uppercase tracking-wider mb-2">Verified Testing Accounts</div>
                <div class="space-y-1.5 text-xs font-mono">
                    <button type="button" onclick="fillCreds('player1', 'PlayerPassword123!')" class="w-full text-left p-2 rounded bg-slate-900/60 border border-slate-800 hover:border-blue-700/60 flex items-center justify-between text-slate-400 hover:text-white transition">
                        <span>Player: <strong class="text-blue-400">player1</strong> ($550 balance)</span>
                        <span class="text-[10px] text-slate-500">Click to fill</span>
                    </button>
                    <button type="button" onclick="fillCreds('vip_gamer', 'VipPassword123!')" class="w-full text-left p-2 rounded bg-slate-900/60 border border-slate-800 hover:border-blue-700/60 flex items-center justify-between text-slate-400 hover:text-white transition">
                        <span>VIP: <strong class="text-blue-400">vip_gamer</strong> ($1,250 balance)</span>
                        <span class="text-[10px] text-slate-500">Click to fill</span>
                    </button>
                    <button type="button" onclick="fillCreds('admin', 'AdminPassword123!')" class="w-full text-left p-2 rounded bg-slate-900/60 border border-slate-800 hover:border-blue-700/60 flex items-center justify-between text-slate-400 hover:text-white transition">
                        <span>Admin: <strong class="text-purple-400">admin</strong> (Master console)</span>
                        <span class="text-[10px] text-slate-500">Click to fill</span>
                    </button>
                </div>
            </div>

            <div class="mt-5 text-center text-xs text-slate-400">
                Don't have an account? <a href="/register.php" class="text-blue-400 hover:text-blue-300 font-medium">Create one now</a>
            </div>
        </div>
    </div>
</main>

<script>
function fillCreds(u, p) {
    document.querySelector('input[name="username_or_email"]').value = u;
    document.querySelector('input[name="password"]').value = p;
}
</script>

<?php renderUserFooter(); ?>
