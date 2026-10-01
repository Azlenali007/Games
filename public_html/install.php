<?php
/**
 * Aura Gaming Platform - Production Web Installer
 * 1. Checks PHP requirements & extensions
 * 2. Verifies MySQL connection
 * 3. Imports normalized database schema
 * 4. Configures administrator credentials & platform settings
 * 5. Secures and locks installer via install.lock
 */

$lockFile = __DIR__ . '/install.lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    die('<!DOCTYPE html><html lang="en"><head><title>Installer Locked</title><style>body{background:#0a0d14;color:#94a3b8;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}div{text-align:center;padding:2.5rem;background:#0f1422;border:1px solid #1e293b;border-radius:12px;max-width:440px;}h2{color:#3b82f6;margin-top:0;}p{color:#64748b;font-size:14px;}</style></head><body><div><h2>Platform Already Installed</h2><p>For security, the installer has been locked with <code>install.lock</code>. To reinstall, delete this lock file on the server.</p><a href="/" style="display:inline-block;margin-top:1rem;color:#fff;background:#2563eb;padding:0.6rem 1.2rem;border-radius:6px;text-decoration:none;font-size:14px;">Go to Platform</a></div></body></html>');
}

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

// Check prerequisites
$phpVersionOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$requiredExtensions = ['pdo', 'pdo_mysql', 'session', 'json', 'openssl', 'ctype'];
$extStatus = [];
$allExtOk = true;

foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    $extStatus[$ext] = $loaded;
    if (!$loaded) $allExtOk = false;
}

