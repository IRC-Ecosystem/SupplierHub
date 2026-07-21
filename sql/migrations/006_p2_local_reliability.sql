USE supplierhub_db;

ALTER TABLE orders
    MODIFY COLUMN status ENUM('submitted','pending_payment','paid','payment_failed','processing','shipped','partially_received','received','completed','rejected','cancelled','refund_pending','refunded','refund_failed') NOT NULL DEFAULT 'submitted';

ALTER TABLE outbox_events
    MODIFY COLUMN status ENUM('pending','published','failed','dead_letter') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS destination VARCHAR(80) NOT NULL DEFAULT 'local_mock',
    ADD COLUMN IF NOT EXISTS delivery_mode ENUM('mock','external') NOT NULL DEFAULT 'mock',
    ADD COLUMN IF NOT EXISTS max_attempts INT NOT NULL DEFAULT 5,
    ADD COLUMN IF NOT EXISTS last_error VARCHAR(500) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS inbox_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) NOT NULL,
    source_system VARCHAR(80) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('processed','failed') NOT NULL DEFAULT 'processed',
    error_message VARCHAR(500) DEFAULT NULL,
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inbox_event (source_system,event_id),
    INDEX idx_inbox_type (event_type, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS webhook_receipts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(80) NOT NULL,
    event_id VARCHAR(100) NOT NULL,
    signature_hash CHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    status ENUM('accepted','replayed','rejected','failed') NOT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_webhook_provider_event (provider,event_id),
    INDEX idx_webhook_received (received_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reconciliation_issues (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    fingerprint CHAR(64) NOT NULL UNIQUE,
    issue_type VARCHAR(80) NOT NULL,
    severity ENUM('warning','critical') NOT NULL DEFAULT 'warning',
    order_id INT DEFAULT NULL,
    details JSON NOT NULL,
    status ENUM('open','resolved','ignored') NOT NULL DEFAULT 'open',
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_recon_open (status, severity, detected_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS refund_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    refund_code VARCHAR(50) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    requested_by INT NOT NULL,
    idempotency_key VARCHAR(100) NOT NULL UNIQUE,
    amount INT NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
    external_reference VARCHAR(100) DEFAULT NULL,
    failure_reason VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK (amount > 0),
    INDEX idx_refund_order (order_id, status)
) ENGINE=InnoDB;
