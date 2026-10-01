<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout_user.php';

renderUserHeader('Privacy Policy', 'privacy');
?>
<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="space-y-6 text-xs text-slate-400 leading-relaxed">
        <h1 class="text-3xl font-extrabold text-white tracking-tight">Privacy Policy</h1>
        <p class="text-slate-500 font-mono text-[11px]">Strict Data Protection & Confidentiality Standards</p>

        <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Information Collected</h2>
            <p>We collect essential operational data required for secure authentication, financial integrity, and regulatory compliance, including usernames, email addresses, transaction logs, and IP access audits.</p>

            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Cryptographic Security</h2>
            <p>All passwords are encrypted utilizing standard bcrypt algorithms with dedicated salt rounds. Financial communications and API webhooks are signed using HMAC verification.</p>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
