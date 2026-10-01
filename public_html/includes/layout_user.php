<?php
/**
 * User Side Common Layout
 * Premium Dark Black + Blue Gaming Aesthetic
 * Strict isolation: Absolutely zero admin navigation or admin controls.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';

function renderUserHeader(string $title = '', string $activePage = ''): void {
    $user = Auth::user();
    $flash = Security::getFlash();
    $balance = $user ? (float)$user['wallet_balance'] : 0.0000;
    $currency = $user ? ($user['currency'] ?? 'USD') : 'USD';
    
    // Unread notifications count
    $unreadCount = 0;
    if ($user) {
        $db = Database::getInstance()->getConnection();
        $notifStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
        $notifStmt->execute([$user['id']]);
        $unreadCount = (int)$notifStmt->fetchColumn();
    }
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ? e($title) . ' · Aura Platform' : 'Aura Gaming Platform Base' ?></title>
    <meta name="description" content="Production-ready gaming platform base infrastructure featuring real PHP and MySQL architecture.">
    <!-- Tailwind CSS CDN for production styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            950: '#07090e',
                            900: '#0a0d14',
                            850: '#0d111a',
                            800: '#0f1422',
                            700: '#151c2e',
                            600: '#1e293b'
                        },
                        aura: {
                            blue: '#2563eb',
                            hover: '#3b82f6',
                            glow: '#1d4ed8'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #07090e;
            color: #cbd5e1;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            overflow-x: hidden;
        }
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-[#07090e] text-slate-300 antialiased selection:bg-blue-600 selection:text-white">

    <!-- Top Bar Contract: 3 Zones: Brand -> Nav Links -> User Actions -->
    <header class="sticky top-0 z-50 border-b border-slate-800/80 bg-[#0a0d14]/95 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            
            <!-- Zone 1: Wordmark Brand -->
            <div class="flex items-center gap-3">
                <a href="/" class="flex items-center gap-2 group">
                    <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-600/30 group-hover:bg-blue-500 transition">
                        A
                    </div>
                    <span class="text-xl font-bold tracking-tight text-white group-hover:text-blue-400 transition">AURA</span>
                </a>
            </div>

            <!-- Zone 2: Primary User Navigation Links -->
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
                <a href="/" class="transition-colors <?= $activePage === 'home' ? 'text-blue-400 font-semibold' : 'text-slate-300 hover:text-white' ?>">Home</a>
                <a href="/about.php" class="transition-colors <?= $activePage === 'about' ? 'text-blue-400 font-semibold' : 'text-slate-300 hover:text-white' ?>">About</a>
                <a href="/how-it-works.php" class="transition-colors <?= $activePage === 'how-it-works' ? 'text-blue-400 font-semibold' : 'text-slate-300 hover:text-white' ?>">How It Works</a>
                <a href="/faq.php" class="transition-colors <?= $activePage === 'faq' ? 'text-blue-400 font-semibold' : 'text-slate-300 hover:text-white' ?>">FAQ</a>
                <a href="/contact.php" class="transition-colors <?= $activePage === 'contact' ? 'text-blue-400 font-semibold' : 'text-slate-300 hover:text-white' ?>">Contact</a>

                <?php if ($user): ?>
                    <a href="/user/dashboard.php" class="transition-colors <?= $activePage === 'dashboard' ? 'text-blue-400 font-semibold' : 'text-slate-300 hover:text-white' ?>">Dashboard</a>
                    <a href="/user/rounds.php" class="transition-colors <?= $activePage === 'rounds' ? 'text-blue-400 font-semibold' : 'text-slate-300 hover:text-white' ?>">Rounds Arena</a>
                    <a href="/user/wallet.php" class="transition-colors <?= $activePage === 'wallet' ? 'text-blue-400 font-semibold' : 'text-slate-300 hover:text-white' ?>">Wallet & Ledger</a>
                <?php endif; ?>
            </nav>

            <!-- Zone 3: Primary Actions & User Menu -->
            <div class="flex items-center gap-3">
                <?php if ($user): ?>
                    <!-- Live Wallet Balance Indicator -->
                    <a href="/user/wallet.php" class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#0f1422] border border-blue-900/40 hover:border-blue-700/60 transition group">
                        <span class="text-xs text-slate-400">Balance:</span>
                        <span id="user-nav-balance" class="text-sm font-semibold font-mono text-emerald-400 tabular-nums">
                            $<?= number_format($balance, 2) ?>
                        </span>
                    </a>

                    <!-- Notifications Dropdown / Link -->
                    <a href="/user/notifications.php" class="relative p-2 rounded-lg bg-[#0f1422] border border-slate-800 text-slate-300 hover:text-white hover:border-slate-700 transition" title="Notifications">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <?php if ($unreadCount > 0): ?>
                            <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-blue-600 text-[10px] font-bold text-white flex items-center justify-center">
                                <?= $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- User Account Menu -->
                    <div class="relative group">
                        <button class="flex items-center gap-2 p-1.5 rounded-lg bg-[#0f1422] border border-slate-800 hover:border-slate-700 transition">
                            <div class="w-7 h-7 rounded-md bg-blue-950 border border-blue-700/40 text-blue-400 font-semibold text-xs flex items-center justify-center">
                                <?= strtoupper(substr($user['username'], 0, 2)) ?>
                            </div>
                            <span class="hidden sm:inline-block text-xs font-medium text-slate-200"><?= e($user['username']) ?></span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        
                        <!-- Dropdown -->
                        <div class="absolute right-0 mt-2 w-48 bg-[#0a0d14] border border-slate-800 rounded-xl shadow-2xl py-2 hidden group-hover:block transition">
                            <div class="px-4 py-2 border-b border-slate-800/80">
                                <div class="text-xs text-slate-400">Signed in as</div>
                                <div class="text-sm font-semibold text-white truncate"><?= e($user['username']) ?></div>
                            </div>
                            <a href="/user/dashboard.php" class="block px-4 py-2 text-xs text-slate-300 hover:bg-[#0f1422] hover:text-white">Dashboard</a>
                            <a href="/user/wallet.php" class="block px-4 py-2 text-xs text-slate-300 hover:bg-[#0f1422] hover:text-white">Wallet & Ledger</a>
                            <a href="/user/rounds.php" class="block px-4 py-2 text-xs text-slate-300 hover:bg-[#0f1422] hover:text-white">Rounds & Bets</a>
                            <a href="/user/referrals.php" class="block px-4 py-2 text-xs text-slate-300 hover:bg-[#0f1422] hover:text-white">Referrals & Affiliates</a>
                            <a href="/user/tickets.php" class="block px-4 py-2 text-xs text-slate-300 hover:bg-[#0f1422] hover:text-white">Support Tickets</a>
                            <a href="/user/profile.php" class="block px-4 py-2 text-xs text-slate-300 hover:bg-[#0f1422] hover:text-white">Security & Profile</a>
                            <div class="border-t border-slate-800/80 my-1"></div>
                            <a href="/logout.php" class="block px-4 py-2 text-xs text-red-400 hover:bg-red-950/20">Sign Out</a>
                        </div>
                    </div>

                <?php else: ?>
                    <a href="/login.php" class="px-4 py-2 text-xs font-medium text-slate-200 hover:text-white transition">Sign In</a>
                    <a href="/register.php" class="px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg shadow-lg shadow-blue-600/30 transition">Get Started</a>
                <?php endif; ?>

                <!-- Mobile Hamburger Toggle -->
                <button id="user-mobile-menu-toggle" class="md:hidden p-2 text-slate-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>
        </div>

        <!-- Mobile Drawer Menu -->
        <div id="user-mobile-menu" class="hidden md:hidden border-t border-slate-800 bg-[#0a0d14] px-4 py-4 space-y-2">
            <a href="/" class="block px-3 py-2 rounded-md text-sm <?= $activePage === 'home' ? 'bg-blue-600/10 text-blue-400' : 'text-slate-300' ?>">Home</a>
            <a href="/about.php" class="block px-3 py-2 rounded-md text-sm <?= $activePage === 'about' ? 'bg-blue-600/10 text-blue-400' : 'text-slate-300' ?>">About</a>
            <a href="/how-it-works.php" class="block px-3 py-2 rounded-md text-sm <?= $activePage === 'how-it-works' ? 'bg-blue-600/10 text-blue-400' : 'text-slate-300' ?>">How It Works</a>
            <a href="/faq.php" class="block px-3 py-2 rounded-md text-sm <?= $activePage === 'faq' ? 'bg-blue-600/10 text-blue-400' : 'text-slate-300' ?>">FAQ</a>
            <a href="/contact.php" class="block px-3 py-2 rounded-md text-sm <?= $activePage === 'contact' ? 'bg-blue-600/10 text-blue-400' : 'text-slate-300' ?>">Contact</a>

            <?php if ($user): ?>
                <div class="border-t border-slate-800 pt-2 my-2"></div>
                <div class="px-3 py-1 text-xs font-mono text-slate-500 uppercase">Player Account</div>
                <a href="/user/dashboard.php" class="block px-3 py-2 rounded-md text-sm <?= $activePage === 'dashboard' ? 'bg-blue-600/10 text-blue-400' : 'text-slate-300' ?>">Dashboard</a>
                <a href="/user/rounds.php" class="block px-3 py-2 rounded-md text-sm <?= $activePage === 'rounds' ? 'bg-blue-600/10 text-blue-400' : 'text-slate-300' ?>">Rounds Arena</a>
                <a href="/user/wallet.php" class="block px-3 py-2 rounded-md text-sm <?= $activePage === 'wallet' ? 'bg-blue-600/10 text-blue-400' : 'text-slate-300' ?>">Wallet & Ledger</a>
                <a href="/user/referrals.php" class="block px-3 py-2 rounded-md text-sm text-slate-300">Referrals</a>
                <a href="/user/tickets.php" class="block px-3 py-2 rounded-md text-sm text-slate-300">Support</a>
                <a href="/logout.php" class="block px-3 py-2 rounded-md text-sm text-red-400">Sign Out</a>
            <?php else: ?>
                <div class="border-t border-slate-800 pt-2 flex gap-2">
                    <a href="/login.php" class="flex-1 text-center py-2 text-xs font-medium text-slate-300 bg-slate-900 border border-slate-800 rounded-lg">Sign In</a>
                    <a href="/register.php" class="flex-1 text-center py-2 text-xs font-medium text-white bg-blue-600 rounded-lg">Register</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Flash Notifications Container -->
    <?php if ($flash): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="p-4 rounded-lg text-sm flex items-center justify-between border <?= 
                $flash['type'] === 'success' ? 'bg-emerald-950/40 border-emerald-800 text-emerald-300' : 
                ($flash['type'] === 'danger' ? 'bg-red-950/40 border-red-800 text-red-300' : 'bg-blue-950/40 border-blue-800 text-blue-300') 
            ?>">
                <span><?= e($flash['message']) ?></span>
                <button onclick="this.parentElement.remove()" class="text-xs opacity-60 hover:opacity-100">&times;</button>
            </div>
        </div>
    <?php endif; ?>

    <script>
        document.getElementById('user-mobile-menu-toggle')?.addEventListener('click', function() {
            var menu = document.getElementById('user-mobile-menu');
            menu.classList.toggle('hidden');
        });
    </script>
<?php
}

function renderUserFooter(): void {
?>
    <!-- Global Footer -->
    <footer class="mt-auto border-t border-slate-800/80 bg-[#07090e] py-12 text-slate-400 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-6 h-6 rounded bg-blue-600 flex items-center justify-center font-bold text-white text-xs">A</div>
                        <span class="text-sm font-bold text-white tracking-wide">AURA</span>
                    </div>
                    <p class="text-slate-500 leading-relaxed">
                        Next-generation common gaming platform infrastructure. Server-authoritative sequential rounds, ACID financial ledger, and real-time settlement engine.
                    </p>
                </div>

                <div>
                    <h4 class="text-xs font-semibold text-slate-200 uppercase tracking-wider mb-3">Platform</h4>
                    <ul class="space-y-2">
                        <li><a href="/about.php" class="hover:text-blue-400 transition">About Architecture</a></li>
                        <li><a href="/how-it-works.php" class="hover:text-blue-400 transition">How Rounds Work</a></li>
                        <li><a href="/faq.php" class="hover:text-blue-400 transition">Platform FAQ</a></li>
                        <li><a href="/contact.php" class="hover:text-blue-400 transition">Support Center</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-semibold text-slate-200 uppercase tracking-wider mb-3">Compliance & Legal</h4>
                    <ul class="space-y-2">
                        <li><a href="/terms.php" class="hover:text-blue-400 transition">Terms & Conditions</a></li>
                        <li><a href="/privacy.php" class="hover:text-blue-400 transition">Privacy Policy</a></li>
                        <li><a href="/responsible-gaming.php" class="hover:text-blue-400 transition">Responsible Gaming</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-semibold text-slate-200 uppercase tracking-wider mb-3">System Specifications</h4>
                    <div class="p-3 rounded-lg bg-[#0a0d14] border border-slate-800 text-[11px] font-mono space-y-1 text-slate-500">
                        <div>Server Time: <span id="server-live-clock" class="text-slate-300">UTC <?= date('H:i:s') ?></span></div>
                        <div>Engine: <span class="text-blue-400">Sequential Round #321+</span></div>
                        <div>Ledger: <span class="text-emerald-400">ACID MySQL Verified</span></div>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-800/80 pt-6 flex flex-col sm:flex-row items-center justify-between text-slate-500">
                <div>&copy; <?= date('Y') ?> Aura Gaming Platform Base. All rights reserved.</div>
                <div class="flex items-center gap-4 mt-2 sm:mt-0">
                    <span class="inline-flex items-center gap-1.5 text-emerald-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Platform Online
                    </span>
                    <span>18+ Play Responsibly</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Universal Server Clock Synchronization Script -->
    <script>
        (function() {
            var clockEl = document.getElementById('server-live-clock');
            if (clockEl) {
                var serverTimestamp = <?= time() ?> * 1000;
                var clientTimestampAtLoad = Date.now();
                setInterval(function() {
                    var currentServerMs = serverTimestamp + (Date.now() - clientTimestampAtLoad);
                    var d = new Date(currentServerMs);
                    var hours = String(d.getUTCHours()).padStart(2, '0');
                    var mins = String(d.getUTCMinutes()).padStart(2, '0');
                    var secs = String(d.getUTCSeconds()).padStart(2, '0');
                    clockEl.textContent = 'UTC ' + hours + ':' + mins + ':' + secs;
                }, 1000);
            }
        })();
    </script>
</body>
</html>
<?php
}
