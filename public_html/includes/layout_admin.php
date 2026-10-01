<?php
/**
 * Admin Panel Layout & Sidebar Navigation
 * Completely separate from user-facing platform.
 * Desktop: Sidebar + Top bar. Mobile: Hamburger Drawer.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';

function renderAdminHeader(string $title = '', string $activePage = ''): void {
    $admin = Auth::requireAdmin();
    $flash = Security::getFlash();

    // Pending counts for badges
    $db = Database::getInstance()->getConnection();
    $pendingDeposits = (int)$db->query("SELECT COUNT(*) FROM transactions WHERE type='deposit' AND status='pending'")->fetchColumn();
    $pendingWithdrawals = (int)$db->query("SELECT COUNT(*) FROM transactions WHERE type='withdrawal' AND status='pending'")->fetchColumn();
    $openTickets = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'in_progress')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ? e($title) . ' · Aura Admin Console' : 'Aura Admin Console' ?></title>
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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            overflow-x: hidden;
        }
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="min-h-screen flex bg-[#07090e] text-slate-300 antialiased selection:bg-blue-600 selection:text-white">

    <!-- DESKTOP ADMIN SIDEBAR (260px) -->
    <aside class="hidden lg:flex lg:flex-col w-64 border-r border-slate-800 bg-[#0a0d14] shrink-0 sticky top-0 h-screen overflow-y-auto">
        <!-- Admin Brand -->
        <div class="h-16 flex items-center gap-3 px-6 border-b border-slate-800 shrink-0">
            <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-600/30">
                A
            </div>
            <div>
                <span class="text-base font-bold tracking-wide text-white">AURA</span>
                <span class="text-[10px] bg-blue-950 border border-blue-800/80 text-blue-400 font-mono px-1.5 py-0.5 rounded ml-1.5 uppercase font-semibold">Admin</span>
            </div>
        </div>

        <!-- Sidebar Navigation Menu -->
        <nav class="flex-1 px-4 py-4 space-y-1 text-xs font-medium">
            <div class="px-2 pb-1 text-[11px] font-mono text-slate-500 uppercase tracking-wider">Core Systems</div>
            
            <a href="/admin/index.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'dashboard' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span>Dashboard</span>
            </a>

            <a href="/admin/users.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'users' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <span>User Management</span>
            </a>

            <div class="pt-3 px-2 pb-1 text-[11px] font-mono text-slate-500 uppercase tracking-wider">Round Infrastructure</div>

            <a href="/admin/rounds.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'rounds' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Sequential Rounds</span>
            </a>

            <a href="/admin/live_bets.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'live_bets' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                <span>Live Bet Monitoring</span>
            </a>

            <a href="/admin/results.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'results' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Result & Settlement</span>
            </a>

            <div class="pt-3 px-2 pb-1 text-[11px] font-mono text-slate-500 uppercase tracking-wider">Financial & Treasury</div>

            <a href="/admin/deposits.php" class="flex items-center justify-between px-3 py-2 rounded-lg transition <?= $activePage === 'deposits' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path></svg>
                    <span>Deposits</span>
                </div>
                <?php if ($pendingDeposits > 0): ?>
                    <span class="px-1.5 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-[10px] font-mono font-bold"><?= $pendingDeposits ?></span>
                <?php endif; ?>
            </a>

            <a href="/admin/withdrawals.php" class="flex items-center justify-between px-3 py-2 rounded-lg transition <?= $activePage === 'withdrawals' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path></svg>
                    <span>Withdrawals</span>
                </div>
                <?php if ($pendingWithdrawals > 0): ?>
                    <span class="px-1.5 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-[10px] font-mono font-bold"><?= $pendingWithdrawals ?></span>
                <?php endif; ?>
            </a>

            <div class="pt-3 px-2 pb-1 text-[11px] font-mono text-slate-500 uppercase tracking-wider">Operations & Marketing</div>

            <a href="/admin/tickets.php" class="flex items-center justify-between px-3 py-2 rounded-lg transition <?= $activePage === 'tickets' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                    <span>Support Tickets</span>
                </div>
                <?php if ($openTickets > 0): ?>
                    <span class="px-1.5 py-0.5 rounded-full bg-blue-500/20 text-blue-400 text-[10px] font-mono font-bold"><?= $openTickets ?></span>
                <?php endif; ?>
            </a>

            <a href="/admin/referrals.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'referrals' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span>Referral System</span>
            </a>

            <a href="/admin/promotions.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'promotions' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                <span>Promotions & Coupons</span>
            </a>

            <a href="/admin/notifications.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'notifications' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                <span>Broadcasts & Alerts</span>
            </a>

            <a href="/admin/reports.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'reports' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Analytics & Reports</span>
            </a>

            <div class="pt-3 px-2 pb-1 text-[11px] font-mono text-slate-500 uppercase tracking-wider">System Governance</div>

            <a href="/admin/settings.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'settings' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Settings & Gateways</span>
            </a>

            <a href="/admin/audit_logs.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition <?= $activePage === 'audit' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-[#0f1422]' ?>">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                <span>Audit & Security Logs</span>
            </a>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-slate-800 text-xs text-slate-500 flex items-center justify-between">
            <span class="truncate"><?= e($admin['email']) ?></span>
            <a href="/logout.php" class="text-red-400 hover:text-red-300 font-medium">Logout</a>
        </div>
    </aside>

    <!-- RIGHT MAIN CONTENT VIEWPORT -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- ADMIN TOPBAR (Mobile Toggle + Breadcrumbs + Live DB Clock) -->
        <header class="h-16 border-b border-slate-800 bg-[#0a0d14]/90 backdrop-blur-md px-4 sm:px-6 flex items-center justify-between sticky top-0 z-40">
            <div class="flex items-center gap-3">
                <button id="admin-mobile-toggle" class="lg:hidden p-2 text-slate-400 hover:text-white rounded-lg bg-slate-900 border border-slate-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="flex items-center gap-2 text-xs font-mono text-slate-400">
                    <span>Admin Console</span>
                    <span class="text-slate-600">/</span>
                    <span class="text-white font-medium capitalize"><?= e($activePage ?: 'Overview') ?></span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden sm:flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900 border border-slate-800 text-xs font-mono text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Database: MariaDB Online</span>
                </div>

                <a href="/user/dashboard.php" class="text-xs text-blue-400 hover:text-blue-300 font-medium flex items-center gap-1">
                    <span>View User Site</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            </div>
        </header>

        <!-- ADMIN MOBILE DRAWER -->
        <div id="admin-mobile-drawer" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm lg:hidden">
            <div class="w-64 h-full bg-[#0a0d14] border-r border-slate-800 flex flex-col p-4">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div class="font-bold text-white tracking-wide">AURA ADMIN</div>
                    <button id="admin-mobile-close" class="text-slate-400 hover:text-white text-lg">&times;</button>
                </div>
                <nav class="flex-1 py-4 space-y-1 text-xs">
                    <a href="/admin/index.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'dashboard' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Dashboard</a>
                    <a href="/admin/users.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'users' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">User Management</a>
                    <a href="/admin/rounds.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'rounds' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Sequential Rounds</a>
                    <a href="/admin/live_bets.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'live_bets' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Live Bet Monitoring</a>
                    <a href="/admin/results.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'results' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Result & Settlement</a>
                    <a href="/admin/deposits.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'deposits' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Deposits</a>
                    <a href="/admin/withdrawals.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'withdrawals' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Withdrawals</a>
                    <a href="/admin/tickets.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'tickets' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Support Tickets</a>
                    <a href="/admin/referrals.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'referrals' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Referrals</a>
                    <a href="/admin/promotions.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'promotions' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Promotions</a>
                    <a href="/admin/notifications.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'notifications' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Broadcasts</a>
                    <a href="/admin/reports.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'reports' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Reports</a>
                    <a href="/admin/settings.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'settings' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Settings</a>
                    <a href="/admin/audit_logs.php" class="block px-3 py-2 rounded-lg <?= $activePage === 'audit' ? 'bg-blue-600 text-white' : 'text-slate-300' ?>">Audit Logs</a>
                </nav>
                <div class="border-t border-slate-800 pt-3">
                    <a href="/logout.php" class="block text-center py-2 bg-red-950/40 text-red-400 border border-red-900/60 rounded-lg text-xs">Logout Admin</a>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($flash): ?>
            <div class="p-4 sm:p-6 pb-0">
                <div class="p-4 rounded-lg text-sm flex items-center justify-between border <?= 
                    $flash['type'] === 'success' ? 'bg-emerald-950/40 border-emerald-800 text-emerald-300' : 
                    ($flash['type'] === 'danger' ? 'bg-red-950/40 border-red-800 text-red-300' : 'bg-blue-950/40 border-blue-800 text-blue-300') 
                ?>">
                    <span><?= e($flash['message']) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-xs opacity-60 hover:opacity-100">&times;</button>
                </div>
            </div>
        <?php endif; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
<?php
}

function renderAdminFooter(): void {
?>
        </main>
        
        <footer class="border-t border-slate-800 py-3 px-6 text-xs text-slate-500 flex items-center justify-between">
            <div>&copy; <?= date('Y') ?> Aura Gaming Platform Base Infrastructure · Super Administrator Console</div>
            <div class="font-mono">Engine: Sequential Round Pipeline</div>
        </footer>
    </div>

    <script>
        var toggleBtn = document.getElementById('admin-mobile-toggle');
        var closeBtn = document.getElementById('admin-mobile-close');
        var drawer = document.getElementById('admin-mobile-drawer');

        toggleBtn?.addEventListener('click', function() {
            drawer.classList.remove('hidden');
        });
        closeBtn?.addEventListener('click', function() {
            drawer.classList.add('hidden');
        });
        drawer?.addEventListener('click', function(e) {
            if (e.target === drawer) drawer.classList.add('hidden');
        });
    </script>
</body>
</html>
<?php
}