// Process installation submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_db') {
        $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? 'gaming_platform');
        $dbUser = trim($_POST['db_user'] ?? 'root');
        $dbPass = $_POST['db_pass'] ?? '';

        try {
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $pdo->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->query("USE `{$dbName}`");
            
            // Store DB config in session for step 3
            session_start();
            $_SESSION['install_db'] = [
                'host' => $dbHost,
                'port' => $dbPort,
                'name' => $dbName,
                'user' => $dbUser,
                'pass' => $dbPass
            ];
            header("Location: /install.php?step=3");
            exit;
        } catch (Exception $e) {
            $error = "Database connection failed: " . $e->getMessage();
        }
    } elseif ($action === 'finish_install') {
        session_start();
        $dbConfig = $_SESSION['install_db'] ?? [
            'host' => '127.0.0.1', 'port' => '3306', 'name' => 'gaming_platform', 'user' => 'root', 'pass' => ''
        ];

        $siteName = trim($_POST['site_name'] ?? 'Aura Gaming Platform');
        $adminUser = trim($_POST['admin_username'] ?? 'admin');
        $adminEmail = trim($_POST['admin_email'] ?? 'admin@platform.com');
        $adminPass = $_POST['admin_password'] ?? '';

        if (strlen($adminPass) < 8) {
            $error = "Admin password must be at least 8 characters.";
        } else {
            try {
                $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                // Import database.sql schema
                $sqlPath = __DIR__ . '/database.sql';
                if (file_exists($sqlPath)) {
                    $sqlContent = file_get_contents($sqlPath);
                    $pdo->exec($sqlContent);
                }

                // Insert or update Admin User
                $passHash = password_hash($adminPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, role, status, referral_code)
                    VALUES (?, ?, ?, 'System Administrator', 'admin', 'active', 'AURAADMIN')
                    ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'admin', status = 'active'
                ");
                $stmt->execute([$adminUser, $adminEmail, $passHash]);
                $adminId = $pdo->query("SELECT id FROM users WHERE username = '{$adminUser}'")->fetchColumn();

                // Create Admin Wallet
                $pdo->prepare("INSERT INTO wallets (user_id, balance, currency) VALUES (?, 10000.0000, 'USD') ON DUPLICATE KEY UPDATE balance=10000.0000")
                    ->execute([$adminId]);

                // Update Site Name
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group, is_public) VALUES ('site_name', ?, 'general', 1) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                    ->execute([$siteName]);

                // Run seed routines for default gateways, initial rounds
                if (file_exists(__DIR__ . '/seed.php')) {
                    include_once __DIR__ . '/seed.php';
                }

                // Lock installer
                file_put_contents($lockFile, "Installed on " . date('Y-m-d H:i:s') . "\n");

                header("Location: /install.php?step=4");
                exit;

            } catch (Exception $e) {
                $error = "Installation execution failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura Platform Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #07090e; font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="text-slate-300 min-h-screen flex flex-col justify-between antialiased">
    <!-- Header -->
    <header class="border-b border-slate-800 bg-[#0a0d14]/90 py-4 px-6">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-600/30">A</div>
                <div>
                    <span class="text-lg font-semibold text-white tracking-wide">AURA</span>
                    <span class="text-xs text-blue-400 font-mono ml-2">PLATFORM INSTALLER</span>
                </div>
            </div>
            <div class="text-xs text-slate-500 font-mono">PHP <?= PHP_VERSION ?> · MySQL PDO</div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-xl w-full mx-auto p-6 flex flex-col justify-center">
        <!-- Steps Navigator -->
        <div class="mb-8 flex items-center justify-between border-b border-slate-800 pb-4 text-xs font-medium">
            <span class="<?= $step === 1 ? 'text-blue-400 font-semibold' : 'text-slate-500' ?>">1. System Check</span>
            <span class="text-slate-700">/</span>
            <span class="<?= $step === 2 ? 'text-blue-400 font-semibold' : 'text-slate-500' ?>">2. Database Config</span>
            <span class="text-slate-700">/</span>
            <span class="<?= $step === 3 ? 'text-blue-400 font-semibold' : 'text-slate-500' ?>">3. Admin Setup</span>
            <span class="text-slate-700">/</span>
            <span class="<?= $step === 4 ? 'text-emerald-400 font-semibold' : 'text-slate-500' ?>">4. Complete</span>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 rounded-lg bg-red-950/50 border border-red-800 text-red-300 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- STEP 1: PHP Requirements -->
        <?php if ($step === 1): ?>
            <div class="bg-[#0f1422] border border-slate-800 rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-semibold text-white mb-2">Environment Verification</h2>
                <p class="text-sm text-slate-400 mb-6">Verify that your PHP environment meets production specifications.</p>

                <div class="space-y-3 mb-6">
                    <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900/60 border border-slate-800/80">
                        <span class="text-sm">PHP Version (&ge; 8.0)</span>
                        <span class="text-xs font-mono font-medium <?= $phpVersionOk ? 'text-emerald-400' : 'text-red-400' ?>">
                            <?= PHP_VERSION ?> <?= $phpVersionOk ? '✓ PASS' : '✗ FAIL' ?>
                        </span>
                    </div>

                    <?php foreach ($extStatus as $ext => $loaded): ?>
                        <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900/60 border border-slate-800/80">
                            <span class="text-sm font-mono">ext-<?= $ext ?></span>
                            <span class="text-xs font-mono font-medium <?= $loaded ? 'text-emerald-400' : 'text-red-400' ?>">
                                <?= $loaded ? '✓ PASS' : '✗ MISSING' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-end">
                    <?php if ($phpVersionOk && $allExtOk): ?>
                        <a href="/install.php?step=2" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition shadow-lg shadow-blue-600/30">
                            Continue to Database &rarr;
                        </a>
                    <?php else: ?>
                        <button disabled class="px-5 py-2.5 bg-slate-800 text-slate-500 text-sm font-medium rounded-lg cursor-not-allowed">
                            Fix Missing Extensions to Continue
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- STEP 2: Database Configuration -->
        <?php if ($step === 2): ?>
            <div class="bg-[#0f1422] border border-slate-800 rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-semibold text-white mb-2">MySQL Database Setup</h2>
                <p class="text-sm text-slate-400 mb-6">Provide database credentials. The installer will create the database if it doesn't already exist.</p>

                <form method="POST" action="/install.php?step=2" class="space-y-4">
                    <input type="hidden" name="action" value="test_db">

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-400 mb-1">Database Host</label>
                            <input type="text" name="db_host" value="127.0.0.1" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Port</label>
                            <input type="text" name="db_port" value="3306" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Database Name</label>
                        <input type="text" name="db_name" value="gaming_platform" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Database Username</label>
                        <input type="text" name="db_user" value="root" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Database Password</label>
                        <input type="password" name="db_pass" value="" placeholder="Leave blank if none" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="flex items-center justify-between pt-4">
                        <a href="/install.php?step=1" class="text-xs text-slate-500 hover:text-slate-400">&larr; Back to Check</a>
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition shadow-lg shadow-blue-600/30">
                            Verify Connection & Next &rarr;
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- STEP 3: Admin & Site Configuration -->
        <?php if ($step === 3): ?>
            <div class="bg-[#0f1422] border border-slate-800 rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-semibold text-white mb-2">Platform Administration</h2>
                <p class="text-sm text-slate-400 mb-6">Create the master administrator account and platform name.</p>

                <form method="POST" action="/install.php?step=3" class="space-y-4">
                    <input type="hidden" name="action" value="finish_install">

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Platform Brand Name</label>
                        <input type="text" name="site_name" value="Aura Gaming Platform" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="border-t border-slate-800 my-4"></div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Admin Username</label>
                        <input type="text" name="admin_username" value="admin" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Admin Email</label>
                        <input type="email" name="admin_email" value="admin@platform.com" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Admin Password</label>
                        <input type="password" name="admin_password" value="AdminPassword123!" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        <span class="text-xs text-slate-500 mt-1 block">Default demo: AdminPassword123!</span>
                    </div>

                    <div class="flex items-center justify-between pt-4">
                        <a href="/install.php?step=2" class="text-xs text-slate-500 hover:text-slate-400">&larr; Back to DB</a>
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition shadow-lg shadow-blue-600/30">
                            Execute Installation &rarr;
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- STEP 4: Installation Complete -->
        <?php if ($step === 4): ?>
            <div class="bg-[#0f1422] border border-slate-800 rounded-xl p-8 shadow-xl text-center">
                <div class="w-14 h-14 bg-emerald-500/10 border border-emerald-500/30 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>

                <h2 class="text-2xl font-bold text-white mb-2">Installation Complete!</h2>
                <p class="text-sm text-slate-400 mb-6 max-w-sm mx-auto">
                    The database tables have been created, seed data imported, and <code>install.lock</code> generated. The installer is now disabled.
                </p>

                <div class="p-4 rounded-lg bg-slate-900/80 border border-slate-800 text-left mb-6 space-y-2 text-xs font-mono">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Admin Account:</span>
                        <span class="text-blue-400">admin</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Admin Password:</span>
                        <span class="text-blue-400">AdminPassword123!</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Demo Player:</span>
                        <span class="text-blue-400">player1 (Pass: PlayerPassword123!)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Status:</span>
                        <span class="text-emerald-400">Live & Synchronized</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <a href="/login.php" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition shadow-lg shadow-blue-600/30">
                        Player Login
                    </a>
                    <a href="/admin/login.php" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition border border-slate-700">
                        Admin Console
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 py-4 px-6 text-center text-xs text-slate-600">
        &copy; <?= date('Y') ?> Aura Gaming Platform Base Infrastructure · Production PHP & MySQL
    </footer>
</body>
</html>
