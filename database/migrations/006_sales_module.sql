/*
============================================================
PACKAGE 006
Sales & Distribution Management
Part 1
============================================================
*/

SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================================
-- SALES
-- ==========================================================

CREATE TABLE IF NOT EXISTS sales (

    id INT AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL UNIQUE,

    farm_id INT NOT NULL,

    harvest_id INT NOT NULL,

    sale_no VARCHAR(50) NOT NULL UNIQUE,

    sale_date DATETIME NOT NULL,

    customer_name VARCHAR(150) DEFAULT NULL,

    customer_phone VARCHAR(30) DEFAULT NULL,

    customer_address TEXT DEFAULT NULL,

    sale_type ENUM(
        'customer_sale',
        'staff_share',
        'company_use',
        'donation',
        'promotion',
        'mortality_disposal',
        'return'
    ) NOT NULL DEFAULT 'customer_sale',

    status ENUM(
        'draft',
        'completed',
        'cancelled',
        'refunded'
    ) NOT NULL DEFAULT 'draft',

    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,

    discount DECIMAL(15,2) NOT NULL DEFAULT 0,

    tax DECIMAL(15,2) NOT NULL DEFAULT 0,

    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,

    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,

    balance DECIMAL(15,2) NOT NULL DEFAULT 0,

    remarks TEXT NULL,

    recorded_by INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NULL DEFAULT NULL
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_sales_uuid (uuid),
    INDEX idx_sales_farm (farm_id),
    INDEX idx_sales_harvest (harvest_id),
    INDEX idx_sales_date (sale_date),
    INDEX idx_sales_status (status),

    CONSTRAINT fk_sales_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id),

    CONSTRAINT fk_sales_harvest
        FOREIGN KEY (harvest_id)
        REFERENCES harvests(id)

);



-- ==========================================================
-- SALE ITEMS
-- ==========================================================

CREATE TABLE IF NOT EXISTS sale_items (

    id INT AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL UNIQUE,

    sale_id INT NOT NULL,

    harvest_pond_id INT NULL,

    product_id VARCHAR(120) NOT NULL,

    quantity_fish INT DEFAULT NULL,

    quantity_kg DECIMAL(12,2) NOT NULL,

    average_weight_kg DECIMAL(10,3) DEFAULT NULL,

    unit_price DECIMAL(15,2) NOT NULL,

    line_total DECIMAL(15,2) NOT NULL,

    remarks TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sale_items_sale (sale_id),
    INDEX idx_sale_items_uuid (uuid),

    CONSTRAINT fk_sale_items_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_sale_items_harvest_pond
        FOREIGN KEY (harvest_pond_id)
        REFERENCES harvest_ponds(id)

);

SET FOREIGN_KEY_CHECKS = 1;

/*
============================================================
PACKAGE 006
Sales & Distribution Management
Part 2
============================================================
*/

SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================================
-- SALE PAYMENTS
-- ==========================================================

CREATE TABLE IF NOT EXISTS sale_payments (

    id INT AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL UNIQUE,

    sale_id INT NOT NULL,

    payment_no VARCHAR(50) NOT NULL UNIQUE,

    payment_date DATETIME NOT NULL,

    payment_method ENUM(
        'cash',
        'transfer',
        'pos',
        'credit',
        'wallet',
        'multiple'
    ) NOT NULL,

    amount DECIMAL(15,2) NOT NULL,

    reference_no VARCHAR(100) DEFAULT NULL,

    bank_name VARCHAR(100) DEFAULT NULL,

    account_name VARCHAR(150) DEFAULT NULL,

    account_number VARCHAR(30) DEFAULT NULL,

    transaction_reference VARCHAR(150) DEFAULT NULL,

    payment_status ENUM(
        'pending',
        'completed',
        'failed',
        'reversed'
    ) NOT NULL DEFAULT 'completed',

    remarks TEXT DEFAULT NULL,

    received_by INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sale_payment_sale (sale_id),
    INDEX idx_sale_payment_uuid (uuid),
    INDEX idx_sale_payment_method (payment_method),

    CONSTRAINT fk_sale_payment_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(id)
        ON DELETE CASCADE

);

-- ==========================================================
-- SALE DISTRIBUTIONS
-- ==========================================================

CREATE TABLE IF NOT EXISTS sale_distributions (

    id INT AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL UNIQUE,

    sale_id INT NOT NULL,

    distribution_no VARCHAR(50) NOT NULL UNIQUE,

    distribution_type ENUM(

        'staff_share',

        'company_use',

        'donation',

        'promotion',

        'mortality_disposal',

        'return'

    ) NOT NULL,

    recipient_name VARCHAR(150) DEFAULT NULL,

    recipient_phone VARCHAR(30) DEFAULT NULL,

    recipient_staff_id INT DEFAULT NULL,

    quantity_fish INT DEFAULT NULL,

    quantity_kg DECIMAL(12,2) NOT NULL,

    estimated_value DECIMAL(15,2) DEFAULT 0,

    approved_by INT DEFAULT NULL,

    approved_at DATETIME DEFAULT NULL,

    released_by INT DEFAULT NULL,

    released_at DATETIME DEFAULT NULL,

    status ENUM(

        'pending',

        'approved',

        'released',

        'cancelled'

    ) NOT NULL DEFAULT 'pending',

    remarks TEXT DEFAULT NULL,

    created_by INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sale_distribution_sale (sale_id),
    INDEX idx_sale_distribution_type (distribution_type),
    INDEX idx_sale_distribution_status (status),

    CONSTRAINT fk_sale_distribution_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(id)
        ON DELETE CASCADE

);

