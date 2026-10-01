<?php
/**
 * Database Seeder Script
 * Inserts default system settings, payment gateways, admin and test users, wallets,
 * initial sequential rounds (319, 320 completed, 321 active, 322 upcoming), and demo notifications.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "Seeding settings...\n";
    $settings = [
        ['site_name', 'Aura Gaming Platform', 'general', 1],
        ['site_title', 'Aura - Premier Next-Gen Gaming Infrastructure', 'general', 1],
        ['site_description', 'High-performance gaming platform base infrastructure with real-time round engine and financial ledger.', 'general', 1],
        ['contact_email', 'support@auragaming.io', 'general', 1],
        ['contact_phone', '+1 (800) 555-AURA', 'general', 1],
        ['contact_address', 'Financial District, Suite 1800, Tech Gateway Tower', 'general', 1],
        ['maintenance_mode', '0', 'general', 1],
        ['maintenance_message', 'We are currently performing scheduled platform maintenance. Please check back shortly.', 'general', 1],
        ['currency', 'USD', 'finance', 1],
        ['currency_symbol', '$', 'finance', 1],
        ['min_deposit', '10.00', 'finance', 1],
        ['max_deposit', '10000.00', 'finance', 1],
        ['min_withdrawal', '20.00', 'finance', 1],
        ['max_withdrawal', '5000.00', 'finance', 1],
        ['withdrawal_fee_pct', '1.50', 'finance', 1],
        ['default_referral_pct', '5.00', 'referral', 1],
        ['round_duration_seconds', '60', 'rounds', 1],
        ['betting_close_lead_seconds', '10', 'rounds', 1],
        ['round_result_mode', 'auto', 'rounds', 1], // 'auto' or 'manual'
        ['cron_secret_key', 'aura_cron_sec_88921a9', 'automation', 0],
        ['installed_at', date('Y-m-d H:i:s'), 'system', 0],
    ];

    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_group, is_public) 
        VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings as $s) {
        $stmt->execute($s);
    }

    echo "Seeding payment gateways...\n";
    $gateways = [
        [
            'code' => 'bank_wire',
            'name' => 'Direct Bank Wire / Instant ACH',
            'is_enabled' => 1,
            'min_deposit' => 50.00,
            'max_deposit' => 10000.00,
            'min_withdrawal' => 50.00,
            'max_withdrawal' => 5000.00,
            'deposit_fee_pct' => 0.00,
            'withdrawal_fee_pct' => 1.00,
            'instructions' => 'Transfer directly to Aura Global Escrow Account. Include your transaction reference in wire memo.'
        ],
        [
            'code' => 'crypto_usdt',
            'name' => 'USDT (TRC-20 / ERC-20 Crypto)',
            'is_enabled' => 1,
            'min_deposit' => 10.00,
            'max_deposit' => 25000.00,
            'min_withdrawal' => 20.00,
            'max_withdrawal' => 10000.00,
            'deposit_fee_pct' => 0.00,
            'withdrawal_fee_pct' => 0.50,
            'instructions' => 'Deposit USDT directly to our verified smart contract vault. Instant automated confirmation.'
        ],
        [
            'code' => 'card_gateway',
            'name' => 'Visa / Mastercard Secure Gateway',
            'is_enabled' => 1,
            'min_deposit' => 15.00,
            'max_deposit' => 3000.00,
            'min_withdrawal' => 25.00,
            'max_withdrawal' => 2000.00,
            'deposit_fee_pct' => 2.00,
            'withdrawal_fee_pct' => 2.00,
            'instructions' => '3D Secure 2.0 card processing with zero liability fraud protection.'
        ]
    ];

    $gwStmt = $db->prepare("INSERT INTO payment_gateways (code, name, is_enabled, min_deposit, max_deposit, min_withdrawal, max_withdrawal, deposit_fee_pct, withdrawal_fee_pct, instructions)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), is_enabled=VALUES(is_enabled)");
    foreach ($gateways as $g) {
        $gwStmt->execute([
            $g['code'], $g['name'], $g['is_enabled'], $g['min_deposit'], $g['max_deposit'],
            $g['min_withdrawal'], $g['max_withdrawal'], $g['deposit_fee_pct'], $g['withdrawal_fee_pct'], $g['instructions']
        ]);
    }

    echo "Seeding users...\n";
    // 1. Super Admin
    $adminPassword = password_hash('AdminPassword123!', PASSWORD_BCRYPT);
    $userStmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, role, status, referral_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE role='admin'");
    $userStmt->execute(['admin', 'admin@platform.com', $adminPassword, 'Platform Administrator', '+1-555-0100', 'admin', 'active', 'AURAADMIN']);
    $adminId = $db->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();

    // 2. Demo Player 1
    $playerPassword = password_hash('PlayerPassword123!', PASSWORD_BCRYPT);
    $userStmt->execute(['player1', 'player@platform.com', $playerPassword, 'Alex Vance', '+1-555-0144', 'user', 'active', 'AURAPLAYER1']);
    $player1Id = $db->query("SELECT id FROM users WHERE username='player1'")->fetchColumn();

    // 3. Demo VIP Player
    $vipPassword = password_hash('VipPassword123!', PASSWORD_BCRYPT);
    $userStmt->execute(['vip_gamer', 'vip@platform.com', $vipPassword, 'Elena Rostova', '+1-555-0199', 'user', 'active', 'AURAVIP']);
    $vipId = $db->query("SELECT id FROM users WHERE username='vip_gamer'")->fetchColumn();

    echo "Seeding wallets & ledger...\n";
    // Setup Admin Wallet
    $wStmt = $db->prepare("INSERT INTO wallets (user_id, balance, locked_balance, currency) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE balance=VALUES(balance)");
    $wStmt->execute([$adminId, 10000.0000, 0.0000, 'USD']);

    // Setup Player1 Wallet: $500.0000 balance + Ledger entry
    $wStmt->execute([$player1Id, 500.0000, 0.0000, 'USD']);
    
    // Check if ledger already has records
    $ledgerCount = $db->query("SELECT COUNT(*) FROM wallet_ledger WHERE user_id = $player1Id")->fetchColumn();
    if ($ledgerCount == 0) {
        $lStmt = $db->prepare("INSERT INTO wallet_ledger (user_id, amount, balance_before, balance_after, type, reference_id, reference_type, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $lStmt->execute([$player1Id, 500.0000, 0.0000, 500.0000, 'deposit', 'TX-DEP-INIT-001', 'transaction', 'Initial verified bank wire deposit']);
        
        // Transaction record
        $tStmt = $db->prepare("INSERT INTO transactions (transaction_ref, user_id, type, amount, fee, net_amount, payment_method, status, admin_notes, approved_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $tStmt->execute(['TX-DEP-INIT-001', $player1Id, 'deposit', 500.0000, 0.0000, 500.0000, 'Direct Bank Wire / Instant ACH', 'approved', 'Verified initial deposit ledger credit', $adminId]);
    }

    // Setup VIP Wallet: $1,250.0000 balance
    $wStmt->execute([$vipId, 1250.0000, 0.0000, 'USD']);
    $vipLedgerCount = $db->query("SELECT COUNT(*) FROM wallet_ledger WHERE user_id = $vipId")->fetchColumn();
    if ($vipLedgerCount == 0) {
        $lStmt = $db->prepare("INSERT INTO wallet_ledger (user_id, amount, balance_before, balance_after, type, reference_id, reference_type, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $lStmt->execute([$vipId, 1250.0000, 0.0000, 1250.0000, 'deposit', 'TX-DEP-INIT-002', 'transaction', 'Crypto USDT TRC-20 confirmed deposit']);
        
        $tStmt = $db->prepare("INSERT INTO transactions (transaction_ref, user_id, type, amount, fee, net_amount, payment_method, status, admin_notes, approved_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $tStmt->execute(['TX-DEP-INIT-002', $vipId, 'deposit', 1250.0000, 0.0000, 1250.0000, 'USDT (TRC-20 / ERC-20 Crypto)', 'approved', 'Blockchain confirmation 12 blocks', $adminId]);
    }

    echo "Seeding promotions...\n";
    $pStmt = $db->prepare("INSERT INTO promotions (code, title, description, bonus_type, bonus_value, min_deposit, max_bonus, max_uses, used_count, starts_at, expires_at, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title)");
    $pStmt->execute([
        'WELCOME100', '100% First Deposit Match Bonus', 'Double your starting platform balance up to $100 on your first qualifying deposit.',
        'percentage', 100.00, 20.00, 100.00, 5000, 14,
        date('Y-m-d 00:00:00', strtotime('-30 days')), date('Y-m-d 23:59:59', strtotime('+90 days')), 1
    ]);
    $pStmt->execute([
        'AURA25', 'VIP Reload $25 Instant Credit', 'Get an instant $25 credit on any deposit exceeding $100.',
        'fixed', 25.00, 100.00, 25.00, 2000, 8,
        date('Y-m-d 00:00:00', strtotime('-10 days')), date('Y-m-d 23:59:59', strtotime('+60 days')), 1
    ]);

    echo "Seeding sequential rounds...\n";
    // Check if rounds exist
    $roundCount = $db->query("SELECT COUNT(*) FROM rounds")->fetchColumn();
    if ($roundCount == 0) {
        $now = time();
        
        // Previous Completed Round #319
        $r319_start = date('Y-m-d H:i:s', $now - 180);
        $r319_end = date('Y-m-d H:i:s', $now - 120);
        $r319_close = date('Y-m-d H:i:s', $now - 130);
        $db->prepare("INSERT INTO rounds (id, round_number, start_time, end_time, betting_closed_at, status, betting_status, result_status, declared_result, settlement_status, result_mode)
            VALUES (319, 319, ?, ?, ?, 'completed', 'closed', 'settled', 'DELTA_9', 'settled', 'auto')")
            ->execute([$r319_start, $r319_end, $r319_close]);

        // Previous Completed Round #320
        $r320_start = date('Y-m-d H:i:s', $now - 120);
        $r320_end = date('Y-m-d H:i:s', $now - 60);
        $r320_close = date('Y-m-d H:i:s', $now - 70);
        $db->prepare("INSERT INTO rounds (id, round_number, start_time, end_time, betting_closed_at, status, betting_status, result_status, declared_result, settlement_status, result_mode)
            VALUES (320, 320, ?, ?, ?, 'completed', 'closed', 'settled', 'ALPHA_1', 'settled', 'auto')")
            ->execute([$r320_start, $r320_end, $r320_close]);

        // Active Sequential Round #321 (starts now, ends in 60s, closes 10s before end)
        $r321_start = date('Y-m-d H:i:s', $now);
        $r321_end = date('Y-m-d H:i:s', $now + 60);
        $r321_close = date('Y-m-d H:i:s', $now + 50);
        $db->prepare("INSERT INTO rounds (id, round_number, start_time, end_time, betting_closed_at, status, betting_status, result_status, declared_result, settlement_status, result_mode)
            VALUES (321, 321, ?, ?, ?, 'active', 'open', 'pending', NULL, 'unsettled', 'auto')")
            ->execute([$r321_start, $r321_end, $r321_close]);

        // Upcoming Sequential Round #322
        $r322_start = date('Y-m-d H:i:s', $now + 60);
        $r322_end = date('Y-m-d H:i:s', $now + 120);
        $r322_close = date('Y-m-d H:i:s', $now + 110);
        $db->prepare("INSERT INTO rounds (id, round_number, start_time, end_time, betting_closed_at, status, betting_status, result_status, declared_result, settlement_status, result_mode)
            VALUES (322, 322, ?, ?, ?, 'upcoming', 'open', 'pending', NULL, 'unsettled', 'auto')")
            ->execute([$r322_start, $r322_end, $r322_close]);

        // Upcoming Sequential Round #323
        $r323_start = date('Y-m-d H:i:s', $now + 120);
        $r323_end = date('Y-m-d H:i:s', $now + 180);
        $r323_close = date('Y-m-d H:i:s', $now + 170);
        $db->prepare("INSERT INTO rounds (id, round_number, start_time, end_time, betting_closed_at, status, betting_status, result_status, declared_result, settlement_status, result_mode)
            VALUES (323, 323, ?, ?, ?, 'upcoming', 'open', 'pending', NULL, 'unsettled', 'auto')")
            ->execute([$r323_start, $r323_end, $r323_close]);

        // Seed some sample bets on Round #321 for live monitoring
        $bStmt = $db->prepare("INSERT INTO bets (bet_ref, user_id, round_id, option_key, amount, potential_payout, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $bStmt->execute(['BET-321-001', $player1Id, 321, 'ALPHA_1', 25.0000, 50.0000, 'placed']);
        $bStmt->execute(['BET-321-002', $vipId, 321, 'BETA_2', 100.0000, 200.0000, 'placed']);
        $bStmt->execute(['BET-321-003', $player1Id, 321, 'DELTA_9', 50.0000, 150.0000, 'placed']);
    }

    echo "Seeding notifications & tickets...\n";
    // System notification
    $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read)
        VALUES (NULL, 'System Upgrade Notice', 'Common gaming round infrastructure and wallet ledger successfully calibrated.', 'system', 0)")
        ->execute();

    $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read)
        VALUES (?, 'Welcome to Aura Platform', 'Your account has been verified with standard tier privileges. Deposit via bank wire or crypto to begin.', 'account', 0)")
        ->execute([$player1Id]);

    // Sample Support Ticket
    $ticketNum = 'TCK-' . strtoupper(substr(uniqid(), -6));
    $db->prepare("INSERT INTO tickets (ticket_number, user_id, category, subject, priority, status)
        VALUES (?, ?, 'Deposit', 'Question regarding TRC-20 USDT confirmation speed', 'normal', 'answered')")
        ->execute([$ticketNum, $player1Id]);
    $tId = $db->lastInsertId();

    $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message)
        VALUES (?, ?, 0, 'Hello, how many network confirmations are required before USDT deposits credit to my wallet?')")
        ->execute([$tId, $player1Id]);

    $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message)
        VALUES (?, ?, 1, 'TRC-20 deposits require 12 block confirmations, typically completing in under 60 seconds.')")
        ->execute([$tId, $adminId]);

    echo "Database seeding completed successfully!\n";

} catch (Exception $e) {
    echo "Seeder Error: " . $e->getMessage() . "\n";
    exit(1);
}
