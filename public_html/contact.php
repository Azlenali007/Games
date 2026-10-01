<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/layout_user.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'All fields are required.';
    } else {
        $db = Database::getInstance()->getConnection();
        // If logged in, create a real ticket
        $userId = Auth::id() ?: 1;
        $ticketNum = 'TCK-' . strtoupper(substr(uniqid(), -6));

        $stmt = $db->prepare("INSERT INTO tickets (ticket_number, user_id, category, subject, priority, status) VALUES (?, ?, ?, ?, 'normal', 'open')");
        $stmt->execute([$ticketNum, $userId, $category, $subject]);
        $ticketId = $db->lastInsertId();

        $replyStmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message) VALUES (?, ?, 0, ?)");
        $replyStmt->execute([$ticketId, $userId, "From: $name ($email)\n\n" . $message]);

        $success = "Your message has been dispatched to platform operations under Ticket Ref #{$ticketNum}.";
    }
}

renderUserHeader('Contact Platform Operations', 'contact');
?>
<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-12">
        <div class="md:col-span-5 space-y-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-950/60 border border-blue-800/60 text-xs text-blue-400 font-mono mb-4">
                    <span>COMMUNICATIONS HUB</span>
                </div>
                <h1 class="text-3xl font-extrabold text-white tracking-tight">Contact Support</h1>
                <p class="text-slate-400 text-xs mt-2 leading-relaxed">
                    Have questions regarding wallet settlement, API integrations, or gaming infrastructure? Our administrative team is available 24/7.
                </p>
            </div>

            <div class="space-y-4 pt-4 text-xs font-mono">
                <div class="p-4 rounded-xl bg-[#0a0d14] border border-slate-800">
                    <span class="text-slate-500 block">Official Escrow & Ops:</span>
                    <span class="text-white font-medium">support@auragaming.io</span>
                </div>
                <div class="p-4 rounded-xl bg-[#0a0d14] border border-slate-800">
                    <span class="text-slate-500 block">Response Time SLA:</span>
                    <span class="text-emerald-400 font-medium">&lt; 15 Minutes Verified</span>
                </div>
            </div>
        </div>

        <div class="md:col-span-7">
            <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800">
                <?php if ($success): ?>
                    <div class="mb-4 p-4 rounded-lg bg-emerald-950/40 border border-emerald-800 text-emerald-300 text-xs">
                        <?= e($success) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="mb-4 p-4 rounded-lg bg-red-950/40 border border-red-800 text-red-300 text-xs">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/contact.php" class="space-y-4">
                    <?= Security::csrfField() ?>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Your Full Name</label>
                            <input type="text" name="name" value="<?= e(Auth::user()['full_name'] ?? '') ?>" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Email Address</label>
                            <input type="email" name="email" value="<?= e(Auth::user()['email'] ?? '') ?>" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Inquiry Category</label>
                            <select name="category" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                                <option value="General">General Inquiry</option>
                                <option value="Wallet">Wallet & Deposits</option>
                                <option value="Rounds">Rounds & Settlements</option>
                                <option value="Security">Security & Verification</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Subject</label>
                            <input type="text" name="subject" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Message Details</label>
                        <textarea name="message" rows="4" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"></textarea>
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition">
                        Dispatch Support Message &rarr;
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
