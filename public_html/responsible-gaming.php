<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout_user.php';

renderUserHeader('Responsible Gaming Standards', 'responsible-gaming');
?>
<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="space-y-6 text-xs text-slate-400 leading-relaxed">
        <h1 class="text-3xl font-extrabold text-white tracking-tight">Responsible Gaming</h1>
        <p class="text-slate-500 font-mono text-[11px]">Player Protection & Self-Exclusion Framework</p>

        <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Commitment to Player Welfare</h2>
            <p>Aura encourages entertainment within manageable personal limits. Our base architecture includes configurable daily deposit caps, cooling-off intervals, and self-exclusion locks.</p>

            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Underage Protection</h2>
            <p>Participation is strictly forbidden to minors under 18 years of age. All user accounts are subject to identity verification prior to withdrawal authorization.</p>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
