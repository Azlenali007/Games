<?php
/**
 * User Registration Page
 * Real account creation, wallet provisioning, referral code tracking.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/layout_user.php';

if (Auth::check()) {
    header("Location: /user/dashboard.php");
    exit;
}

$error = '';
$refCodeParam = trim($_GET['ref'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $referralCode = trim($_POST['referral_code'] ?? '');

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All primary fields are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = 'Username must be 3-30 characters with letters, numbers, and underscores only.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters in length.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = Database::getInstance()->getConnection();

        // Check if username or email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'A user account with that username or email already exists.';
        } else {
            // Find referrer if code supplied
            $referrerId = null;
            if (!empty($referralCode)) {
                $refStmt = $db->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
                $refStmt->execute([$referralCode]);
                $referrerId = $refStmt->fetchColumn() ?: null;
            }

            // Generate unique referral code
            $myRefCode = 'AURA' . strtoupper(substr(md5(uniqid()), 0, 6));

            $db->beginTransaction();
            try {
                $passHash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $db->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, role, status, referral_code, referred_by_user_id)
                    VALUES (?, ?, ?, ?, 'user', 'active', ?, ?)
                ");
                $ins->execute([$username, $email, $passHash, $username, $myRefCode, $referrerId]);
                $newUserId = (int)$db->lastInsertId();

                // Create initial wallet
                $db->prepare("INSERT INTO wallets (user_id, balance, locked_balance, currency) VALUES (?, 0.0000, 0.0000, 'USD')")
                   ->execute([$newUserId]);

                // Record referral relationship if applicable
                if ($referrerId) {
                    $db->prepare("INSERT INTO referrals (referrer_id, referee_id, commission_rate) VALUES (?, ?, 5.00)")
                       ->execute([$referrerId, $newUserId]);
                }

                // Send welcome notification
                $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Welcome to Aura Platform', 'Your account has been provisioned. Deposit to participate in active sequential rounds.', 'account')")
                   ->execute([$newUserId]);

                $db->commit();

                // Auto-login
                Auth::attempt($username, $password);
                Security::setFlash('success', 'Your player account was created successfully! Welcome to Aura.');
                header("Location: /user/dashboard.php");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Account creation failed: ' . $e->getMessage();
            }
        }
    }
}

renderUserHeader('Register Player Account', 'register');
?>
<main class="flex-1 flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full">
        <div class="bg-[#0a0d14] border border-slate-800 rounded-2xl p-8 shadow-2xl">
            <div class="text-center mb-6">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-600/30 mx-auto mb-3">
                    A
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Create Account</h1>
                <p class="text-xs text-slate-400 mt-1">Join the premier real-time gaming base infrastructure.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-5 p-3 rounded-lg bg-red-950/50 border border-red-800 text-red-300 text-xs">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/register.php" class="space-y-4">
                <?= Security::csrfField() ?>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Username</label>
                    <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus placeholder="player_one" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Email Address</label>
                    <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required placeholder="you@example.com" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500 transition">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Password</label>
                        <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Confirm Password</label>
                        <input type="password" name="password_confirm" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Referral Code (Optional)</label>
                    <input type="text" name="referral_code" value="<?= e($_POST['referral_code'] ?? $refCodeParam) ?>" placeholder="e.g. AURAPLAYER1" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500 transition">
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition">
                    Register Player Account &rarr;
                </button>
            </form>

            <div class="mt-5 text-center text-xs text-slate-400">
                Already registered? <a href="/login.php" class="text-blue-400 hover:text-blue-300 font-medium">Sign in here</a>
            </div>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
