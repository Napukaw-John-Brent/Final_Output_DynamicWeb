-- SmartBudget Database Schema
-- Run this file in phpMyAdmin to set up the database from scratch.

CREATE DATABASE IF NOT EXISTS budget_app;
USE budget_app;

-- ─────────────────────────────────────────────────────────────
-- 1. USERS
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
-- 2. BUDGETS  (one record per user per month)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS budgets (
    id           INT           AUTO_INCREMENT PRIMARY KEY,
    user_id      INT           NOT NULL,
    month        CHAR(7)       NOT NULL,   -- Format: YYYY-MM  e.g. 2025-05
    total_budget DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- 3. CATEGORY_BUDGETS  (per user, per month, per category)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS category_budgets (
    id               INT           AUTO_INCREMENT PRIMARY KEY,
    user_id          INT           NOT NULL,
    month            CHAR(7)       NOT NULL,
    category         VARCHAR(50)   NOT NULL,
    allocated_amount DECIMAL(10,2) NOT NULL,
    percentage       DECIMAL(5,2)  NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- 4. EXPENSES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expenses (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    category    VARCHAR(50)   NOT NULL,
    description VARCHAR(255)  NULL,
    date        DATE          NOT NULL,
    deleted_at  DATETIME      NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- 5. QR_CODES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS qr_codes (
    id      INT  AUTO_INCREMENT PRIMARY KEY,
    user_id INT  NOT NULL,
    qr_data TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- INDEXES for performance
-- ─────────────────────────────────────────────────────────────
CREATE INDEX idx_user_email          ON users(email);
CREATE INDEX idx_expenses_user_date  ON expenses(user_id, date);
CREATE INDEX idx_budgets_user_month  ON budgets(user_id, month);
CREATE INDEX idx_catbud_user_month   ON category_budgets(user_id, month);
