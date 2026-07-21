USE supplierhub_db;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS fulfillment_eta DATETIME DEFAULT NULL AFTER resi_pengiriman,
    ADD COLUMN IF NOT EXISTS shipped_at DATETIME DEFAULT NULL AFTER paid_at,
    ADD COLUMN IF NOT EXISTS received_at DATETIME DEFAULT NULL AFTER shipped_at,
    ADD COLUMN IF NOT EXISTS cancellation_reason VARCHAR(255) DEFAULT NULL AFTER received_at;

CREATE TABLE IF NOT EXISTS supplier_profiles (
    supplier_id INT PRIMARY KEY,
    business_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    lead_time_days INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE,
    CHECK (lead_time_days BETWEEN 0 AND 365)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS goods_receipts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    receipt_code VARCHAR(40) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    idempotency_key VARCHAR(100) NOT NULL UNIQUE,
    received_by INT NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    receipt_status ENUM('partial','full') NOT NULL,
    inventory_sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_goods_receipts_order (order_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    goods_receipt_id BIGINT NOT NULL,
    order_item_id INT NOT NULL,
    accepted_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    rejection_reason VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_receipt_order_item (goods_receipt_id, order_item_id),
    CHECK (accepted_qty >= 0 AND rejected_qty >= 0),
    CHECK (accepted_qty + rejected_qty > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS procurement_disputes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    dispute_code VARCHAR(40) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    opened_by INT NOT NULL,
    category ENUM('shortage','damaged','quality','late','other') NOT NULL,
    description VARCHAR(500) NOT NULL,
    status ENUM('open','resolved','rejected') NOT NULL DEFAULT 'open',
    resolution_note VARCHAR(500) DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_disputes_order (order_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS outbox_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id CHAR(36) NOT NULL UNIQUE,
    aggregate_type VARCHAR(60) NOT NULL,
    aggregate_id VARCHAR(60) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_version SMALLINT NOT NULL DEFAULT 1,
    payload JSON NOT NULL,
    status ENUM('pending','published','failed') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    available_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_outbox_pending (status, available_at)
) ENGINE=InnoDB;

INSERT INTO supplier_profiles (supplier_id,business_name,contact_name,lead_time_days)
SELECT id,name,name,1 FROM users WHERE role='supplier'
ON DUPLICATE KEY UPDATE business_name=VALUES(business_name);
