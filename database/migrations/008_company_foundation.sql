-- ============================================================
-- YOTRIBE IFMS
-- Module 007: Finance & Accounting
-- Migration 008: Company Foundation
-- Purpose: Multi-company support with optional farm association
-- ============================================================

CREATE TABLE companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,

    legal_name VARCHAR(150) NOT NULL,
    trading_name VARCHAR(150) NULL,
    registration_number VARCHAR(100) NULL,
    tax_identification_number VARCHAR(100) NULL,

    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state_province VARCHAR(100) NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'NG',

    status ENUM('active', 'inactive', 'suspended')
        NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_uuid (uuid),
    KEY idx_companies_legal_name (legal_name),
    KEY idx_companies_status (status)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE company_farms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    farm_id INT(11) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_company_farms_farm_id (farm_id),
    KEY idx_company_farms_company_id (company_id),

    CONSTRAINT fk_company_farms_company
        FOREIGN KEY (company_id)
        REFERENCES companies (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_company_farms_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;