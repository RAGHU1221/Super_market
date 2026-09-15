-- ============================================================
-- Supermarket Management Suite - Database Schema
-- Engine: MySQL 5.7+/MariaDB, InnoDB, utf8mb4 (Tamil support)
-- Built for shared hosting (InfinityFree / AIC Cloud) - PDO, no Composer
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Store / Settings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS store_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_name VARCHAR(150) NOT NULL DEFAULT 'My Supermarket',
    store_name_ta VARCHAR(150) DEFAULT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    gstin VARCHAR(20) DEFAULT NULL,
    fssai_no VARCHAR(20) DEFAULT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    currency_symbol VARCHAR(5) DEFAULT '₹',
    default_tax_percent DECIMAL(5,2) DEFAULT 0.00,
    invoice_prefix VARCHAR(10) DEFAULT 'INV',
    invoice_next_number INT DEFAULT 1,
    receipt_paper_size ENUM('thermal80','a4') DEFAULT 'thermal80',
    low_stock_threshold INT DEFAULT 10,
    expiry_alert_days INT DEFAULT 15,
    default_language ENUM('ta','en') DEFAULT 'ta',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Users / Roles / Staff
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','cashier') NOT NULL DEFAULT 'cashier',
    phone VARCHAR(20) DEFAULT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    salary DECIMAL(10,2) DEFAULT NULL,
    joining_date DATE DEFAULT NULL,
    api_token VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_username (username),
    KEY idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_shift_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    logout_at DATETIME DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    device VARCHAR(20) DEFAULT 'web',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_user_login (user_id, login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Categories / Products / Suppliers
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ta VARCHAR(100) DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    address TEXT,
    gstin VARCHAR(20) DEFAULT NULL,
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(30) NOT NULL,
    barcode VARCHAR(50) DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    name_ta VARCHAR(150) DEFAULT NULL,
    category_id INT DEFAULT NULL,
    supplier_id INT DEFAULT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs',        -- pcs, kg, ltr, box, etc
    hsn_code VARCHAR(20) DEFAULT NULL,
    tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,  -- GST %
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    mrp DECIMAL(10,2) DEFAULT NULL,
    stock_qty DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    reorder_level DECIMAL(10,3) NOT NULL DEFAULT 5.000,
    expiry_date DATE DEFAULT NULL,
    batch_no VARCHAR(50) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sku (sku),
    KEY idx_barcode (barcode),
    KEY idx_category (category_id),
    KEY idx_name (name),
    KEY idx_expiry (expiry_date),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Purchases (stock IN)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_no VARCHAR(30) NOT NULL,
    supplier_id INT DEFAULT NULL,
    invoice_no VARCHAR(50) DEFAULT NULL,
    purchase_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('paid','partial','unpaid') NOT NULL DEFAULT 'unpaid',
    notes TEXT,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_purchase_no (purchase_no),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    qty DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    batch_no VARCHAR(50) DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    KEY idx_purchase (purchase_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Stock adjustments (damage / correction / stock-take)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    adjustment_type ENUM('add','remove') NOT NULL,
    qty DECIMAL(10,3) NOT NULL,
    reason VARCHAR(150) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Customers (optional, for credit/loyalty)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL DEFAULT 'Walk-in Customer',
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT,
    loyalty_points INT DEFAULT 0,
    credit_balance DECIMAL(12,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Sales (Billing / POS) - stock OUT
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL,
    customer_id INT DEFAULT NULL,
    cashier_id INT DEFAULT NULL,
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    round_off DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_mode ENUM('cash','card','upi','credit','split') NOT NULL DEFAULT 'cash',
    status ENUM('completed','held','cancelled') NOT NULL DEFAULT 'completed',
    sync_source ENUM('web','desktop','mobile') NOT NULL DEFAULT 'web',
    sync_uid VARCHAR(64) DEFAULT NULL COMMENT 'client-generated UUID for offline dedupe',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice (invoice_no),
    UNIQUE KEY uq_sync_uid (sync_uid),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_sale_date (sale_date),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    qty DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    KEY idx_sale (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sale_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    sale_item_id INT NOT NULL,
    qty DECIMAL(10,3) NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reason VARCHAR(150) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (sale_item_id) REFERENCES sale_items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Held bills (parked carts, web/desktop/mobile)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS held_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hold_ref VARCHAR(30) NOT NULL,
    cashier_id INT DEFAULT NULL,
    customer_name VARCHAR(100) DEFAULT NULL,
    cart_json LONGTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hold_ref (hold_ref),
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Sync log (mobile/desktop <-> server)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    direction ENUM('pull','push') NOT NULL,
    entity VARCHAR(30) NOT NULL,
    record_count INT DEFAULT 0,
    status ENUM('success','partial','failed') DEFAULT 'success',
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_device (device_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
