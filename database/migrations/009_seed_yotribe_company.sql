
-- ============================================================
-- YOTRIBE IFMS
-- Module 007: Finance & Accounting
-- Migration 009: Initialize the first company
-- ============================================================

INSERT INTO companies (
    uuid,
    legal_name,
    country_code,
    status
)
SELECT
    UUID(),
    'Yotribe Agro Allied Services',
    'NG',
    'active'
WHERE NOT EXISTS (
    SELECT 1
    FROM companies
    WHERE legal_name = 'Yotribe Agro Allied Services'
);
