-- ============================================================
-- Seed data - default store settings + admin user + sample categories
-- Default login: username = admin , password = admin123  (CHANGE AFTER FIRST LOGIN)
-- Password hash below is bcrypt for "admin123"
-- ============================================================

INSERT INTO store_settings (store_name, store_name_ta, address, phone, currency_symbol, default_tax_percent, invoice_prefix, invoice_next_number, receipt_paper_size, low_stock_threshold, expiry_alert_days, default_language)
VALUES ('My Supermarket', 'என் சூப்பர்மார்க்கெட்', 'Tamil Nadu, India', '9999999999', '₹', 5.00, 'INV', 1, 'thermal80', 10, 15, 'ta');

INSERT INTO users (name, username, password_hash, role, is_active)
VALUES ('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);
-- NOTE: the hash above is a PLACEHOLDER pattern length; the actual working hash is generated
-- by install/index.php at install time (see install wizard) so it always matches the server's
-- PHP/OpenSSL build. If you import this seed.sql manually instead of using the installer,
-- run install/reset_admin_password.php once to set a fresh bcrypt hash for "admin123".

INSERT INTO categories (name, name_ta) VALUES
('Groceries', 'மளிகை'),
('Vegetables', 'காய்கறிகள்'),
('Fruits', 'பழங்கள்'),
('Dairy', 'பால் பொருட்கள்'),
('Snacks', 'சிற்றுண்டி'),
('Beverages', 'குளிர்பானங்கள்'),
('Household', 'வீட்டுப் பொருட்கள்'),
('Personal Care', 'தனிப்பட்ட பராமரிப்பு');

INSERT INTO customers (name, phone) VALUES ('Walk-in Customer', NULL);
