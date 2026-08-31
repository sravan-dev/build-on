-- Migration: persist the quotation discount.
--
-- The quotation form has always collected a discount and subtracted it when
-- computing quotations.total_amount, but the figure itself was never stored.
-- The printed quotation therefore could not show a Subtotal/Discount breakdown,
-- and the line-item sum silently disagreed with the stored (net) total.

ALTER TABLE quotations ADD COLUMN discount DECIMAL(10,2) DEFAULT 0 AFTER total_amount;

-- Existing rows: any gap between the item sum and the stored net total was a discount.
UPDATE quotations q
SET q.discount = GREATEST(0, (
        SELECT COALESCE(SUM(qi.total), 0) FROM quotation_items qi WHERE qi.quotation_id = q.id
    ) - COALESCE(q.total_amount, 0))
WHERE EXISTS (SELECT 1 FROM quotation_items qi WHERE qi.quotation_id = q.id);
