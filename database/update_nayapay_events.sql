-- =====================================================
-- Update: JazzCash -> NayaPay, and Event Decoration bookings
-- Run ONCE in phpMyAdmin:
--   click flower_shop on the left -> SQL tab -> paste ALL of this -> Go
-- =====================================================

-- ----- 1. JazzCash -> NayaPay -----
-- Allow both for a moment, move old JazzCash rows to NayaPay, then remove JazzCash
ALTER TABLE orders   MODIFY payment_method ENUM('cod','jazzcash','nayapay','easypaisa') NOT NULL;
ALTER TABLE payments MODIFY method ENUM('jazzcash','nayapay','easypaisa') NOT NULL;

UPDATE orders   SET payment_method = 'nayapay' WHERE payment_method = 'jazzcash';
UPDATE payments SET method = 'nayapay'         WHERE method = 'jazzcash';

ALTER TABLE orders   MODIFY payment_method ENUM('cod','nayapay','easypaisa') NOT NULL;
ALTER TABLE payments MODIFY method ENUM('nayapay','easypaisa') NOT NULL;

DELETE FROM settings WHERE setting_key IN ('jazzcash_number', 'jazzcash_title');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('nayapay_number', '03XX-XXXXXXX'),
  ('nayapay_title',  'Account Title');

-- ----- 2. Event types -----
CREATE TABLE IF NOT EXISTS event_types (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL UNIQUE,
  is_active  TINYINT(1) NOT NULL DEFAULT 1
);

-- ----- 3. Event decoration packages -----
CREATE TABLE IF NOT EXISTS event_packages (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  event_type_id  INT NULL,
  name           VARCHAR(120) NOT NULL,
  description    TEXT,
  starting_price DECIMAL(10,2) NOT NULL,
  image          VARCHAR(255) NOT NULL DEFAULT '',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_type_id) REFERENCES event_types(id) ON DELETE SET NULL
);

-- ----- 4. Event bookings -----
CREATE TABLE IF NOT EXISTS event_bookings (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  booking_number  VARCHAR(20) NOT NULL UNIQUE,
  customer_id     INT NOT NULL,
  package_id      INT NULL,
  package_name    VARCHAR(120),
  event_type      VARCHAR(80) NOT NULL,
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
  quoted_price    DECIMAL(10,2) NULL,
  shop_note       VARCHAR(255),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (package_id)  REFERENCES event_packages(id) ON DELETE SET NULL
);

-- ----- 5. Starting data -----
INSERT IGNORE INTO event_types (name) VALUES ('Marriage'), ('Birthday'), ('Engagement');

INSERT INTO event_packages (event_type_id, name, description, starting_price)
SELECT t.id, p.name, p.description, p.price
FROM (
  SELECT 'Marriage'   AS type_name, 'Barat Stage Decoration' AS name,
         'Fresh flower stage, backdrop, entrance arch and walkway' AS description, 80000.00 AS price
  UNION ALL SELECT 'Marriage', 'Mehndi Stage Decoration',
         'Colourful flowers, marigold strings and swing (jhoola) setup', 45000.00
  UNION ALL SELECT 'Birthday', 'Birthday Balloon & Flower Setup',
         'Balloon arch, flower table centre and name backdrop', 15000.00
  UNION ALL SELECT 'Engagement', 'Engagement Floral Backdrop',
         'Flower wall backdrop, stage chairs decoration and ring tray', 35000.00
) p
JOIN event_types t ON t.name = p.type_name
WHERE NOT EXISTS (SELECT 1 FROM event_packages);