-- ==========================================================
-- SALE RECEIPTS
-- ==========================================================

CREATE TABLE IF NOT EXISTS sale_receipts (

    id INT AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL UNIQUE,

    sale_id INT NOT NULL,

    receipt_no VARCHAR(50) NOT NULL UNIQUE,

    receipt_date DATETIME NOT NULL,

    receipt_status ENUM(

        'pending',

        'printed',

        'reprinted',

        'cancelled'

    ) NOT NULL DEFAULT 'pending',

    print_count INT NOT NULL DEFAULT 0,

    printed_by INT DEFAULT NULL,

    printed_at DATETIME DEFAULT NULL,

    pdf_path VARCHAR(255) DEFAULT NULL,

    qr_code VARCHAR(255) DEFAULT NULL,

    remarks TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sale_receipt_sale (sale_id),
    INDEX idx_sale_receipt_no (receipt_no),

    CONSTRAINT fk_sale_receipt_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(id)
        ON DELETE CASCADE

);

SET FOREIGN_KEY_CHECKS = 1;
/*
============================================================
PACKAGE 006
Sales & Distribution Management
Part 3
Audit Logs
Offline Synchronization
Devices
============================================================
*/

SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================================
-- SALE LOGS
-- ==========================================================

CREATE TABLE IF NOT EXISTS sale_logs (

    id INT AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL UNIQUE,

    sale_id INT NOT NULL,

    action ENUM(

        'create',
        'update',
        'payment',
        'distribution',
        'receipt',
        'print',
        'refund',
        'cancel',
        'sync'

    ) NOT NULL,

    description TEXT NOT NULL,

    old_values JSON NULL,

    new_values JSON NULL,

    ip_address VARCHAR(50) NULL,

    user_agent VARCHAR(255) NULL,

    recorded_by INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sale_logs_sale (sale_id),
    INDEX idx_sale_logs_action (action),
    INDEX idx_sale_logs_created (created_at),

    CONSTRAINT fk_sale_logs_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(id)
        ON DELETE CASCADE

);

-- ==========================================================
-- SALES SYNC QUEUE
-- ==========================================================

CREATE TABLE IF NOT EXISTS sales_sync_queue (

    id INT AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL UNIQUE,

    sale_uuid CHAR(36) NOT NULL,

    device_uuid CHAR(36) NOT NULL,

    operation ENUM(

        'insert',

        'update',

        'delete'

    ) NOT NULL,

    payload_json JSON NOT NULL,

    status ENUM(

        'pending',

        'syncing',

        'synced',

        'failed'

    ) NOT NULL DEFAULT 'pending',

    retry_count INT NOT NULL DEFAULT 0,

    last_attempt DATETIME NULL,

    synced_at DATETIME NULL,

    error_message TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sales_sync_status (status),
    INDEX idx_sales_sync_sale (sale_uuid),
    INDEX idx_sales_sync_device (device_uuid)

);

-- ==========================================================
-- SYNC LOGS
-- ==========================================================

CREATE TABLE IF NOT EXISTS sync_logs (

    id INT AUTO_INCREMENT PRIMARY KEY,

    queue_uuid CHAR(36) NOT NULL,

    request_json JSON NULL,

    response_json JSON NULL,

    status ENUM(

        'success',

        'failed'

    ) NOT NULL,

    duration_ms INT DEFAULT NULL,

    error_message TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sync_logs_queue (queue_uuid),
    INDEX idx_sync_logs_status (status)

);

-- ==========================================================
-- REGISTERED DEVICES
-- ==========================================================

CREATE TABLE IF NOT EXISTS devices (

    id INT AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL UNIQUE,

    farm_id INT NOT NULL,

    staff_id INT DEFAULT NULL,

    device_name VARCHAR(120) NOT NULL,

    device_type ENUM(

        'desktop',

        'laptop',

        'tablet',

        'mobile',

        'server'

    ) NOT NULL,

    platform VARCHAR(50) NULL,

    app_version VARCHAR(30) NULL,

    last_ip VARCHAR(50) NULL,

    last_seen DATETIME NULL,

    status ENUM(

        'active',

        'inactive',

        'blocked'

    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_device_uuid (uuid),
    INDEX idx_device_status (status),

    CONSTRAINT fk_devices_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)

);

SET FOREIGN_KEY_CHECKS = 1;