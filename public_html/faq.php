<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout_user.php';

renderUserHeader('Frequently Asked Questions', 'faq');
?>
<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="space-y-8">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-950/60 border border-blue-800/60 text-xs text-blue-400 font-mono mb-4">
                <span>HELP & DOCUMENTATION</span>
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Frequently Asked Questions</h1>
            <p class="text-slate-400 text-sm mt-2">Answers to common questions regarding rounds, deposits, withdrawals, and platform rules.</p>
        </div>

        <div class="space-y-4">
            <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
                <h3 class="text-sm font-bold text-white mb-2">What is the Aura Gaming Platform Base?</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Aura is a standardized gaming framework. In this base phase, all core infrastructure (wallets, sequential round generator, payment gateways, admin live monitoring, and user accounts) is fully functional. Specific gaming titles are plugged into this architecture in subsequent phases.
                </p>
            </div>

            <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
                <h3 class="text-sm font-bold text-white mb-2">Why are Round IDs sequential rather than random?</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Sequential Round IDs (e.g. 321 &rarr; 322 &rarr; 323) ensure total transparency and auditability. No round can be skipped, replayed, or altered out of sequence.
                </p>
            </div>

            <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
                <h3 class="text-sm font-bold text-white mb-2">How does the wallet ledger work?</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Our platform utilizes an immutable financial ledger with ACID transactions in MySQL. Every single balance adjustment records the prior balance, new balance, exact amount, transaction reference, and timestamp.
                </p>
            </div>

            <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
                <h3 class="text-sm font-bold text-white mb-2">What payment methods are supported?</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Aura supports Direct Bank Wire / Instant ACH, Crypto USDT (TRC-20 and ERC-20), and Secure Card Processing with configurable deposit and withdrawal limits.
                </p>
            </div>

            <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
                <h3 class="text-sm font-bold text-white mb-2">Can unreleased round results be seen in advance?</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Strictly no. The server architecture guarantees that unreleased outcomes are never exposed in HTML, client JavaScript variables, API payloads, or hidden fields until officially declared and settled.
                </p>
            </div>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
