-- Aura Gaming Platform Base Database Schema
-- Production MySQL / MariaDB Schema

SET FOREIGN_KEY_CHECKS = 0;

-- 1. System Settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(64) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL,
  `setting_group` VARCHAR(32) NOT NULL DEFAULT 'general',
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(48) NOT NULL UNIQUE,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL DEFAULT '',
  `phone` VARCHAR(32) NOT NULL DEFAULT '',
  `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'blocked', 'suspended') NOT NULL DEFAULT 'active',
  `referral_code` VARCHAR(24) NOT NULL UNIQUE,
  `referred_by_user_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_role` (`role`),
  INDEX `idx_user_status` (`status`),
  INDEX `idx_user_referred_by` (`referred_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. User Login Activity Log
CREATE TABLE IF NOT EXISTS `user_logins` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` TEXT NOT NULL,
  `status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_login_user` (`user_id`),
  INDEX `idx_login_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. User Wallets (Fixed-precision monetary values)
CREATE TABLE IF NOT EXISTS `wallets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `balance` DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
  `locked_balance` DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'USD',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Wallet Ledger (Authoritative audit ledger of all balance mutations)
CREATE TABLE IF NOT EXISTS `wallet_ledger` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `amount` DECIMAL(16,4) NOT NULL,
  `balance_before` DECIMAL(16,4) NOT NULL,
  `balance_after` DECIMAL(16,4) NOT NULL,
  `type` ENUM('deposit', 'withdrawal', 'withdrawal_refund', 'bet_placed', 'bet_won', 'bet_refund', 'referral_bonus', 'promo_bonus', 'admin_adjustment') NOT NULL,
  `reference_id` VARCHAR(64) NOT NULL DEFAULT '',
  `reference_type` VARCHAR(32) NOT NULL DEFAULT '',
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ledger_user` (`user_id`),
  INDEX `idx_ledger_type` (`type`),
  INDEX `idx_ledger_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Transactions (Deposits, Withdrawals, Adjustments)
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_ref` VARCHAR(48) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `type` ENUM('deposit', 'withdrawal', 'adjustment', 'bonus') NOT NULL,
  `amount` DECIMAL(16,4) NOT NULL,
  `fee` DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
  `net_amount` DECIMAL(16,4) NOT NULL,
  `payment_method` VARCHAR(48) NOT NULL DEFAULT 'Bank Transfer',
  `payment_details` TEXT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT NULL,
  `approved_by_user_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tx_user` (`user_id`),
  INDEX `idx_tx_type` (`type`),
  INDEX `idx_tx_status` (`status`),
  INDEX `idx_tx_created` (`created_at`),
  CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Sequential Game/Round Infrastructure
-- Sequential IDs starting at 321
CREATE TABLE IF NOT EXISTS `rounds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `round_number` INT NOT NULL UNIQUE,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `betting_closed_at` DATETIME NOT NULL,
  `status` ENUM('upcoming', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
  `betting_status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `result_status` ENUM('pending', 'declared', 'locked', 'settled') NOT NULL DEFAULT 'pending',
  `declared_result` VARCHAR(64) NULL,
  `settlement_status` ENUM('unsettled', 'processing', 'settled') NOT NULL DEFAULT 'unsettled',
  `result_mode` ENUM('auto', 'manual') NOT NULL DEFAULT 'auto',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_round_status` (`status`),
  INDEX `idx_round_betting` (`betting_status`),
  INDEX `idx_round_times` (`start_time`, `end_time`)
) ENGINE=InnoDB AUTO_INCREMENT=321 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Bets Infrastructure (Common base for future game modules)
CREATE TABLE IF NOT EXISTS `bets` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `bet_ref` VARCHAR(48) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `round_id` INT NOT NULL,
  `option_key` VARCHAR(32) NOT NULL,
  `amount` DECIMAL(16,4) NOT NULL,
  `potential_payout` DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
  `payout_amount` DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
  `status` ENUM('placed', 'won', 'lost', 'refunded') NOT NULL DEFAULT 'placed',
  `settled_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_bet_user` (`user_id`),
  INDEX `idx_bet_round` (`round_id`),
  INDEX `idx_bet_status` (`status`),
  INDEX `idx_bet_option` (`round_id`, `option_key`),
  CONSTRAINT `fk_bet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bet_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Round Administrative Audit Records
CREATE TABLE IF NOT EXISTS `round_audit_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `round_id` INT NOT NULL,
  `admin_id` INT NULL,
  `action` VARCHAR(64) NOT NULL,
  `previous_state` TEXT NULL,
  `new_state` TEXT NULL,
  `reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_round` (`round_id`),
  INDEX `idx_audit_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Payment Gateways Configuration
