-- ═══════════════════════════════════════════════════════════════
--  HyperPrint — MySQL Schema
--  Run once: mysql -u root -p print_system < schema.sql
-- ═══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `print_system`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `print_system`;

-- ── Users ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(80)  NOT NULL UNIQUE,
    `name`       VARCHAR(120) NOT NULL DEFAULT '',
    `email`      VARCHAR(180) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Print Jobs ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `print_jobs` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`              INT UNSIGNED NOT NULL,
    `email`                VARCHAR(180) NOT NULL,
    `file_name`            VARCHAR(255) NOT NULL,
    `file_path`            VARCHAR(512) NOT NULL,
    `pages`                SMALLINT     NOT NULL DEFAULT 1,
    `copies`               SMALLINT     NOT NULL DEFAULT 1,
    `print_type`           ENUM('bw','color')          NOT NULL DEFAULT 'bw',
    `print_sides`          ENUM('single','double')     NOT NULL DEFAULT 'single',
    `cost`                 DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `estimated_time`       INT          NOT NULL DEFAULT 0 COMMENT 'seconds',
    `status`               ENUM('Pending','Printed','Failed') NOT NULL DEFAULT 'Pending',
    `payment_status`       ENUM('pending','paid','refunded')  NOT NULL DEFAULT 'pending',

    -- Razorpay fields (live payment)
    `razorpay_order_id`    VARCHAR(64)  DEFAULT NULL,
    `razorpay_payment_id`  VARCHAR(64)  DEFAULT NULL,
    `razorpay_signature`   VARCHAR(128) DEFAULT NULL,

    `uploaded_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `printed_at`           TIMESTAMP    NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_user`   (`user_id`),
    INDEX `idx_email`  (`email`),
    INDEX `idx_status` (`status`),

    CONSTRAINT `fk_print_jobs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── If upgrading an existing DB (adds missing columns safely) ──
-- Run these ALTER statements only if the table already exists
-- without these columns:

ALTER TABLE `print_jobs`
    ADD COLUMN IF NOT EXISTS `razorpay_order_id`   VARCHAR(64)  DEFAULT NULL AFTER `payment_status`,
    ADD COLUMN IF NOT EXISTS `razorpay_payment_id` VARCHAR(64)  DEFAULT NULL AFTER `razorpay_order_id`,
    ADD COLUMN IF NOT EXISTS `razorpay_signature`  VARCHAR(128) DEFAULT NULL AFTER `razorpay_payment_id`,
    ADD COLUMN IF NOT EXISTS `printed_at`          TIMESTAMP    NULL DEFAULT NULL AFTER `uploaded_at`;
