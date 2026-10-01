<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout_user.php';

renderUserHeader('How Gaming Rounds Work', 'how-it-works');
?>
<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="space-y-12">
        <div class="text-center max-w-2xl mx-auto">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-950/60 border border-blue-800/60 text-xs text-blue-400 font-mono mb-4">
                <span>SYSTEM WORKFLOW</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">How the Round Engine Operates</h1>
            <p class="text-slate-400 text-sm mt-3 leading-relaxed">
                Step-by-step breakdown of how sequential rounds are generated, how betting windows open and close, and how settlements are cryptographically and financially verified.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 relative">
                <div class="text-3xl font-black font-mono text-blue-500/20 mb-3">01</div>
                <h3 class="text-sm font-bold text-white mb-2">Sequential Scheduling</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Round numbers increment sequentially (e.g., 321 &rarr; 322). Next rounds are pre-scheduled in MySQL with authoritative start and end times.
                </p>
            </div>

            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 relative">
                <div class="text-3xl font-black font-mono text-blue-500/20 mb-3">02</div>
                <h3 class="text-sm font-bold text-white mb-2">Betting Window</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Players participate during the active phase. Wallet balances are debited atomically with immediate ledger logging and bet slips issued.
                </p>
            </div>

            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 relative">
                <div class="text-3xl font-black font-mono text-blue-500/20 mb-3">03</div>
                <h3 class="text-sm font-bold text-white mb-2">Automated Lockout</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    10 seconds before round completion, the server locks betting status to <code class="text-red-400">closed</code>. No new bets are accepted.
                </p>
            </div>

            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 relative">
                <div class="text-3xl font-black font-mono text-blue-500/20 mb-3">04</div>
                <h3 class="text-sm font-bold text-white mb-2">ACID Settlement</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Results are declared, locked, and recorded in audit logs. Winning players receive atomic wallet credits with full transaction ledger integrity.
                </p>
            </div>
        </div>

        <div class="p-6 rounded-xl bg-[#0f1422] border border-blue-900/40 text-center">
            <h3 class="text-base font-semibold text-white mb-2">Ready to explore the active round?</h3>
            <p class="text-xs text-slate-400 mb-4">View live server timers, participation options, and verified settlement records.</p>
            <a href="/user/rounds.php" class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition">
                Enter Active Round Arena &rarr;
            </a>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
