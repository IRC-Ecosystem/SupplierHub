-- PRD-aligned local procurement/payment flow.
USE supplierhub_db;

ALTER TABLE orders
    MODIFY COLUMN status ENUM('pending','approved','submitted','pending_payment','paid','payment_failed','processing','shipped','partially_received','received','completed','rejected','cancelled') NOT NULL DEFAULT 'submitted',
    ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER idempotency_key;

UPDATE orders SET status = 'submitted' WHERE status = 'pending';
UPDATE orders SET status = 'pending_payment' WHERE status = 'approved';
UPDATE orders SET status = 'paid', paid_at = COALESCE(paid_at, completed_at) WHERE status = 'completed' AND payment_status = 'paid';

ALTER TABLE orders
    MODIFY COLUMN status ENUM('submitted','pending_payment','paid','payment_failed','processing','shipped','partially_received','received','completed','rejected','cancelled') NOT NULL DEFAULT 'submitted';

CREATE TABLE IF NOT EXISTS order_status_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    actor_user_id INT NULL,
    reason VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_status_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_history_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_status_history_order (order_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    idempotency_key VARCHAR(100) NOT NULL,
    status ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
    payment_reference VARCHAR(100) NULL,
    error_message VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_attempt_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY uq_payment_attempt_key (idempotency_key),
    INDEX idx_payment_attempt_order (order_id)
) ENGINE=InnoDB;

INSERT INTO order_status_history (order_id, from_status, to_status, actor_user_id, reason, created_at)
SELECT o.id, NULL, o.status, NULL, 'Migrasi data awal', o.created_at
FROM orders o
WHERE NOT EXISTS (SELECT 1 FROM order_status_history h WHERE h.order_id = o.id);
