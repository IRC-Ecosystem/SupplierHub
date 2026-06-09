-- ========================================
-- SupplierHub Database Setup
-- ========================================

CREATE DATABASE IF NOT EXISTS supplierhub_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE supplierhub_db;

-- ========================================
-- 1. USERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('umkm', 'supplier') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ========================================
-- 2. MATERIALS TABLE (Bahan Baku)
-- ========================================
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price INT NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL DEFAULT 'Kg',
    icon VARCHAR(50) DEFAULT 'ph-package',
    supplier_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================
-- 3. ORDERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(30) NOT NULL UNIQUE,
    umkm_id INT NOT NULL,
    supplier_id INT NOT NULL,
    status ENUM('pending', 'approved', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
    subtotal INT NOT NULL DEFAULT 0,
    fee_supplier INT NOT NULL DEFAULT 0,
    total INT NOT NULL DEFAULT 0,
    smartbank_ref VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (umkm_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================
-- 4. ORDER ITEMS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    material_id INT NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    price_at_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================
-- 5. TRANSACTION LOGS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS transaction_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(100) NOT NULL,
    method VARCHAR(10) NOT NULL DEFAULT 'POST',
    user_id INT DEFAULT NULL,
    request_data TEXT DEFAULT NULL,
    response_data TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ========================================
-- 6. PAYMENTS TABLE (LOCAL LOG)
-- ========================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('debit', 'credit') NOT NULL,
    amount INT NOT NULL,
    description VARCHAR(255),
    reference_id VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================
-- SEEDER DATA
-- ========================================

-- Users (password = bcrypt hash of 'password123')
INSERT INTO users (id, name, email, password, role) VALUES
(1, 'SupplierHub', 'supplier@b2blink.com', '$2y$10$mWm02z6ClvVWIBal.MbSbOl0b5gCKdALG6LHWXIJlZvEeqaf/WWZW', 'supplier'),
(2, 'Warung Bu Ani', 'umkm@b2blink.com', '$2y$10$mWm02z6ClvVWIBal.MbSbOl0b5gCKdALG6LHWXIJlZvEeqaf/WWZW', 'umkm');

-- Materials (sesuai data asli di supplierhub.html)
INSERT INTO materials (id, material_code, name, category, price, stock, unit, icon, supplier_id) VALUES
(1, 'MAT-001', 'Tepung Terigu Segitiga Biru', 'Bahan Pokok', 12000, 500, 'Kg', 'ph-package', 1),
(2, 'MAT-002', 'Gula Pasir Kristal Putih', 'Bahan Pokok', 16500, 350, 'Kg', 'ph-cube', 1),
(3, 'MAT-003', 'Minyak Goreng Sawit', 'Cair', 17000, 200, 'Liter', 'ph-drop', 1),
(4, 'MAT-004', 'Telur Ayam Horn', 'Bahan Pokok', 28000, 150, 'Tray', 'ph-egg', 1),
(5, 'MAT-005', 'Bawang Merah Brebes', 'Bumbu & Rempah', 35000, 50, 'Kg', 'ph-plant', 1);

-- Incoming Orders (pending)
INSERT INTO orders (id, order_code, umkm_id, supplier_id, status, subtotal, fee_supplier, total, created_at) VALUES
(1, 'ORD-B2B-901', 2, 1, 'pending', 405000, 12150, 417150, '2026-04-24 10:15:00'),
(2, 'ORD-B2B-902', 2, 1, 'pending', 1760000, 52800, 1812800, '2026-04-24 10:30:00');

-- Order Items for ORD-B2B-901
INSERT INTO order_items (order_id, material_id, qty, price_at_order) VALUES
(1, 1, 20, 12000),
(1, 2, 10, 16500);

-- Order Items for ORD-B2B-902
INSERT INTO order_items (order_id, material_id, qty, price_at_order) VALUES
(2, 1, 100, 12000),
(2, 4, 20, 28000);

-- Completed Order History
INSERT INTO orders (id, order_code, umkm_id, supplier_id, status, subtotal, fee_supplier, total, smartbank_ref, created_at, completed_at) VALUES
(3, 'ORD-B2B-881', 2, 1, 'completed', 150000, 4500, 154500, 'SB-REF-20260420-001', '2026-04-20 10:00:00', '2026-04-20 10:35:00');

INSERT INTO order_items (order_id, material_id, qty, price_at_order) VALUES
(3, 1, 10, 12000),
(3, 2, 2, 16500);
