<?php
/**
 * Aura Gaming Platform Base - Landing Page
 * Real PHP + MySQL architecture with live sequential round engine.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/round_engine.php';
require_once __DIR__ . '/includes/layout_user.php';

// Check maintenance mode
$db = Database::getInstance()->getConnection();
$maint = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'")->fetchColumn();
if ($maint === '1' && !Auth::isAdmin()) {
    $msg = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_message'")->fetchColumn();
    http_response_code(503);
    die("<!DOCTYPE html><html lang='en'><head><title>Maintenance</title><style>body{background:#07090e;color:#94a3b8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}div{text-align:center;padding:2.5rem;background:#0f1422;border:1px solid #1e293b;border-radius:12px;max-width:440px;}h2{color:#3b82f6;}p{color:#64748b;line-height:1.6;}</style></head><body><div><h2>Platform Under Maintenance</h2><p>" . htmlspecialchars($msg ?: 'Routine system maintenance in progress. Please check back shortly.') . "</p></div></body></html>");
}

$activeRound = RoundEngine::getActiveRound();
$countdown = RoundEngine::getCountdownData($activeRound);
$completedRounds = RoundEngine::getCompletedRounds(5);

// Real platform stats from MySQL
$totalRoundsCount = (int)$db->query("SELECT COUNT(*) FROM rounds WHERE status='completed'")->fetchColumn();
$totalBetsVolume = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM bets")->fetchColumn();
$totalActivePlayers = (int)$db->query("SELECT COUNT(*) FROM users WHERE status='active' AND role='user'")->fetchColumn();

renderUserHeader('Premier Next-Gen Gaming Infrastructure', 'home');
?>

<!-- Hero Section -->
<section class="relative overflow-hidden pt-12 pb-20 border-b border-slate-800/80">
    <div class="absolute inset-0 bg-gradient-to-b from-blue-950/20 via-transparent to-transparent pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            <!-- Left Hero Content -->
            <div class="lg:col-span-7 space-y-6">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-950/60 border border-blue-800/60 text-xs text-blue-400 font-mono">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                    <span>REAL-TIME SERVER SYNCHRONIZED INFRASTRUCTURE</span>
                </div>

                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white tracking-tight leading-tight">
                    Next-Generation Gaming Engine & Common Base.
                </h1>

                <p class="text-base sm:text-lg text-slate-400 max-w-2xl leading-relaxed">
                    A production-grade gaming platform foundation built on pure PHP and MySQL architecture. Features server-authoritative sequential rounds, ACID financial ledger, and real-time live monitoring.
                </p>

                <div class="flex flex-wrap items-center gap-4 pt-2">
                    <?php if (Auth::check()): ?>
                        <a href="/user/rounds.php" class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-xl shadow-lg shadow-blue-600/30 transition flex items-center gap-2">
                            <span>Enter Active Round Arena</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                        <a href="/user/wallet.php" class="px-6 py-3 bg-[#0f1422] hover:bg-slate-800 text-slate-200 text-sm font-semibold rounded-xl border border-slate-700 transition">
                            Manage Wallet & Ledger
                        </a>
                    <?php else: ?>
                        <a href="/register.php" class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-xl shadow-lg shadow-blue-600/30 transition flex items-center gap-2">
                            <span>Create Player Account</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                        <a href="/login.php" class="px-6 py-3 bg-[#0f1422] hover:bg-slate-800 text-slate-200 text-sm font-semibold rounded-xl border border-slate-700 transition">
                            Sign In to Play
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Proof Bar -->
                <div class="pt-6 border-t border-slate-800/80 grid grid-cols-3 gap-4">
                    <div>
                        <div class="text-xs text-slate-500 uppercase font-mono">Completed Rounds</div>
                        <div class="text-xl font-bold font-mono text-white tabular-nums mt-0.5"><?= number_format($totalRoundsCount) ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 uppercase font-mono">Platform Volume</div>
                        <div class="text-xl font-bold font-mono text-emerald-400 tabular-nums mt-0.5">$<?= number_format($totalBetsVolume, 2) ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 uppercase font-mono">Active Members</div>
                        <div class="text-xl font-bold font-mono text-blue-400 tabular-nums mt-0.5"><?= number_format($totalActivePlayers) ?></div>
                    </div>
                </div>
            </div>

            <!-- Right Hero: Live Interactive Round Widget -->
            <div class="lg:col-span-5">
                <div class="bg-[#0a0d14] border border-blue-900/50 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
                    <!-- Subtle Glow Border -->
                    <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div>
                            <span class="text-xs font-mono text-slate-400">ACTIVE SEQUENTIAL ROUND</span>
                            <div class="text-2xl font-black font-mono text-white mt-0.5">
                                #<?= $countdown['round_number'] ?: '---' ?>
                            </div>
                        </div>

                        <div id="hero-betting-badge" class="px-2.5 py-1 rounded-md text-xs font-mono font-bold <?= $countdown['betting_open'] ? 'bg-emerald-950/80 border border-emerald-700/60 text-emerald-400' : 'bg-red-950/80 border border-red-700/60 text-red-400' ?>">
                            <?= $countdown['betting_open'] ? 'BETTING OPEN' : 'BETTING CLOSED' ?>
                        </div>
                    </div>

                    <!-- Countdown Display -->
                    <div class="my-6 text-center py-5 bg-[#07090e] rounded-xl border border-slate-800/80">
                        <div class="text-xs font-mono text-slate-500 uppercase tracking-widest mb-1">Round Closes In</div>
                        <div id="hero-countdown-clock" class="text-5xl font-black font-mono text-blue-400 tracking-wider tabular-nums">
                            <?= sprintf('%02d:%02d', floor($countdown['seconds_remaining'] / 60), $countdown['seconds_remaining'] % 60) ?>
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono mt-2">
                            Cutoff: <span id="hero-betting-cutoff" class="text-slate-300"><?= $countdown['betting_seconds_remaining'] ?>s lead</span> · Server Synchronized
                        </div>
                    </div>

                    <!-- Common Options Representation -->
                    <div class="space-y-2 mb-6">
                        <div class="text-xs font-medium text-slate-400">Available Round Channels</div>
                        <div class="grid grid-cols-2 gap-2">
                            <?php foreach (RoundEngine::OUTCOMES as $opt): ?>
                                <div class="p-2.5 rounded-lg bg-slate-900/80 border border-slate-800 flex items-center justify-between">
                                    <span class="text-xs font-mono font-semibold text-white"><?= $opt ?></span>
                                    <span class="text-[10px] font-mono text-blue-400">2.00x Payout</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (Auth::check()): ?>
                        <a href="/user/rounds.php" class="w-full block text-center py-3 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase tracking-wider rounded-xl shadow-lg shadow-blue-600/30 transition">
                            Participate in Round #<?= $countdown['round_number'] ?> &rarr;
                        </a>
                    <?php else: ?>
                        <a href="/login.php" class="w-full block text-center py-3 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase tracking-wider rounded-xl shadow-lg shadow-blue-600/30 transition">
                            Sign In to Participate &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Core Platform Architecture Features -->
<section class="py-16 border-b border-slate-800/80 bg-[#07090e]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <h2 class="text-xs font-mono text-blue-400 uppercase tracking-widest mb-2">COMMON ARCHITECTURE</h2>
            <p class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Built Exclusively on Pure PHP & MySQL Persistence</p>
            <p class="text-sm text-slate-400 mt-2">Zero mock data. Zero simulated cron. Authoritative server-side financial transactions and sequential round execution.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Feature 1 -->
            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 hover:border-blue-900/50 transition">
                <div class="w-10 h-10 rounded-lg bg-blue-950 border border-blue-800/60 text-blue-400 flex items-center justify-center font-bold mb-4 font-mono">01</div>
                <h3 class="text-base font-semibold text-white mb-2">Sequential Round Engine</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Round IDs proceed sequentially (321 &rarr; 322 &rarr; 323). Server-enforced start, end, and betting closure states with immutable audit logs.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 hover:border-blue-900/50 transition">
                <div class="w-10 h-10 rounded-lg bg-blue-950 border border-blue-800/60 text-blue-400 flex items-center justify-center font-bold mb-4 font-mono">02</div>
                <h3 class="text-base font-semibold text-white mb-2">ACID Financial Ledger</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Every wallet modification creates an atomic ledger record with balance_before, balance_after, and reference IDs. Fixed decimal(16,4) precision.
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800 hover:border-blue-900/50 transition">
                <div class="w-10 h-10 rounded-lg bg-blue-950 border border-blue-800/60 text-blue-400 flex items-center justify-center font-bold mb-4 font-mono">03</div>
                <h3 class="text-base font-semibold text-white mb-2">Live Bet Monitoring</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Dedicated administrator dashboard for live exposure calculations, option-wise bet totals, user risk analysis, and manual/automated result declaration.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Verified Completed Rounds History Table -->
<section class="py-16 bg-[#0a0d14]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-white tracking-tight">Recent Verified Settlements</h3>
                <p class="text-xs text-slate-400">All results officially locked and settled in the MySQL database.</p>
            </div>
            <a href="/user/rounds.php" class="text-xs text-blue-400 hover:text-blue-300 font-medium">View All History &rarr;</a>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900/70 border-b border-slate-800 text-slate-400 font-mono uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">Round ID</th>
                        <th class="py-3 px-4">Declared Outcome</th>
                        <th class="py-3 px-4">Result State</th>
                        <th class="py-3 px-4">Settlement State</th>
                        <th class="py-3 px-4 text-right">Settled Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono">
                    <?php if (empty($completedRounds)): ?>
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">No completed rounds recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($completedRounds as $cr): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 font-bold text-white">#<?= $cr['round_number'] ?></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded bg-blue-950/80 border border-blue-800/60 text-blue-300 font-bold">
                                        <?= e($cr['declared_result']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-emerald-400 font-semibold uppercase"><?= e($cr['result_status']) ?></td>
                                <td class="py-3 px-4 text-slate-300"><?= e($cr['settlement_status']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($cr['end_time']) ?> UTC</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Client Side Server-Synchronized Countdown Script -->
<script>
(function() {
    var countdownEl = document.getElementById('hero-countdown-clock');
    var badgeEl = document.getElementById('hero-betting-badge');
    var cutoffEl = document.getElementById('hero-betting-cutoff');

    function syncCountdown() {
        fetch('/api/countdown.php')
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (json.success && json.data) {
                    var d = json.data;
                    var rem = d.seconds_remaining;
                    var mins = Math.floor(rem / 60);
                    var secs = rem % 60;
                    countdownEl.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

                    if (d.betting_open) {
                        badgeEl.textContent = 'BETTING OPEN';
                        badgeEl.className = 'px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-emerald-950/80 border border-emerald-700/60 text-emerald-400';
                        cutoffEl.textContent = d.betting_seconds_remaining + 's lead';
                    } else {
                        badgeEl.textContent = 'BETTING CLOSED';
                        badgeEl.className = 'px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-red-950/80 border border-red-700/60 text-red-400';
                        cutoffEl.textContent = 'Closed';
                    }
                }
            })
            .catch(function(err) {
                console.error("Countdown sync error", err);
            });
    }

    // Poll every 1000ms for authoritative server time
    setInterval(syncCountdown, 1000);
})();
</script>

<?php renderUserFooter(); ?>
