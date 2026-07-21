-- P0 transaction-safety migration for existing SupplierHub databases.
-- Run once after taking a database backup.
USE supplierhub_db;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid', 'pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'unpaid' AFTER status,
    ADD COLUMN IF NOT EXISTS resi_pengiriman VARCHAR(100) NULL AFTER smartbank_ref,
    ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(100) NULL AFTER resi_pengiriman,
    ADD UNIQUE INDEX IF NOT EXISTS uq_orders_umkm_idempotency (umkm_id, idempotency_key);

UPDATE orders
SET payment_status = CASE
    WHEN status = 'completed' AND smartbank_ref IS NOT NULL THEN 'paid'
    ELSE 'unpaid'
END;

ALTER TABLE payments
    ADD UNIQUE INDEX IF NOT EXISTS uq_payments_effect (user_id, type, reference_id);
