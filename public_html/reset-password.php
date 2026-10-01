<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/layout_user.php';

renderUserHeader('Reset Password', 'login');
?>
<main class="flex-1 flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full">
        <div class="bg-[#0a0d14] border border-slate-800 rounded-2xl p-8 shadow-2xl">
            <h1 class="text-2xl font-bold text-white tracking-tight mb-2">Create New Password</h1>
            <p class="text-xs text-slate-400 mb-6">Choose a secure password with at least 8 characters.</p>

            <form method="POST" action="/login.php" class="space-y-4">
                <?= Security::csrfField() ?>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">New Password</label>
                    <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Confirm New Password</label>
                    <input type="password" name="password_confirm" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition">
                    Update Password &rarr;
                </button>
            </form>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
