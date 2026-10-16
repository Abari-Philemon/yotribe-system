
-- ============================================================
-- YOTRIBE IFMS
-- Module 007: Finance & Accounting
-- Migration 010: Finance Settings Company Relationship
-- ============================================================

-- Refuse to proceed if any existing settings reference
-- a company that does not exist.
DELIMITER //

CREATE PROCEDURE validate_finance_settings_company_fk()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM finance_settings fs
        LEFT JOIN companies c ON c.id = fs.company_id
        WHERE c.id IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Cannot add company foreign key: orphan finance_settings.company_id found.';
    END IF;
END//

DELIMITER ;

CALL validate_finance_settings_company_fk();

DROP PROCEDURE validate_finance_settings_company_fk;

ALTER TABLE finance_settings
    ADD CONSTRAINT fk_finance_settings_company
    FOREIGN KEY (company_id)
    REFERENCES companies (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT;
