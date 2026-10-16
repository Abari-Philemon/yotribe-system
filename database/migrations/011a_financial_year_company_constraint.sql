ALTER TABLE financial_years
    ADD UNIQUE KEY uq_financial_year_company_id (company_id, id),
    ADD CONSTRAINT fk_financial_year_company
        FOREIGN KEY (company_id)
        REFERENCES companies (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
