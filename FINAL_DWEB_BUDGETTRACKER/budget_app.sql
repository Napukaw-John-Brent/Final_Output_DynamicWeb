-- SmartBudget - Normalized Database Schema (3NF)
-- Run this file in phpMyAdmin to create the normalized database from scratch.

CREATE DATABASE IF NOT EXISTS budget_app;
USE budget_app;

-- ─────────────────────────────────────────────────────────────
-- 1. CATEGORIES  (extracted from raw VARCHAR in expenses/category_budgets)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id   INT          AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50)  NOT NULL UNIQUE
);

-- Seed the four default categories
INSERT IGNORE INTO categories (name) VALUES ('Food'), ('Transportation'), ('Bills'), ('Savings');

-- ─────────────────────────────────────────────────────────────
-- 2. USERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id                  INT           AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100)  NOT NULL,
    email               VARCHAR(100)  NOT NULL UNIQUE,
    mobile              VARCHAR(30)   NULL,
    date_of_birth       DATE          NULL,
    password            VARCHAR(255)  NOT NULL,
    security_pin        VARCHAR(255)  NULL,
    reset_token         VARCHAR(64)   NULL,
    reset_token_expires DATETIME      NULL,
    avatar              VARCHAR(255)  NULL,
    city                VARCHAR(100)  NULL,
    country             VARCHAR(100)  NULL,
    created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────────────────────
-- 3. BUDGETS  (one record per user per month)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS budgets (
    id           INT           AUTO_INCREMENT PRIMARY KEY,
    user_id      INT           NOT NULL,
    month        CHAR(7)       NOT NULL,        -- Format: YYYY-MM  e.g. 2025-05
    total_budget DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- 4. CATEGORY_BUDGETS  (child of budgets; references categories by FK)
--    NOTE: 'percentage' removed — it is a derived value (allocated_amount / total_budget * 100)
--          and was a transitive dependency violating 3NF.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS category_budgets (
    id               INT           AUTO_INCREMENT PRIMARY KEY,
    budget_id        INT           NOT NULL,
    category_id      INT           NOT NULL,
    allocated_amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (budget_id)   REFERENCES budgets(id)    ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- ─────────────────────────────────────────────────────────────
-- 5. EXPENSES  (category stored as FK instead of raw VARCHAR)
--    soft-delete: deleted_at = NULL means active; non-NULL means trashed
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expenses (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    category_id INT           NOT NULL,
    description VARCHAR(255)  NULL,
    date        DATE          NOT NULL,
    deleted_at  DATETIME      NULL DEFAULT NULL,
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);


-- ─────────────────────────────────────────────────────────────
-- 7. QR_CODES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS qr_codes (
    id      INT  AUTO_INCREMENT PRIMARY KEY,
    user_id INT  NOT NULL,
    qr_data TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- 8. USER_SETTINGS  (one-to-one with users)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_settings (
    id                    INT         AUTO_INCREMENT PRIMARY KEY,
    user_id               INT         NOT NULL UNIQUE,
    currency              VARCHAR(3)  DEFAULT 'PHP',
    date_format           VARCHAR(10) DEFAULT 'Y-m-d',
    notifications_enabled BOOLEAN     DEFAULT TRUE,
    email_notifications   BOOLEAN     DEFAULT TRUE,
    budget_alerts         BOOLEAN     DEFAULT TRUE,
    theme                 VARCHAR(20) DEFAULT 'dark',
    created_at            TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- 9. NOTIFICATIONS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    type       VARCHAR(50),
    title      VARCHAR(255),
    message    TEXT,
    is_read    BOOLEAN      DEFAULT FALSE,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
);

-- ─────────────────────────────────────────────────────────────
-- INDEXES for performance
-- ─────────────────────────────────────────────────────────────
CREATE INDEX idx_user_email              ON users(email);
CREATE INDEX idx_expenses_user_date      ON expenses(user_id, date);
CREATE INDEX idx_budgets_user_month      ON budgets(user_id, month);
CREATE INDEX idx_cat_budgets_budget      ON category_budgets(budget_id);
