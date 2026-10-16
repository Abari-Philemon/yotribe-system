ALTER TABLE financial_periods
    ADD CONSTRAINT fk_financial_period_company_year
        FOREIGN KEY (company_id, financial_year_id)
        REFERENCES financial_years (company_id, id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
