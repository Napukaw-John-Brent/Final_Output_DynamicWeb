-- Budget App database - run this whole file to create everything from scratch
-- In phpMyAdmin: create DB first if needed, then select budget_app and run the CREATE TABLE statements
-- Or run: mysql -u root < budget_app.sql

CREATE DATABASE IF NOT EXISTS budget_app;
USE budget_app;

-- Users (login/register, forgot password, security pin)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  mobile VARCHAR(30) NULL,
  date_of_birth DATE NULL,
  password VARCHAR(255),
  security_pin VARCHAR(255) NULL,
  reset_token VARCHAR(64) NULL,
  reset_token_expires DATETIME NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  city VARCHAR(80) DEFAULT NULL,
  country VARCHAR(80) DEFAULT NULL
);

-- Budgets per user per month
CREATE TABLE IF NOT EXISTS budgets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  month VARCHAR(20),
  total_budget DECIMAL(10,2)
);

-- Category budgets per user per month
CREATE TABLE IF NOT EXISTS category_budgets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  month VARCHAR(20),
  category VARCHAR(50),
  allocated_amount DECIMAL(10,2),
  percentage DECIMAL(5,2)
);

-- Expenses per user
CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  amount DECIMAL(10,2),
  category VARCHAR(50),
  description VARCHAR(255),
  date DATE
);

-- Deals (shared list, compared to remaining budget)
CREATE TABLE IF NOT EXISTS deals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100),
  price DECIMAL(10,2)
);

-- Saved QR data per user
CREATE TABLE IF NOT EXISTS qr_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  qr_data TEXT
);