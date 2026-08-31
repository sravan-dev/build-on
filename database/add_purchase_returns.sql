-- Migration: record goods/amounts returned to a vendor against a purchase.
--
-- A return reduces what is still payable on the purchase. If the purchase was
-- already paid in full, the balance goes negative — that is a refund owed by
-- the vendor, and the purchases list labels it as such.

CREATE TABLE IF NOT EXISTS purchase_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    return_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    invoice_number VARCHAR(100),
    reason TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_purchase_returns_purchase (purchase_id),
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
