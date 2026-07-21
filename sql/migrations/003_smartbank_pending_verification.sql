-- Payment request remains pending until SmartBank verifies it.
USE supplierhub_db;

UPDATE orders o
LEFT JOIN payment_attempts p ON p.order_id=o.id AND p.status='pending'
SET o.payment_status='unpaid'
WHERE o.status='pending_payment' AND o.payment_status='pending' AND p.id IS NULL;
