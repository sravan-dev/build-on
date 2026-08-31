-- Migration: attribute invoices to a project directly.
--
-- Until now an invoice reached its project only through its quotation
-- (invoices.quotation_id -> quotations.project_id). Invoices raised without a
-- quotation ("Void Quotation") therefore had no project at all, and every
-- payment against them was silently excluded from the Projects income/profit
-- figures, which filter on `WHERE q.project_id = p.id`.
--
-- Safe to re-run guard: check first, this script assumes the column is absent.
--   SELECT COUNT(*) FROM information_schema.columns
--   WHERE table_schema = DATABASE() AND table_name = 'invoices' AND column_name = 'project_id';

ALTER TABLE invoices ADD COLUMN project_id INT(11) DEFAULT NULL AFTER quotation_id;

-- Backfill: existing invoices keep the project their quotation pointed at.
UPDATE invoices i
JOIN quotations q ON i.quotation_id = q.id
SET i.project_id = q.project_id
WHERE q.project_id IS NOT NULL;

CREATE INDEX idx_invoices_project_id ON invoices (project_id);
