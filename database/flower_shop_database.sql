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
  api_token     CHAR(64) NULL UNIQUE,         -- login token (hashed), added in Phase 2
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
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  image      VARCHAR(255) NULL                  -- photo for the category tile on the home page
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
  old_price    DECIMAL(10,2) NULL,               -- price before a sale (shown crossed out)
  is_featured  TINYINT(1) NOT NULL DEFAULT 0,    -- show in Top selling on the home page
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
  payment_method  ENUM('cod','nayapay','easypaisa') NOT NULL,
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
-- 8. Payments (advance payment proof for NayaPay / Easypaisa)
--    Customer sends money, enters Transaction ID + uploads screenshot,
--    admin checks and marks verified or rejected.
-- -----------------------------------------------------
CREATE TABLE payments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  order_id        INT NOT NULL,
  method          ENUM('nayapay','easypaisa') NOT NULL,
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


-- -----------------------------------------------------
-- 10. Event types (Marriage, Birthday, Engagement ...)
-- -----------------------------------------------------
CREATE TABLE event_types (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL UNIQUE,
  is_active  TINYINT(1) NOT NULL DEFAULT 1
);

-- -----------------------------------------------------
-- 11. Event decoration packages (shown in the app's Events tab)
-- -----------------------------------------------------
CREATE TABLE event_packages (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  event_type_id  INT NULL,
  name           VARCHAR(120) NOT NULL,
  description    TEXT,
  starting_price DECIMAL(10,2) NOT NULL,        -- "From Rs ..." (final price agreed by phone)
  image          VARCHAR(255) NOT NULL DEFAULT '',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_type_id) REFERENCES event_types(id) ON DELETE SET NULL
);

-- -----------------------------------------------------
-- 12. Event bookings (customer sends a request, shop calls and confirms)
-- -----------------------------------------------------
CREATE TABLE event_bookings (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  booking_number  VARCHAR(20) NOT NULL UNIQUE,  -- e.g. EV-20261001-0001
  customer_id     INT NOT NULL,
  package_id      INT NULL,                     -- NULL = custom request
  package_name    VARCHAR(120),                 -- copied, stays correct if package changes
  event_type      VARCHAR(80) NOT NULL,         -- copied: Marriage / Birthday / ...
  starting_price  DECIMAL(10,2) NULL,
  event_date      DATE NOT NULL,
  event_time      TIME NULL,
  venue_address   TEXT NOT NULL,
  city            VARCHAR(60) NOT NULL,
  guests          INT NULL,
  contact_name    VARCHAR(100) NOT NULL,
  contact_phone   VARCHAR(20) NOT NULL,
  notes           TEXT,
  status          ENUM('new','contacted','confirmed','completed','cancelled') NOT NULL DEFAULT 'new',
  quoted_price    DECIMAL(10,2) NULL,           -- final price agreed with the customer
  shop_note       VARCHAR(255),                 -- message the customer sees in the app
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (package_id)  REFERENCES event_packages(id) ON DELETE SET NULL
);

-- =====================================================
-- Starting data
-- =====================================================

INSERT INTO settings (setting_key, setting_value) VALUES
  ('shop_name',            'Flower Shop'),
  ('schema_version',       '1'),               -- used by config/migrate.php, do not change
  ('standard_delivery_charge', '150'),
  ('same_day_delivery_charge', '350'),
  ('same_day_cutoff_time', '14:00'),          -- same-day only if ordered before 2 PM
  ('delivery_cities',      'Islamabad, Rawalpindi'),  -- customers can only order to these cities
  ('same_day_cities',      'Islamabad, Rawalpindi'),  -- comma separated, must be delivery cities
  ('google_review_url',    ''),                -- link for the Rate us on Google button
  ('whatsapp_alerts_enabled', '0'),            -- send new orders to the shop's WhatsApp
  ('whatsapp_alert_phone', ''),
  ('whatsapp_alert_apikey', ''),
  ('cod_enabled',          '1'),
  ('nayapay_number',       '03XX-XXXXXXX'),    -- NayaPay number or ID, replace with shop's
  ('nayapay_title',        'Account Title'),
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

INSERT INTO event_types (name) VALUES ('Marriage'), ('Birthday'), ('Engagement');

-- Sample packages: the shop owner changes names, prices and photos in the admin panel
INSERT INTO event_packages (event_type_id, name, description, starting_price) VALUES
  (1, 'Barat Stage Decoration',  'Fresh flower stage, backdrop, entrance arch and walkway', 80000.00),
  (1, 'Mehndi Stage Decoration', 'Colourful flowers, marigold strings and swing (jhoola) setup', 45000.00),
  (2, 'Birthday Balloon & Flower Setup', 'Balloon arch, flower table centre and name backdrop', 15000.00),
  (3, 'Engagement Floral Backdrop', 'Flower wall backdrop, stage chairs decoration and ring tray', 35000.00);
