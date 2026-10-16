ALTER TABLE financial_periods
    ADD COLUMN current_financial_year_id BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE WHEN is_current = 1 THEN financial_year_id ELSE NULL END
        ) PERSISTENT,
    ADD UNIQUE KEY uq_financial_period_current_year
        (company_id, current_financial_year_id);
