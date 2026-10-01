-- =====================================================
-- Flower Shop App - Phase 1: Database
-- MySQL 8 / MariaDB
-- Import in phpMyAdmin: Import tab -> choose this file -> Go
-- =====================================================

CREATE DATABASE IF NOT EXISTS flower_shop
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE flower_shop;

-- -----------------------------------------------------
-- 1. Admins (shop owner / staff who add flowers)
-- -----------------------------------------------------
CREATE TABLE admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,          -- use password_hash() in PHP
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- 2. Customers (people using the Android app)
-- -----------------------------------------------------
CREATE TABLE customers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  phone         VARCHAR(20)  NOT NULL UNIQUE,   -- login with phone number
  password_hash VARCHAR(255) NOT NULL,
  address       TEXT,
  city          VARCHAR(60),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- 3. Categories (Roses, Bouquets, Wedding, etc.)
-- -----------------------------------------------------
CREATE TABLE categories (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL UNIQUE,
  is_active  TINYINT(1) NOT NULL DEFAULT 1
);

-- -----------------------------------------------------
-- 4. Flowers (products with price and photo)
-- -----------------------------------------------------
CREATE TABLE flowers (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  category_id  INT NULL,
  name         VARCHAR(120) NOT NULL,
  description  TEXT,
  price        DECIMAL(10,2) NOT NULL,           -- in PKR
  image        VARCHAR(255) NOT NULL,            -- file name, e.g. uploads/red-roses.jpg
  stock        INT NOT NULL DEFAULT 0,
  same_day_available TINYINT(1) NOT NULL DEFAULT 1, -- some items may need pre-order
  is_active    TINYINT(1) NOT NULL DEFAULT 1,    -- hide without deleting
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- -----------------------------------------------------
-- 5. Cart (Add to Cart button)
--    "Buy Now" skips this table and goes straight to checkout
-- -----------------------------------------------------
CREATE TABLE cart_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  customer_id  INT NOT NULL,
  flower_id    INT NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  added_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_row_per_flower (customer_id, flower_id),
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (flower_id)   REFERENCES flowers(id)   ON DELETE CASCADE
);

-- -----------------------------------------------------
-- 6. Orders
-- -----------------------------------------------------
CREATE TABLE orders (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  order_number    VARCHAR(20) NOT NULL UNIQUE,   -- e.g. FL-20260930-0001
  customer_id     INT NOT NULL,

  -- delivery details
  receiver_name   VARCHAR(100) NOT NULL,
  receiver_phone  VARCHAR(20)  NOT NULL,
  address         TEXT NOT NULL,
  city            VARCHAR(60)  NOT NULL,
  delivery_type   ENUM('standard','same_day') NOT NULL DEFAULT 'standard',
  delivery_date   DATE NOT NULL,
  gift_message    VARCHAR(255),                  -- card message with flowers

  -- money
  subtotal        DECIMAL(10,2) NOT NULL,
  delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
  total           DECIMAL(10,2) NOT NULL,

  -- payment
  payment_method  ENUM('cod','jazzcash','easypaisa') NOT NULL,
  payment_status  ENUM('unpaid','submitted','verified','rejected') NOT NULL DEFAULT 'unpaid',

  -- order progress
  order_status    ENUM('pending','confirmed','out_for_delivery','delivered','cancelled')
                  NOT NULL DEFAULT 'pending',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- -----------------------------------------------------
-- 7. Order items (price is copied here so old orders
--    stay correct even if the flower price changes later)
-- -----------------------------------------------------
CREATE TABLE order_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT NOT NULL,
  flower_id    INT NULL,
  flower_name  VARCHAR(120) NOT NULL,
  price        DECIMAL(10,2) NOT NULL,
  quantity     INT NOT NULL,
  FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE CASCADE,
  FOREIGN KEY (flower_id) REFERENCES flowers(id) ON DELETE SET NULL
);

-- -----------------------------------------------------
-- 8. Payments (advance payment proof for JazzCash / Easypaisa)
--    Customer sends money, enters Transaction ID + uploads screenshot,
--    admin checks and marks verified or rejected.
-- -----------------------------------------------------
CREATE TABLE payments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  order_id        INT NOT NULL,
  method          ENUM('jazzcash','easypaisa') NOT NULL,
  transaction_id  VARCHAR(50) NOT NULL UNIQUE,   -- stops the same TID being reused
  sender_number   VARCHAR(20),
  amount          DECIMAL(10,2) NOT NULL,
  screenshot      VARCHAR(255),
  status          ENUM('submitted','verified','rejected') NOT NULL DEFAULT 'submitted',
  verified_by     INT NULL,
  admin_note      VARCHAR(255),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id)    REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- -----------------------------------------------------
-- 9. Settings (owner can change these from admin panel,
--    no code changes needed)
-- -----------------------------------------------------
CREATE TABLE settings (
  setting_key   VARCHAR(60) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
);

-- =====================================================
-- Starting data
-- =====================================================

INSERT INTO settings (setting_key, setting_value) VALUES
  ('shop_name',            'Flower Shop'),
  ('standard_delivery_charge', '150'),
  ('same_day_delivery_charge', '350'),
  ('same_day_cutoff_time', '14:00'),          -- same-day only if ordered before 2 PM
  ('same_day_cities',      'Multan'),          -- comma separated
  ('cod_enabled',          '1'),
  ('jazzcash_number',      '03XX-XXXXXXX'),    -- replace with shop number
  ('jazzcash_title',       'Account Title'),
  ('easypaisa_number',     '03XX-XXXXXXX'),
  ('easypaisa_title',      'Account Title');

-- Default admin login: admin@shop.com / admin123  (CHANGE after first login)
INSERT INTO admins (name, email, password_hash) VALUES
  ('Shop Owner', 'admin@shop.com',
   '$2y$12$PE2AmMcrA5AG0pzrsc3ih.RsbgRIbg5Rd0/jOH6lDDYDleCCcxxRy');

INSERT INTO categories (name) VALUES
  ('Roses'), ('Bouquets'), ('Wedding & Events'), ('Plants');

INSERT INTO flowers (category_id, name, description, price, image, stock) VALUES
  (1, 'Red Roses (12 stems)',   'Fresh red roses wrapped in paper', 2500.00, 'uploads/red-roses.jpg',   20),
  (1, 'White Roses (12 stems)', 'Fresh white roses with ribbon',    2800.00, 'uploads/white-roses.jpg', 15),
  (2, 'Mixed Flower Bouquet',   'Seasonal mixed flowers',           3500.00, 'uploads/mixed-bouquet.jpg', 10),
  (3, 'Car Decoration Set',     'Flowers for wedding car',          8000.00, 'uploads/car-decor.jpg',    5),
  (4, 'Money Plant (Pot)',      'Indoor plant in ceramic pot',      1200.00, 'uploads/money-plant.jpg', 25);
