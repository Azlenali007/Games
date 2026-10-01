# Aura Gaming Platform Base Infrastructure

A production-ready, enterprise gaming platform common infrastructure built strictly on **pure PHP (8.2+)**, **MySQL (InnoDB)**, **Tailwind CSS**, and **HTML5**.

Designed specifically as an authoritative, modular common base. No game-specific rules are hardcoded; individual game modules plug directly into this common sequential round and ledger engine.

---

## Key Base Features

1. **Pure PHP & MySQL Architecture**
   - Zero framework overhead (No Laravel, Node, React, or SQLite).
   - Strict PDO prepared statements with SQL injection protection.
   - Fixed-precision monetary handling (`DECIMAL(16,4)`).
   - Atomic ACID transactions with row-level locks (`SELECT FOR UPDATE`).

2. **Sequential Round Engine**
   - Sequential Round IDs (`#321` &rarr; `#322` &rarr; `#323` &rarr; `#324`).
   - Server-synchronized countdowns based on UTC MySQL server time.
   - Betting closes automatically at cutoff time (10s before round end).
   - Strict Result Visibility: Unreleased results are never exposed in HTML, JavaScript, API payloads, or DOM until officially settled.

3. **Authoritative Financial Wallet Ledger**
   - Double-entry audit ledger recording `balance_before`, `balance_after`, `delta amount`, `reference_id`, and `type`.
   - Deposit, withdrawal escrow lock, and automated bet winning settlements.

4. **Live Bet Monitoring Infrastructure**
   - Real-time admin monitoring of active rounds.
   - Option-wise bet counts, wager volume, and maximum exposure / worst-case liability calculations.
   - Individual bet slip logs and highest individual bet tracking.

5. **Dual Result & Settlement Modes**
   - **Automatic Mode**: Deterministic outcome generation upon round end time.
   - **Manual Mode**: Operator declaration and result locking with immutable audit trail.

6. **Completely Separate User & Admin Portals**
   - Dedicated User Navigation (Home, About, How It Works, FAQ, Contact, Dashboard, Arena, Wallet, Tickets, Referrals, Profile).
   - Dedicated Admin Console (Sidebar, Dashboard, Users, Rounds, Live Bets, Results, Deposits, Withdrawals, Tickets, Referrals, Promotions, Broadcasts, Reports, Settings, Audit Logs).

7. **Production Web Installer (`install.php`)**
   - Step 1: System requirements and PHP extensions check (`pdo`, `pdo_mysql`, `session`, `openssl`, etc.).
   - Step 2: MySQL database connection verification and auto-creation.
   - Step 3: Admin account provisioning and branding configuration.
   - Step 4: Database schema import and installer lock via `install.lock`.

---

## Installation & Deployment (Standard LAMP / cPanel / Linux)

### 1. Requirements
- PHP 8.0+ (8.2+ recommended) with `pdo_mysql`, `session`, `json`, `openssl`, `ctype` extensions.
- MySQL 5.7+ or MariaDB 10.3+.
- Web server: Apache (with `mod_rewrite`) or Nginx.

### 2. File Placement
Extract all files inside `public_html/` to your web root (`/var/www/html/` or cPanel `public_html/`).

### 3. Database Setup
Create a MySQL database and import `database.sql`:
```bash
mysql -u your_user -p your_database < database.sql
```
Alternatively, navigate to `http://your-domain.com/install.php` to run the web installer.

### 4. Configuration
Review `includes/config.php` and set your MySQL credentials:
```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'gaming_platform');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

### 5. Automated Cron Setup
Add the master cron task to crontab to run every minute (or via systemd timer):
```bash
* * * * * php /path/to/public_html/cron.php > /dev/null 2>&1
```
Or trigger via HTTP webhook:
```bash
curl -s "https://your-domain.com/cron.php?key=aura_cron_sec_88921a9"
```

---

## Default Testing Accounts

| Role | Username | Password | Notes |
|---|---|---|---|
| **Super Admin** | `admin` | `AdminPassword123!` | Master console at `/admin/login.php` |
| **Player 1** | `player1` | `PlayerPassword123!` | Standard account ($550.00 balance) |
| **VIP Gamer** | `vip_gamer` | `VipPassword123!` | VIP account ($1,250.00 balance) |

---

## Common API Contracts for Future Games

- **Server-Synchronized Countdown**: `GET /api/countdown.php`
- **Active Round & Recent History**: `GET /api/round_status.php`
- **Place Round Bet**: `POST /api/place_bet.php` (params: `csrf_token`, `round_id`, `option_key`, `amount`)
