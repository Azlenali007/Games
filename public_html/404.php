<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout_user.php';

http_response_code(404);
renderUserHeader('404 Page Not Found', '');
?>
<main class="flex-1 flex items-center justify-center px-4 py-16 text-center">
    <div class="max-w-md w-full p-8 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div class="text-5xl font-black font-mono text-blue-500">404</div>
        <h1 class="text-xl font-bold text-white">Resource Not Found</h1>
        <p class="text-xs text-slate-400">The platform route or gaming resource you requested does not exist or has been relocated.</p>
        <a href="/" class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
            Return to Arena Home &rarr;
        </a>
    </div>
</main>
<?php renderUserFooter(); ?>
