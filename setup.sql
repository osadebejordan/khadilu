-- ============================================
--  Khadilu Phone Hub — Database Setup
--  Run this once, then open install.php
-- ============================================

CREATE DATABASE IF NOT EXISTS khadilu_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE khadilu_hub;

-- Users
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'staff') DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customers (for debt tracking)
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(20),
  type ENUM('Retail', 'Repair', 'Both') DEFAULT 'Retail',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customer Debt Payments
CREATE TABLE IF NOT EXISTS customer_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method VARCHAR(50),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Part Categories (Dynamic)
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) UNIQUE NOT NULL
);

-- Quality Grades (Dynamic)
CREATE TABLE IF NOT EXISTS qualities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) UNIQUE NOT NULL,
  category_name VARCHAR(60) DEFAULT NULL  -- Optionally restrict this quality to a category
);

-- Insert default categories and qualities
INSERT IGNORE INTO categories (name) VALUES ('Smartphone'), ('Tablet'), ('Laptop'), ('Accessory'), ('Charger'), ('Screen'), ('Battery'), ('Other');
INSERT IGNORE INTO qualities (name) VALUES ('Original'), ('Refurbished'), ('Used'), ('Generic');

-- Products (inventory items)
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  brand VARCHAR(80),
  compatible_models TEXT,
  category VARCHAR(60) DEFAULT 'Smartphone',
  quality VARCHAR(50) DEFAULT 'Original',
  sku VARCHAR(60) UNIQUE,
  current_stock INT DEFAULT 0,
  selling_price DECIMAL(12,2) DEFAULT 0.00,
  cost_price DECIMAL(12,2) DEFAULT 0.00,
  low_stock_threshold INT DEFAULT 3,
  is_deleted TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Stock Inflow (purchases / restocking)
CREATE TABLE IF NOT EXISTS stock_inflow (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  quantity INT NOT NULL,
  cost_per_unit DECIMAL(12,2) DEFAULT 0.00,
  total_cost DECIMAL(12,2) DEFAULT 0.00,
  supplier VARCHAR(120) DEFAULT 'Unknown',
  supplier_phone VARCHAR(20) DEFAULT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Stock Outflow (sales)
CREATE TABLE IF NOT EXISTS stock_outflow (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  quantity INT NOT NULL,
  cost_price DECIMAL(12,2) DEFAULT 0.00,
  price_per_unit DECIMAL(12,2) DEFAULT 0.00,
  total_amount DECIMAL(12,2) DEFAULT 0.00,
  amount_paid DECIMAL(12,2) DEFAULT 0.00,
  customer_id INT DEFAULT NULL,
  customer_name VARCHAR(120) DEFAULT 'Walk-in',
  payment_method ENUM('Cash','Transfer','POS','Other') DEFAULT 'Cash',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Stock Returns (Customer returns)
CREATE TABLE IF NOT EXISTS stock_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  customer_id INT DEFAULT NULL,
  user_id INT NOT NULL,
  quantity INT NOT NULL,
  refund_amount DECIMAL(12,2) DEFAULT 0.00,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Sales Transactions (Header for multi-item sales)
CREATE TABLE IF NOT EXISTS sales_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT DEFAULT NULL,
  customer_name VARCHAR(120) NOT NULL,
  user_id INT NOT NULL,
  total_cart_value DECIMAL(12,2) NOT NULL,
  total_paid DECIMAL(12,2) NOT NULL,
  payment_method ENUM('Cash','Transfer','POS','Other') DEFAULT 'Cash',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- Add transaction_id to stock_outflow
ALTER TABLE stock_outflow ADD COLUMN transaction_id INT DEFAULT NULL AFTER id;
ALTER TABLE stock_outflow ADD CONSTRAINT fk_transaction_id FOREIGN KEY (transaction_id) REFERENCES sales_transactions(id) ON DELETE CASCADE;

-- Repairs Management
CREATE TABLE IF NOT EXISTS repairs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT DEFAULT NULL,
  product_id INT DEFAULT NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_phone VARCHAR(20) DEFAULT NULL,
  device_model VARCHAR(150) NOT NULL,
  category VARCHAR(60) DEFAULT NULL,
  issue_description TEXT NOT NULL,
  status ENUM('Received', 'In-Progress', 'Ready', 'Delivered', 'Cancelled') DEFAULT 'Received',
  estimated_cost DECIMAL(12,2) DEFAULT 0.00,
  amount_paid DECIMAL(12,2) DEFAULT 0.00,
  user_id INT NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- View for centralized debt calculation
-- Repair balance correctly deducts deposits already paid at intake
CREATE OR REPLACE VIEW view_customer_balances AS
SELECT 
    c.*,
    (
        (SELECT IFNULL(SUM(total_amount - amount_paid), 0) FROM stock_outflow WHERE customer_id = c.id) + 
        (SELECT IFNULL(SUM(estimated_cost - amount_paid), 0) FROM repairs WHERE customer_id = c.id) - 
        (SELECT IFNULL(SUM(amount), 0) FROM customer_payments WHERE customer_id = c.id) -
        (SELECT IFNULL(SUM(refund_amount), 0) FROM stock_returns WHERE customer_id = c.id AND status = 'Approved')
    ) AS balance
FROM customers c;

-- Business Expenses
CREATE TABLE IF NOT EXISTS business_expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category ENUM('Rent', 'Electricity', 'Salary', 'Marketing', 'Transport', 'Others') DEFAULT 'Others',
  amount DECIMAL(12,2) NOT NULL,
  description TEXT,
  expense_date DATE DEFAULT (CURRENT_DATE),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System Audit Log
CREATE TABLE IF NOT EXISTS system_audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  table_name VARCHAR(60),
  record_id INT,
  details TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
