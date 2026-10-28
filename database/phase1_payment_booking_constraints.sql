-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
SELECT `key`, COUNT(*) AS duplicate_count
FROM idempotency_keys
GROUP BY `key`
HAVING COUNT(*) > 1;
SELECT code, COUNT(*) AS duplicate_count
FROM orders
WHERE code IS NOT NULL
GROUP BY code
HAVING COUNT(*) > 1;
SELECT gateway_order_code, COUNT(*) AS duplicate_count
FROM orders
WHERE gateway_order_code IS NOT NULL
GROUP BY gateway_order_code
HAVING COUNT(*) > 1;
SELECT gateway_order_code, COUNT(*) AS duplicate_count
FROM payments
WHERE gateway_order_code IS NOT NULL
GROUP BY gateway_order_code
HAVING COUNT(*) > 1;
SELECT order_id, COUNT(*) AS duplicate_count
FROM payments
WHERE order_id IS NOT NULL
GROUP BY order_id
HAVING COUNT(*) > 1;
SELECT
    o.showtime_id,
    oi.item_id AS seat_id,
    COUNT(*) AS duplicate_count
FROM order_items oi
JOIN orders o ON o.id = oi.order_id
WHERE oi.item_type = 'App\\Models\\Seat'
  AND o.status IN (1, 2)
GROUP BY o.showtime_id, oi.item_id
HAVING COUNT(*) > 1;

-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------

ALTER TABLE idempotency_keys
    ADD UNIQUE KEY idempotency_keys_key_unique (`key`);

ALTER TABLE orders
    ADD UNIQUE KEY orders_code_unique (code),
    ADD UNIQUE KEY orders_gateway_order_code_unique (gateway_order_code),
    ADD INDEX orders_status_payment_status_expired_at_index (status, payment_status, expired_at),
    ADD INDEX orders_user_id_status_index (user_id, status),
    ADD INDEX orders_showtime_id_status_index (showtime_id, status);

ALTER TABLE payments
    ADD UNIQUE KEY payments_order_id_unique (order_id),
    ADD UNIQUE KEY payments_gateway_order_code_unique (gateway_order_code),
    ADD INDEX payments_user_id_status_index (user_id, status),
    ADD INDEX payments_status_paid_at_index (status, paid_at);

ALTER TABLE seat_holds
    ADD INDEX seat_holds_user_showtime_expires_index (user_id, showtime_id, expires_at),
    ADD INDEX seat_holds_showtime_expires_index (showtime_id, expires_at);

ALTER TABLE order_items
    ADD INDEX order_items_order_type_item_index (order_id, item_type, item_id),
    ADD INDEX order_items_type_item_index (item_type, item_id);

-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
