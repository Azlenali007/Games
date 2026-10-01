<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout_user.php';

renderUserHeader('Terms & Conditions', 'terms');
?>
<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="space-y-6 text-xs text-slate-400 leading-relaxed">
        <h1 class="text-3xl font-extrabold text-white tracking-tight">Terms & Conditions</h1>
        <p class="text-slate-500 font-mono text-[11px]">Last Updated: October 2026 · Platform Base Infrastructure v1.0</p>
        
        <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">1. Acceptance of Terms</h2>
            <p>By creating an account or accessing the Aura Gaming Platform Base, users agree to be bound by these Terms of Service. Participation is strictly limited to individuals aged 18 years or older who reside in jurisdictions where online participation is lawful.</p>
            
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">2. Server-Authoritative Sequencing</h2>
            <p>All round sequencing (e.g. 321 &rarr; 322), timers, betting lockouts, and outcomes are governed exclusively by the server's MySQL database timestamps. Client-side timers are for visual aid only.</p>

            <h2 class="text-sm font-bold text-white uppercase tracking-wider">3. Financial Ledger Integrity</h2>
            <p>All balances are maintained in fixed-precision currency units. Users acknowledge that balance adjustments occur strictly through atomic ledger transactions. Any attempt to exploit network latency or reverse engineered payloads will result in immediate account suspension.</p>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