CREATE TABLE IF NOT EXISTS `payment_gateways` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(32) NOT NULL UNIQUE,
  `name` VARCHAR(64) NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `min_deposit` DECIMAL(16,4) NOT NULL DEFAULT 10.0000,
  `max_deposit` DECIMAL(16,4) NOT NULL DEFAULT 5000.0000,
  `min_withdrawal` DECIMAL(16,4) NOT NULL DEFAULT 20.0000,
  `max_withdrawal` DECIMAL(16,4) NOT NULL DEFAULT 2500.0000,
  `deposit_fee_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `withdrawal_fee_pct` DECIMAL(5,2) NOT NULL DEFAULT 1.50,
  `instructions` TEXT NULL,
  `config_json` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Payment Logs & Webhook Architecture
CREATE TABLE IF NOT EXISTS `payment_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` INT NULL,
  `gateway_code` VARCHAR(32) NOT NULL,
  `request_payload` TEXT NULL,
  `response_payload` TEXT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_paylog_tx` (`transaction_id`),
  INDEX `idx_paylog_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Referral System
CREATE TABLE IF NOT EXISTS `referrals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `referrer_id` INT NOT NULL,
  `referee_id` INT NOT NULL UNIQUE,
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  `total_commission` DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ref_referrer` (`referrer_id`),
  CONSTRAINT `fk_ref_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_referee` FOREIGN KEY (`referee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Referral Commissions Ledger
CREATE TABLE IF NOT EXISTS `referral_commissions` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `referral_id` INT NOT NULL,
  `referrer_id` INT NOT NULL,
  `referee_id` INT NOT NULL,
  `amount` DECIMAL(16,4) NOT NULL,
  `source_type` VARCHAR(32) NOT NULL DEFAULT 'deposit',
  `source_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_refcomm_referrer` (`referrer_id`),
  INDEX `idx_refcomm_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Promotional Campaigns & Coupons
CREATE TABLE IF NOT EXISTS `promotions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(32) NOT NULL UNIQUE,
  `title` VARCHAR(128) NOT NULL,
  `description` TEXT NULL,
  `bonus_type` ENUM('fixed', 'percentage') NOT NULL DEFAULT 'percentage',
  `bonus_value` DECIMAL(16,4) NOT NULL,
  `min_deposit` DECIMAL(16,4) NOT NULL DEFAULT 20.0000,
  `max_bonus` DECIMAL(16,4) NOT NULL DEFAULT 100.0000,
  `max_uses` INT NOT NULL DEFAULT 1000,
  `used_count` INT NOT NULL DEFAULT 0,
  `starts_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. User Promotion Redemptions
CREATE TABLE IF NOT EXISTS `user_promotions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `promotion_id` INT NOT NULL,
  `bonus_amount` DECIMAL(16,4) NOT NULL,
  `claimed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_user_promo` (`user_id`, `promotion_id`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_promo` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Notifications (User and Broadcast)
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL, -- NULL indicates global broadcast
  `title` VARCHAR(128) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('system', 'transaction', 'account', 'round') NOT NULL DEFAULT 'system',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user` (`user_id`),
  INDEX `idx_notif_read` (`is_read`),
  INDEX `idx_notif_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Support Tickets
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_number` VARCHAR(32) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `category` VARCHAR(48) NOT NULL DEFAULT 'General',
  `subject` VARCHAR(191) NOT NULL,
  `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
  `status` ENUM('open', 'in_progress', 'answered', 'closed') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_ticket_user` (`user_id`),
  INDEX `idx_ticket_status` (`status`),
  CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Ticket Messages/Replies
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_reply_ticket` (`ticket_id`),
  CONSTRAINT `fk_reply_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Admin Activity Audit Logs
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `action` VARCHAR(64) NOT NULL,
  `target_entity` VARCHAR(48) NOT NULL,
  `target_id` VARCHAR(64) NOT NULL DEFAULT '',
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_admact_admin` (`admin_id`),
  INDEX `idx_admact_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Cron Automation Logs & Locking
CREATE TABLE IF NOT EXISTS `cron_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `task_name` VARCHAR(64) NOT NULL,
  `status` ENUM('running', 'success', 'failed', 'locked') NOT NULL DEFAULT 'success',
  `message` TEXT NULL,
  `execution_time_ms` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cron_task` (`task_name`),
  INDEX `idx_cron_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Cron Lock Mutex
CREATE TABLE IF NOT EXISTS `cron_locks` (
  `lock_key` VARCHAR(64) PRIMARY KEY,
  `locked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `locked_by` VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
