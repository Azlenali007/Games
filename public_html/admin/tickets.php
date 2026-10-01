<?php
/**
 * Admin Support Ticket Management
 * View, Reply, Change Status (open, in_progress, answered, closed).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$ticketId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Handle Admin Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_reply') {
    Security::requireCsrf();
    $targetId = (int)$_POST['ticket_id'];
    $replyMsg = trim($_POST['message'] ?? '');
    $newStatus = trim($_POST['status'] ?? 'answered');

    if ($targetId > 0 && !empty($replyMsg)) {
        $db->beginTransaction();
        try {
            $rStmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message) VALUES (?, ?, 1, ?)");
            $rStmt->execute([$targetId, Auth::id(), $replyMsg]);

            $db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $targetId]);

            // Notify user
            $t = $db->query("SELECT user_id, ticket_number FROM tickets WHERE id = $targetId")->fetch();
            if ($t) {
                $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Operator Reply on Ticket', ?, 'system')")
                   ->execute([$t['user_id'], "Aura support responded to ticket {$t['ticket_number']}."]);
            }

            $db->commit();
            Security::setFlash('success', 'Admin reply dispatched and ticket status updated.');
            header("Location: /admin/tickets.php?id=" . $targetId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to post reply: ' . $e->getMessage();
        }
    }
}

// Fetch single ticket details
$activeTicket = null;
$replies = [];
if ($ticketId > 0) {
    $tStmt = $db->prepare("
        SELECT t.*, u.username, u.email 
        FROM tickets t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ? LIMIT 1
    ");
    $tStmt->execute([$ticketId]);
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

// Fetch all tickets
$tickets = $db->query("
    SELECT t.*, u.username 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.id DESC LIMIT 50
")->fetchAll();

renderAdminHeader('Support Ticket Operations', 'tickets');
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">CUSTOMER SUPPORT OPERATIONS</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Support Ticket Management</h1>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-red-950/40 border border-red-800 text-red-300 text-xs"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Tickets Sidebar -->
        <div class="lg:col-span-5 space-y-3">
            <h3 class="text-xs font-bold font-mono uppercase text-slate-400">All Player Tickets</h3>
            <div class="space-y-2">
                <?php foreach ($tickets as $t): ?>
                    <a href="/admin/tickets.php?id=<?= $t['id'] ?>" class="block p-4 rounded-xl border transition <?= ($activeTicket && $activeTicket['id'] == $t['id']) ? 'bg-blue-950/30 border-blue-600 text-white' : 'bg-[#0a0d14] border-slate-800 hover:border-slate-700 text-slate-300' ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-mono text-xs font-bold text-blue-400"><?= e($t['ticket_number']) ?></span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase font-bold <?= 
                                $t['status'] === 'open' ? 'bg-amber-950 text-amber-400 border border-amber-800' :
                                ($t['status'] === 'answered' ? 'bg-blue-950 text-blue-400 border border-blue-800' : 'bg-slate-800 text-slate-400')
                            ?>"><?= e($t['status']) ?></span>
                        </div>
                        <div class="text-xs font-semibold truncate"><?= e($t['subject']) ?></div>
                        <div class="text-[11px] text-slate-500 mt-2 font-mono flex justify-between">
                            <span>User: <strong class="text-slate-300"><?= e($t['username']) ?></strong></span>
                            <span><?= substr($t['created_at'], 0, 10) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Ticket Detail / Reply View -->
        <div class="lg:col-span-7">
            <?php if ($activeTicket): ?>
                <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div>
                            <span class="text-xs font-mono text-blue-400"><?= e($activeTicket['ticket_number']) ?> · <?= e($activeTicket['category']) ?></span>
                            <h2 class="text-xl font-bold text-white mt-1"><?= e($activeTicket['subject']) ?></h2>
                            <span class="text-xs text-slate-500 font-mono">By <?= e($activeTicket['username']) ?> (<?= e($activeTicket['email']) ?>)</span>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-xs font-mono font-bold uppercase <?= 
                            $activeTicket['status'] === 'open' ? 'bg-amber-950 text-amber-400 border border-amber-800' :
                            ($activeTicket['status'] === 'answered' ? 'bg-blue-950 text-blue-400 border border-blue-800' : 'bg-slate-800 text-slate-400')
                        ?>"><?= e($activeTicket['status']) ?></span>
                    </div>

                    <!-- Conversation Thread -->
                    <div class="space-y-4">
                        <?php foreach ($replies as $r): ?>
                            <div class="p-4 rounded-xl border <?= $r['is_admin'] ? 'bg-blue-950/20 border-blue-900/60 ml-4' : 'bg-[#07090e] border-slate-800 mr-4' ?>">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold font-mono <?= $r['is_admin'] ? 'text-blue-400' : 'text-slate-300' ?>">
                                        <?= $r['is_admin'] ? 'Operator: ' . e($r['username']) : 'Player: ' . e($r['username']) ?>
                                    </span>
                                    <span class="text-[11px] font-mono text-slate-500"><?= e($r['created_at']) ?></span>
                                </div>
                                <div class="text-xs text-slate-300 whitespace-pre-line leading-relaxed"><?= e($r['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Admin Reply Form -->
                    <form method="POST" action="/admin/tickets.php?id=<?= $activeTicket['id'] ?>" class="pt-4 border-t border-slate-800 space-y-4">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="admin_reply">
                        <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">

                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Administrative Response</label>
                            <textarea name="message" rows="4" required placeholder="Type official response..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"></textarea>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-slate-400 font-mono">Set Status:</label>
                                <select name="status" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                                    <option value="answered">Answered</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="closed">Closed / Resolved</option>
                                </select>
                            </div>

                            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                                Dispatch Response &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="p-8 rounded-2xl bg-[#0a0d14] border border-slate-800 text-center text-slate-500 text-xs font-mono">
                    Select a ticket from the left panel to review message history and reply.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
