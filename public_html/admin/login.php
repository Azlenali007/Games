<?php
/**
 * Dedicated Admin Console Authentication
 * Isolated login portal for platform administrators.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

if (Auth::check() && Auth::isAdmin()) {
    header("Location: /admin/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $login = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = 'Both administrative credentials are required.';
    } else {
        $result = Auth::attempt($login, $password);
        if ($result['success']) {
            if ($result['user']['role'] !== 'admin') {
                Auth::logout();
                $error = 'Access Denied: Account lacks administrative privileges.';
            } else {
                Security::setFlash('success', 'Admin session initialized.');
                header("Location: /admin/index.php");
                exit;
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Administrator Login · Aura Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #07090e; font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 text-slate-300">
    <div class="max-w-md w-full">
        <div class="bg-[#0a0d14] border border-blue-950/80 rounded-2xl p-8 shadow-2xl">
            <div class="text-center mb-6">
                <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center font-bold text-white text-lg shadow-lg shadow-blue-600/30 mx-auto mb-3">
                    A
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Admin Console</h1>
                <p class="text-xs text-slate-400 mt-1">Master operational control and audit management.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-5 p-3 rounded-lg bg-red-950/50 border border-red-800 text-red-300 text-xs">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/admin/login.php" class="space-y-4">
                <?= Security::csrfField() ?>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Admin Username / Email</label>
                    <input type="text" name="username_or_email" value="admin" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Master Password</label>
                    <input type="password" name="password" value="AdminPassword123!" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase rounded-lg shadow-lg shadow-blue-600/30 transition">
                    Authenticate Console &rarr;
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-800 text-center text-xs">
                <a href="/" class="text-slate-500 hover:text-slate-300">&larr; Return to Public Platform</a>
            </div>
        </div>
    </div>
</body>
</html>
