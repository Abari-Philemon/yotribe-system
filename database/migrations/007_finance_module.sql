/*
==============================================================================
YOTRIBE IFMS
Enterprise Fish Farm Management System

Database Migration

Module 007

FINANCE & ACCOUNTING

Version 1.0.0

Author:
YOTRIBE Development Team

Copyright © YOTRIBE Agro Allied Services

==============================================================================
*/

SET SQL_MODE='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

SET FOREIGN_KEY_CHECKS=0;

START TRANSACTION;/*
==============================================================================
TABLE

finance_settings

One Record Per Company
==============================================================================
*/

CREATE TABLE finance_settings (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    default_currency CHAR(3)
        NOT NULL DEFAULT 'NGN',

    currency_symbol VARCHAR(10)
        NOT NULL DEFAULT '₦',

    decimal_places TINYINT
        NOT NULL DEFAULT 2,

    financial_year_start_month
        TINYINT NOT NULL DEFAULT 1,

    financial_year_start_day
        TINYINT NOT NULL DEFAULT 1,

    default_tax_rate
        DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    allow_backdated_entries
        TINYINT(1)
        NOT NULL DEFAULT 0,

    allow_negative_cash
        TINYINT(1)
        NOT NULL DEFAULT 0,

    auto_post_journals
        TINYINT(1)
        NOT NULL DEFAULT 1,

    default_cash_account_id
        BIGINT UNSIGNED NULL,

    default_bank_account_id
        BIGINT UNSIGNED NULL,

    created_by BIGINT UNSIGNED NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_company_finance(company_id),

    UNIQUE KEY uk_finance_uuid(uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


ALTER TABLE finance_settings
    ADD CONSTRAINT fk_finance_settings_cash_account
        FOREIGN KEY (default_cash_account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    ADD CONSTRAINT fk_finance_settings_bank_account
        FOREIGN KEY (default_bank_account_id)
        REFERENCES bank_accounts(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;

CREATE TABLE finance_document_types (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    code VARCHAR(20) NOT NULL,

    name VARCHAR(100) NOT NULL,

    prefix VARCHAR(20) NOT NULL,

    description TEXT NULL,

    is_system TINYINT(1)
        DEFAULT 1,

    is_active TINYINT(1)
        DEFAULT 1,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_document_type(
        company_id,
        code
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;

/*
==============================================================================
TABLE

finance_document_sequences

Maintains running numbers for all financial documents.

One record per:

Company
+
Document Type
+
Financial Year

==============================================================================
*/

CREATE TABLE finance_document_sequences (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    document_type_id BIGINT UNSIGNED NOT NULL,

    financial_year SMALLINT NOT NULL,

    prefix VARCHAR(20) NOT NULL,

    last_number BIGINT UNSIGNED
        NOT NULL DEFAULT 0,

    number_length TINYINT
        NOT NULL DEFAULT 6,

    separator VARCHAR(5)
        NOT NULL DEFAULT '-',

    reset_annually TINYINT(1)
        NOT NULL DEFAULT 1,

    is_active TINYINT(1)
        NOT NULL DEFAULT 1,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_document_sequence (

        company_id,

        document_type_id,

        financial_year

    ),

    UNIQUE KEY uk_sequence_uuid (

        uuid

    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


ALTER TABLE finance_document_sequences
    ADD CONSTRAINT fk_document_sequence_document_type
        FOREIGN KEY (document_type_id)
        REFERENCES finance_document_types(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;

/*
==============================================================================
TABLE

account_types

One Chart of Accounts Structure Per Company

==============================================================================
*/

CREATE TABLE account_types (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    type_code VARCHAR(20) NOT NULL,

    type_name VARCHAR(100) NOT NULL,

    normal_balance ENUM(

        'debit',

        'credit'

    ) NOT NULL,

    display_order INT
        NOT NULL DEFAULT 0,

    description TEXT NULL,

    is_system TINYINT(1)
        NOT NULL DEFAULT 1,

    is_active TINYINT(1)
        NOT NULL DEFAULT 1,

    created_by BIGINT UNSIGNED NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_account_type (

        company_id,

        type_code

    ),

    INDEX idx_account_type_active (

        company_id,

        is_active

    ),

    UNIQUE KEY uk_account_type_uuid (

        uuid

    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

/*
==============================================================================
TABLE

account_classes

Classifies accounts within an account type.

==============================================================================
*/

CREATE TABLE account_classes (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    account_type_id BIGINT UNSIGNED NOT NULL,

    class_code VARCHAR(20) NOT NULL,

    class_name VARCHAR(100) NOT NULL,

    description TEXT NULL,

    display_order INT NOT NULL DEFAULT 0,

    is_system TINYINT(1) NOT NULL DEFAULT 1,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_by BIGINT UNSIGNED NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_account_class (
        company_id,
        class_code
    ),

    UNIQUE KEY uk_account_class_uuid (uuid),

    CONSTRAINT fk_account_classes_type
        FOREIGN KEY (account_type_id)
        REFERENCES account_types(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

/*
==============================================================================
TABLE

accounts

Company Chart of Accounts

==============================================================================
*/

CREATE TABLE accounts (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    account_type_id BIGINT UNSIGNED NOT NULL,

    account_class_id BIGINT UNSIGNED NOT NULL,

    parent_account_id BIGINT UNSIGNED NULL,

    account_code VARCHAR(30) NOT NULL,

    system_key VARCHAR(100) NOT NULL,

    account_name VARCHAR(150) NOT NULL,

    short_name VARCHAR(50) NULL,

    description TEXT NULL,

    normal_balance ENUM(
        'debit',
        'credit'
    ) NOT NULL,

    account_level SMALLINT NOT NULL DEFAULT 1,

    account_path VARCHAR(500) NULL,

    opening_balance DECIMAL(18,2)
        NOT NULL DEFAULT 0.00,

    current_balance DECIMAL(18,2)
        NOT NULL DEFAULT 0.00,

    allow_posting TINYINT(1)
        NOT NULL DEFAULT 1,

    is_control_account TINYINT(1)
        NOT NULL DEFAULT 0,

    is_cash_account TINYINT(1)
        NOT NULL DEFAULT 0,

    is_bank_account TINYINT(1)
        NOT NULL DEFAULT 0,

    is_system TINYINT(1)
        NOT NULL DEFAULT 0,

    is_active TINYINT(1)
        NOT NULL DEFAULT 1,

    created_by BIGINT UNSIGNED NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_account_code (
        company_id,
        account_code
    ),

    UNIQUE KEY uk_account_system_key (
        company_id,
        system_key
    ),

    UNIQUE KEY uk_account_uuid (uuid),

    INDEX idx_parent (parent_account_id),

    INDEX idx_type (account_type_id),

    INDEX idx_class (account_class_id),

    CONSTRAINT fk_accounts_type
        FOREIGN KEY (account_type_id)
        REFERENCES account_types(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_accounts_class
        FOREIGN KEY (account_class_id)
        REFERENCES account_classes(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_accounts_parent
        FOREIGN KEY (parent_account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

/*
==============================================================================
TABLE : financial_years
==============================================================================*/

CREATE TABLE financial_years (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    year_code VARCHAR(20) NOT NULL,

    year_name VARCHAR(100) NOT NULL,

    start_date DATE NOT NULL,

    end_date DATE NOT NULL,

    status ENUM(
        'OPEN',
        'CLOSED',
        'LOCKED'
    ) NOT NULL DEFAULT 'OPEN',

    is_current TINYINT(1)
        NOT NULL DEFAULT 0,

    closed_by BIGINT UNSIGNED NULL,

    closed_at DATETIME NULL,

    created_by BIGINT UNSIGNED NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_financial_year(
        company_id,
        year_code
    ),

    UNIQUE KEY uk_financial_year_uuid(uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

/*
==============================================================================
TABLE : financial_periods
==============================================================================*/

CREATE TABLE financial_periods (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    financial_year_id BIGINT UNSIGNED NOT NULL,

    period_no TINYINT NOT NULL,

    period_code VARCHAR(20) NOT NULL,

    period_name VARCHAR(50) NOT NULL,

    start_date DATE NOT NULL,

    end_date DATE NOT NULL,

    status ENUM(
        'OPEN',
        'CLOSED',
        'LOCKED'
    ) NOT NULL DEFAULT 'OPEN',

    is_current TINYINT(1)
        NOT NULL DEFAULT 0,

    closed_by BIGINT UNSIGNED NULL,

    closed_at DATETIME NULL,

    created_by BIGINT UNSIGNED NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_financial_period(
        company_id,
        financial_year_id,
        period_no
    ),

    UNIQUE KEY uk_financial_period_uuid(uuid),

    CONSTRAINT fk_period_year

        FOREIGN KEY (financial_year_id)

        REFERENCES financial_years(id)

        ON UPDATE CASCADE

        ON DELETE RESTRICT

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

/*
==============================================================================
TABLE : finance_posting_rules
==============================================================================*/

CREATE TABLE finance_posting_rules (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    transaction_code VARCHAR(50) NOT NULL,

    transaction_name VARCHAR(150) NOT NULL,

    debit_account_id BIGINT UNSIGNED NOT NULL,

    credit_account_id BIGINT UNSIGNED NOT NULL,

    description TEXT NULL,

    auto_post TINYINT(1)
        NOT NULL DEFAULT 1,

    is_active TINYINT(1)
        NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_posting_rule(
        company_id,
        transaction_code
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

ALTER TABLE finance_posting_rules
    ADD CONSTRAINT fk_posting_rule_debit_account
        FOREIGN KEY (debit_account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    ADD CONSTRAINT fk_posting_rule_credit_account
        FOREIGN KEY (credit_account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;

/*
==============================================================================
TABLE : journal_entries
==============================================================================*/

CREATE TABLE journal_entries (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    farm_id BIGINT UNSIGNED NOT NULL,

    financial_year_id BIGINT UNSIGNED NOT NULL,

    financial_period_id BIGINT UNSIGNED NOT NULL,

    document_sequence_id BIGINT UNSIGNED NOT NULL,

    document_type_id BIGINT UNSIGNED NOT NULL,

    journal_no VARCHAR(50) NOT NULL,

    journal_date DATE NOT NULL,

    transaction_code VARCHAR(50) NOT NULL,

    source_module VARCHAR(50) NOT NULL,

    source_table VARCHAR(100) NULL,

    source_id BIGINT UNSIGNED NULL,

    reference_no VARCHAR(100) NULL,

    narration TEXT NOT NULL,

    status ENUM(
        'DRAFT',
        'POSTED',
        'REVERSED',
        'CANCELLED'
    ) NOT NULL DEFAULT 'DRAFT',

    total_debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    total_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    posted_by BIGINT UNSIGNED NULL,

    posted_at DATETIME NULL,

    reversed_by BIGINT UNSIGNED NULL,

    reversed_at DATETIME NULL,

    reversal_journal_id BIGINT UNSIGNED NULL,

    created_by BIGINT UNSIGNED NOT NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_journal_no (
        company_id,
        journal_no
    ),

    INDEX idx_company_farm (
        company_id,
        farm_id
    ),

    INDEX idx_journal_date (journal_date),

    INDEX idx_status (status),

    INDEX idx_source (
        source_module,
        source_id
    ),

    UNIQUE KEY uk_journal_uuid (uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

ALTER TABLE journal_entries


    ADD CONSTRAINT fk_journal_financial_year
        FOREIGN KEY (financial_year_id)
        REFERENCES financial_years(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    ADD CONSTRAINT fk_journal_financial_period
        FOREIGN KEY (financial_period_id)
        REFERENCES financial_periods(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    ADD CONSTRAINT fk_journal_document_sequence
        FOREIGN KEY (document_sequence_id)
        REFERENCES finance_document_sequences(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    ADD CONSTRAINT fk_journal_document_type
        FOREIGN KEY (document_type_id)
        REFERENCES finance_document_types(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;




ALTER TABLE journal_entries
    ADD CONSTRAINT fk_journal_reversal
        FOREIGN KEY (reversal_journal_id)
        REFERENCES journal_entries(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;
/*
==============================================================================
TABLE : journal_entry_lines
==============================================================================*/

CREATE TABLE journal_entry_lines (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    journal_entry_id BIGINT UNSIGNED NOT NULL,

    account_id BIGINT UNSIGNED NOT NULL,

    line_no SMALLINT NOT NULL,

    entry_type ENUM(
        'DEBIT',
        'CREDIT'
    ) NOT NULL,

    amount DECIMAL(18,2) NOT NULL,

    description VARCHAR(255) NULL,

    posting_rule_line_id BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_journal (journal_entry_id),

    INDEX idx_account (account_id),

    INDEX idx_entry_type (entry_type),

    UNIQUE KEY uk_journal_line (
        journal_entry_id,
        line_no
    ),

    UNIQUE KEY uk_journal_line_uuid (uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

ALTER TABLE journal_entry_lines

    ADD CONSTRAINT fk_journal_line_journal
        FOREIGN KEY (journal_entry_id)
        REFERENCES journal_entries(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    ADD CONSTRAINT fk_journal_line_account
        FOREIGN KEY (account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
/*
==============================================================================
TABLE : ledger_balances
==============================================================================
*/

CREATE TABLE ledger_balances (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    farm_id BIGINT UNSIGNED NOT NULL,

    financial_year_id BIGINT UNSIGNED NOT NULL,

    financial_period_id BIGINT UNSIGNED NOT NULL,

    account_id BIGINT UNSIGNED NOT NULL,

    opening_debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    opening_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    period_debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    period_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    closing_debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    closing_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    last_posted_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_ledger_balance (
        company_id,
        farm_id,
        financial_period_id,
        account_id
    ),

    UNIQUE KEY uk_ledger_balance_uuid (uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ledger_balances

    ADD CONSTRAINT fk_ledger_year
        FOREIGN KEY (financial_year_id)
        REFERENCES financial_years(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    ADD CONSTRAINT fk_ledger_period
        FOREIGN KEY (financial_period_id)
        REFERENCES financial_periods(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    ADD CONSTRAINT fk_ledger_account
        FOREIGN KEY (account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;

/*
==============================================================================
TABLE : bank_accounts
==============================================================================
*/

CREATE TABLE bank_accounts (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    ledger_account_id BIGINT UNSIGNED NOT NULL,

    bank_code VARCHAR(20) NULL,

    bank_name VARCHAR(100) NOT NULL,

    account_name VARCHAR(150) NOT NULL,

    account_number VARCHAR(30) NOT NULL,

    branch_name VARCHAR(100) NULL,

    currency_code CHAR(3) NOT NULL DEFAULT 'NGN',

    opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    current_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    is_default TINYINT(1) NOT NULL DEFAULT 0,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_by BIGINT UNSIGNED NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_company_account (
        company_id,
        account_number
    ),

    UNIQUE KEY uk_bank_uuid (uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bank_accounts

    ADD CONSTRAINT fk_bank_ledger_account
        FOREIGN KEY (ledger_account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;

/*
==============================================================================
TABLE : bank_transactions
==============================================================================
*/

CREATE TABLE bank_transactions (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    farm_id BIGINT UNSIGNED NOT NULL,

    bank_account_id BIGINT UNSIGNED NOT NULL,

    journal_entry_id BIGINT UNSIGNED NULL,

    transaction_date DATE NOT NULL,

    transaction_type ENUM(
        'DEPOSIT',
        'WITHDRAWAL',
        'TRANSFER',
        'ADJUSTMENT'
    ) NOT NULL,

    reference_no VARCHAR(100) NULL,

    description TEXT NULL,

    debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_bank_transaction_uuid (uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
ALTER TABLE bank_transactions

    ADD CONSTRAINT fk_bank_transaction_bank
        FOREIGN KEY (bank_account_id)
        REFERENCES bank_accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    ADD CONSTRAINT fk_bank_transaction_journal
        FOREIGN KEY (journal_entry_id)
        REFERENCES journal_entries(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;
/*
==============================================================================
TABLE : cash_book
==============================================================================
*/

CREATE TABLE cash_book (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    farm_id BIGINT UNSIGNED NOT NULL,

    journal_entry_id BIGINT UNSIGNED NOT NULL,

    account_id BIGINT UNSIGNED NOT NULL,

    transaction_date DATE NOT NULL,

    reference_no VARCHAR(100) NULL,

    description TEXT NULL,

    debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    running_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_cash_book_uuid (uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
ALTER TABLE cash_book

    ADD CONSTRAINT fk_cashbook_journal
        FOREIGN KEY (journal_entry_id)
        REFERENCES journal_entries(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    ADD CONSTRAINT fk_cashbook_account
        FOREIGN KEY (account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
/*
==============================================================================
TABLE : income_categories
==============================================================================
*/

CREATE TABLE income_categories (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    code VARCHAR(30) NOT NULL,

    category_name VARCHAR(150) NOT NULL,

    ledger_account_id BIGINT UNSIGNED NOT NULL,

    description TEXT NULL,

    is_system TINYINT(1) NOT NULL DEFAULT 0,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_by BIGINT UNSIGNED NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_income_category (
        company_id,
        code
    ),

    UNIQUE KEY uk_income_category_uuid (uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
ALTER TABLE income_categories

    ADD CONSTRAINT fk_income_category_account
        FOREIGN KEY (ledger_account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
/*
==============================================================================
TABLE : expense_categories
==============================================================================
*/

CREATE TABLE expense_categories (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    code VARCHAR(30) NOT NULL,

    category_name VARCHAR(150) NOT NULL,

    ledger_account_id BIGINT UNSIGNED NOT NULL,

    requires_approval TINYINT(1) NOT NULL DEFAULT 0,

    description TEXT NULL,

    is_system TINYINT(1) NOT NULL DEFAULT 0,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_by BIGINT UNSIGNED NULL,

    updated_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_expense_category (
        company_id,
        code
    ),

    UNIQUE KEY uk_expense_category_uuid (uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
ALTER TABLE expense_categories

    ADD CONSTRAINT fk_expense_category_account
        FOREIGN KEY (ledger_account_id)
        REFERENCES accounts(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
/*


/*
==============================================================================
TABLE : finance_audit_log
==============================================================================
*/

CREATE TABLE finance_audit_log (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL,

    company_id BIGINT UNSIGNED NOT NULL,

    farm_id BIGINT UNSIGNED NULL,

    module VARCHAR(50) NOT NULL,

    entity_name VARCHAR(100) NOT NULL,

    entity_id BIGINT UNSIGNED NOT NULL,

    action VARCHAR(50) NOT NULL,

    old_values JSON NULL,

    new_values JSON NULL,

    remarks TEXT NULL,

    performed_by BIGINT UNSIGNED NOT NULL,

    ip_address VARCHAR(45) NULL,

    user_agent VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_company (company_id),

    INDEX idx_entity (entity_name, entity_id),

    INDEX idx_action (action),

    UNIQUE KEY uk_finance_audit_uuid (uuid)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
