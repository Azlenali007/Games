<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout_user.php';

renderUserHeader('About Platform Architecture', 'about');
?>
<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="space-y-12">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-950/60 border border-blue-800/60 text-xs text-blue-400 font-mono mb-4">
                <span>SYSTEM SPECIFICATION</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">About Aura Base Infrastructure</h1>
            <p class="text-slate-400 text-base sm:text-lg mt-3 leading-relaxed">
                Aura provides the standardized common framework for hosting modern, real-time gaming rounds. Designed specifically to separate game design logic from foundational account, ledger, security, and settlement layers.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800">
                <h3 class="text-lg font-bold text-white mb-2">Architectural Principles</h3>
                <ul class="space-y-3 text-xs text-slate-400 leading-relaxed">
                    <li class="flex items-start gap-2">
                        <span class="text-blue-400 font-bold font-mono">1.</span>
                        <span><strong class="text-slate-200">Server-Authoritative Clock:</strong> All countdowns, betting lockouts, and outcomes are governed by UTC MySQL server timestamps, eliminating client desynchronization.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-blue-400 font-bold font-mono">2.</span>
                        <span><strong class="text-slate-200">ACID Financial Ledger:</strong> Every deposit, debit, bet, payout, and adjustment is recorded with absolute transaction safety. Balances are never modified in isolation.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-blue-400 font-bold font-mono">3.</span>
                        <span><strong class="text-slate-200">Modular Plug & Play:</strong> Game developers can integrate custom game logic via standardized API contracts without re-engineering wallet or user management.</span>
                    </li>
                </ul>
            </div>

            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800">
                <h3 class="text-lg font-bold text-white mb-2">Technology Stack Integrity</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    The platform runs entirely on pure PHP 8.2+ and normalized MySQL/MariaDB database schemas, styled with Tailwind CSS. It is natively compatible with standard Linux hosting environments without third-party framework overhead.
                </p>
                <div class="p-4 rounded-lg bg-[#07090e] border border-slate-800 font-mono text-[11px] space-y-1.5 text-slate-400">
                    <div>Runtime: <span class="text-white">PHP 8.2 CLI / FPM</span></div>
                    <div>Database: <span class="text-white">MariaDB / MySQL InnoDB</span></div>
                    <div>Ledger Precision: <span class="text-white">DECIMAL(16,4)</span></div>
                    <div>Concurrency: <span class="text-white">Row Locks (SELECT FOR UPDATE)</span></div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
