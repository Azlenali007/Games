<?php
/**
 * User Support Tickets & Replies
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_user.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::getInstance()->getConnection();

$ticketId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Handle creating new ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_ticket') {
    Security::requireCsrf();
    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $priority = trim($_POST['priority'] ?? 'normal');
    $message = trim($_POST['message'] ?? '');

    if (empty($subject) || empty($message)) {
        $error = 'Please provide both a ticket subject and message details.';
    } else {
        $ticketNum = 'TCK-' . strtoupper(substr(uniqid(), -6));
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO tickets (ticket_number, user_id, category, subject, priority, status) VALUES (?, ?, ?, ?, ?, 'open')");
            $stmt->execute([$ticketNum, $userId, $category, $subject, $priority]);
            $newId = (int)$db->lastInsertId();

            $rStmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message) VALUES (?, ?, 0, ?)");
            $rStmt->execute([$newId, $userId, $message]);

            $db->commit();
            Security::setFlash('success', "Ticket {$ticketNum} created successfully!");
            header("Location: /user/tickets.php?id=" . $newId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to create ticket: ' . $e->getMessage();
        }
    }
}

// Handle reply to existing ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    Security::requireCsrf();
    $message = trim($_POST['message'] ?? '');

    if ($ticketId <= 0 || empty($message)) {
        $error = 'Reply message cannot be empty.';
    } else {
        // Verify ticket ownership
        $tStmt = $db->prepare("SELECT id, status FROM tickets WHERE id = ? AND user_id = ?");
        $tStmt->execute([$ticketId, $userId]);
        $ticket = $tStmt->fetch();

        if (!$ticket) {
            $error = 'Ticket not found or access denied.';
        } elseif ($ticket['status'] === 'closed') {
            $error = 'This ticket has been closed and cannot accept new replies.';
        } else {
            $db->beginTransaction();
            try {
                $rStmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message) VALUES (?, ?, 0, ?)");
                $rStmt->execute([$ticketId, $userId, $message]);

                // Update ticket status to open
                $db->prepare("UPDATE tickets SET status = 'open', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);

                $db->commit();
                Security::setFlash('success', 'Your reply was dispatched to platform operators.');
                header("Location: /user/tickets.php?id=" . $ticketId);
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to post reply: ' . $e->getMessage();
            }
        }
    }
}

// If viewing a specific ticket
$activeTicket = null;
$replies = [];
if ($ticketId > 0) {
    $tStmt = $db->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
    $tStmt->execute([$ticketId, $userId]);
    $activeTicket = $tStmt->fetch();

    if ($activeTicket) {
        $rStmt = $db->prepare("
            SELECT r.*, u.username, u.role 
            FROM ticket_replies r
            JOIN users u ON r.user_id = u.id
            WHERE r.ticket_id = ? 
            ORDER BY r.id ASC
        ");
        $rStmt->execute([$ticketId]);
        $replies = $rStmt->fetchAll();
    }
}

// Fetch all user tickets
$myTickets = $db->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY id DESC");
$myTickets->execute([$userId]);
$tickets = $myTickets->fetchAll();

renderUserHeader('Support Tickets', 'tickets');
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">HELP & RESOLUTION DESK</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Support Tickets</h1>
        </div>
        <a href="/user/tickets.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
            + Open New Ticket
        </a>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-red-950/40 border border-red-800 text-red-300 text-xs">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Ticket List Sidebar -->
        <div class="lg:col-span-4 space-y-3">
            <h3 class="text-xs font-bold font-mono uppercase text-slate-400">My Ticket History</h3>
            <?php if (empty($tickets)): ?>
                <div class="p-4 rounded-xl bg-[#0a0d14] border border-slate-800 text-center text-xs text-slate-500">
                    No support tickets submitted yet.
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                    <a href="/user/tickets.php?id=<?= $t['id'] ?>" class="block p-4 rounded-xl border transition <?= ($activeTicket && $activeTicket['id'] == $t['id']) ? 'bg-blue-950/30 border-blue-600 text-white' : 'bg-[#0a0d14] border-slate-800 hover:border-slate-700 text-slate-300' ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-mono text-xs font-bold"><?= e($t['ticket_number']) ?></span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase font-bold <?= 
                                $t['status'] === 'answered' ? 'bg-blue-950 text-blue-400 border border-blue-800' :
                                ($t['status'] === 'open' ? 'bg-amber-950 text-amber-400 border border-amber-800' : 'bg-slate-800 text-slate-400')
                            ?>"><?= e($t['status']) ?></span>
                        </div>
                        <div class="text-xs font-semibold truncate"><?= e($t['subject']) ?></div>
                        <div class="text-[11px] text-slate-500 mt-2 font-mono flex justify-between">
                            <span><?= e($t['category']) ?></span>
                            <span><?= substr($t['created_at'], 0, 10) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Ticket Conversation or New Ticket Form -->
        <div class="lg:col-span-8">
            <?php if ($activeTicket): ?>
                <!-- Conversation Thread -->
                <div class="bg-[#0a0d14] border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div>
                            <span class="text-xs font-mono text-blue-400"><?= e($activeTicket['ticket_number']) ?> · <?= e($activeTicket['category']) ?></span>
                            <h2 class="text-xl font-bold text-white mt-1"><?= e($activeTicket['subject']) ?></h2>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-xs font-mono font-bold uppercase <?= 
                            $activeTicket['status'] === 'answered' ? 'bg-blue-950 text-blue-400 border border-blue-800' :
                            ($activeTicket['status'] === 'open' ? 'bg-amber-950 text-amber-400 border border-amber-800' : 'bg-slate-800 text-slate-400')
                        ?>"><?= e($activeTicket['status']) ?></span>
                    </div>

                    <!-- Messages -->
                    <div class="space-y-4">
                        <?php foreach ($replies as $r): ?>
                            <div class="p-4 rounded-xl border <?= $r['is_admin'] ? 'bg-blue-950/20 border-blue-900/60 ml-4' : 'bg-[#07090e] border-slate-800/80 mr-4' ?>">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold <?= $r['is_admin'] ? 'text-blue-400 font-mono' : 'text-slate-200' ?>">
                                        <?= $r['is_admin'] ? 'Aura Platform Support Team' : e($user['username']) ?>
                                    </span>
                                    <span class="text-[11px] font-mono text-slate-500"><?= e($r['created_at']) ?></span>
                                </div>
                                <div class="text-xs text-slate-300 whitespace-pre-line leading-relaxed">
                                    <?= e($r['message']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Reply Box -->
                    <?php if ($activeTicket['status'] !== 'closed'): ?>
                        <form method="POST" action="/user/tickets.php?id=<?= $activeTicket['id'] ?>" class="pt-4 border-t border-slate-800 space-y-3">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="reply">
                            <label class="block text-xs font-medium text-slate-400">Post Reply to Operators</label>
                            <textarea name="message" rows="3" required placeholder="Type your response here..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"></textarea>
                            <div class="flex justify-end">
                                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                                    Send Reply &rarr;
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- New Ticket Form -->
                <div class="bg-[#0a0d14] border border-slate-800 rounded-2xl p-6 shadow-xl">
                    <h2 class="text-lg font-bold text-white mb-1">Create Support Ticket</h2>
                    <p class="text-xs text-slate-400 mb-6">Describe your issue or inquiry in detail. Our operational desk monitors tickets 24/7.</p>

                    <form method="POST" action="/user/tickets.php" class="space-y-4">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="create_ticket">

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Category</label>
                                <select name="category" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                                    <option value="Deposit">Deposit Verification</option>
                                    <option value="Withdrawal">Withdrawal Processing</option>
                                    <option value="Rounds">Round & Settlement Audit</option>
                                    <option value="Account">Account Security</option>
                                    <option value="General">General Inquiry</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Priority</label>
                                <select name="priority" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Subject</label>
                            <input type="text" name="subject" placeholder="Summary of the issue" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Detailed Description</label>
                            <textarea name="message" rows="5" required placeholder="Please provide specific round IDs, transaction hashes, or error details..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"></textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition">
                            Submit Official Ticket &rarr;
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php renderUserFooter(); ?>
