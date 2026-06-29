-- ════════════════════════════════════════════════════════════
--  HyperPrint Admin — Schema additions
--  Run once: mysql -u root -p print_system < admin_schema.sql
-- ════════════════════════════════════════════════════════════

USE `print_system`;

-- ── Admins table ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(80)  NOT NULL UNIQUE,
    `email`      VARCHAR(180) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Add is_paid column to print_jobs if missing ──────────────
ALTER TABLE `print_jobs`
    ADD COLUMN IF NOT EXISTS `is_paid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_status`;

-- ── Add devices table ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `devices` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100)  NOT NULL,
    `type`        VARCHAR(50)   NOT NULL DEFAULT 'Printer',
    `model`       VARCHAR(100)  DEFAULT '',
    `location`    VARCHAR(150)  DEFAULT '',
    `ip_address`  VARCHAR(45)   DEFAULT '',
    `status`      ENUM('Active','Inactive','Maintenance') NOT NULL DEFAULT 'Active',
    `login_user`  VARCHAR(100)  DEFAULT '',
    `login_pass`  VARCHAR(255)  DEFAULT '',
    `notes`       TEXT,
    `added_on`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `updated_on`  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Insert default admin (password: admin123) ─────────────────
-- Run hash.php to generate a fresh hash, then replace the value below.
INSERT IGNORE INTO `admins` (username, email, password)
VALUES (
    'admin',
    'admin@hyperprint.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
    -- ↑ bcrypt hash of "password" — CHANGE THIS before going live!
    -- Use hash.php to generate: echo password_hash("yourpassword", PASSWORD_DEFAULT);
);
