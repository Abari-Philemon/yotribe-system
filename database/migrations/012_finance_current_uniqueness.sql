-- ============================================================
-- YOTRIBE IFMS
-- Module 007: Finance & Accounting
-- Migration 012: Current Financial Year and Period Uniqueness
-- ============================================================

ALTER TABLE financial_years
    ADD COLUMN current_company_id BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE WHEN is_current = 1 THEN company_id ELSE NULL END
        ) PERSISTENT,
    ADD UNIQUE KEY uq_financial_year_current_company (current_company_id);

ALTER TABLE financial_periods
    ADD COLUMN current_financial_year_id BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE WHEN is_current = 1 THEN financial_year_id ELSE NULL END
        ) PERSISTENT,
    ADD UNIQUE KEY uq_financial_period_current_year (company_id, current_financial_year_id);
