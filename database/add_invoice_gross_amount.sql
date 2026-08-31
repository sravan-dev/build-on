-- Migration: store the invoice gross (pre-discount) amount explicitly.
--
-- total_amount is stored NET of discount. Every path that changed a discount had
-- to reconstruct the gross as total_amount + discount, which is ambiguous for an
-- invoice with no line items: it cannot tell a header-only invoice (raised
-- without a quotation, gross lives on the header) from an item-based invoice
-- whose items were all deleted (gross should fall to 0). Storing the gross
-- removes the reconstruction entirely.

ALTER TABLE invoices ADD COLUMN gross_amount DECIMAL(10,2) DEFAULT NULL AFTER total_amount;

-- Backfill: today's gross is the stored net plus the discount that produced it.
UPDATE invoices SET gross_amount = COALESCE(total_amount, 0) + COALESCE(discount, 0);
