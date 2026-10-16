ALTER TABLE financial_years
    ADD COLUMN current_company_id BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE WHEN is_current = 1 THEN company_id ELSE NULL END
        ) PERSISTENT,
    ADD UNIQUE KEY uq_financial_year_current_company (current_company_id);
