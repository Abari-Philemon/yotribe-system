-- ============================================================
-- YOTRIBE IFMS
-- Module 007: Finance & Accounting
-- Migration 011: Finance Relationship Constraints
--
-- Apply once, after confirming the integrity checks pass.
-- ============================================================

ALTER TABLE financial_years
    ADD UNIQUE KEY uq_financial_year_company_id (company_id, id),
    ADD CONSTRAINT fk_financial_year_company
        FOREIGN KEY (company_id)
        REFERENCES companies (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;

ALTER TABLE financial_periods
    ADD CONSTRAINT fk_financial_period_company_year
        FOREIGN KEY (company_id, financial_year_id)
        REFERENCES financial_years (company_id, id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
