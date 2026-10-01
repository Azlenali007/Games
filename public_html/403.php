<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout_user.php';

http_response_code(403);
renderUserHeader('403 Access Denied', '');
?>
<main class="flex-1 flex items-center justify-center px-4 py-16 text-center">
    <div class="max-w-md w-full p-8 rounded-2xl bg-[#0a0d14] border border-red-900/40 space-y-4">
        <div class="text-5xl font-black font-mono text-red-500">403</div>
        <h1 class="text-xl font-bold text-white">Access Forbidden</h1>
        <p class="text-xs text-slate-400">You do not possess the required security clearance or administrative role to access this resource.</p>
        <a href="/" class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
            Return to Arena Home &rarr;
        </a>
    </div>
</main>
<?php renderUserFooter(); ?>
