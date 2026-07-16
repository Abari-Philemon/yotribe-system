/*
======================================================================
YOTRIBE IFMS
Module      : Harvest Management
Migration   : 005
Version     : 1.0.0
Database    : MySQL
Author      : Yotribe IFMS Development Team
======================================================================

DESCRIPTION

Creates the Harvest Management core tables.

Business Flow

Fish Batch
    ↓
Pond Stocking
    ↓
Harvest
    ↓
Sales
    ↓
Finance

======================================================================
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;

-- =====================================================
-- TABLE : harvests
-- =====================================================

CREATE TABLE IF NOT EXISTS harvests (

    id INT AUTO_INCREMENT PRIMARY KEY,

    harvest_no VARCHAR(30) NOT NULL,

    farm_id INT NOT NULL,

    fish_batch_id INT NOT NULL,

    harvest_date DATE NOT NULL,

    status ENUM(
        'draft',
        'selling',
        'closed',
        'cancelled'
    ) NOT NULL DEFAULT 'draft',

    is_open TINYINT(1) NOT NULL DEFAULT 1,

    remarks TEXT NULL,

    created_by INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    closed_at TIMESTAMP NULL DEFAULT NULL,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_harvest_no (harvest_no),

    INDEX idx_farm (farm_id),
    INDEX idx_batch (fish_batch_id),
    INDEX idx_status (status),
    INDEX idx_open (is_open),
    INDEX idx_date (harvest_date),

    CONSTRAINT fk_harvest_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id),

    CONSTRAINT fk_harvest_batch
        FOREIGN KEY (fish_batch_id)
        REFERENCES fish_batches(id)

);

-- =====================================================
-- TABLE : harvest_ponds
-- =====================================================

CREATE TABLE IF NOT EXISTS harvest_ponds (

    id INT AUTO_INCREMENT PRIMARY KEY,

    harvest_id INT NOT NULL,

    pond_stocking_id INT NOT NULL,

    pond_id INT NOT NULL,

    batch_id INT NOT NULL,

    harvest_start DATETIME NULL,

    harvest_end DATETIME NULL,

    remarks VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_harvest (harvest_id),

    INDEX idx_pond_stocking (pond_stocking_id),

    INDEX idx_pond (pond_id),

    INDEX idx_batch (batch_id),

    CONSTRAINT fk_hp_harvest
        FOREIGN KEY (harvest_id)
        REFERENCES harvests(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_hp_stocking
        FOREIGN KEY (pond_stocking_id)
        REFERENCES pond_stocking(id),

    CONSTRAINT fk_hp_pond
        FOREIGN KEY (pond_id)
        REFERENCES ponds_tanks(id),

    CONSTRAINT fk_hp_batch
        FOREIGN KEY (batch_id)
        REFERENCES fish_batches(id)

);
/*=====================================================================
TABLE : harvest_movements

Purpose

Records EVERY movement from an open harvest.

Movement Sources

SALE
STAFF_WELFARE
COMPANY_USE
DONATION
SAMPLE
LOSS
ADJUSTMENT

This table becomes the operational source of truth for
everything leaving a harvest.

=====================================================================*/

CREATE TABLE IF NOT EXISTS harvest_movements (

    id INT AUTO_INCREMENT PRIMARY KEY,

    movement_no VARCHAR(30) NOT NULL,

    harvest_id INT NOT NULL,

    movement_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    movement_source ENUM(
        'sale',
        'staff_welfare',
        'company_use',
        'donation',
        'sample',
        'loss',
        'adjustment'
    ) NOT NULL,

    quantity_kg DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    fish_count INT NOT NULL DEFAULT 0,

    average_weight_kg DECIMAL(10,4) GENERATED ALWAYS AS (
        CASE
            WHEN fish_count > 0
            THEN quantity_kg / fish_count
            ELSE 0
        END
    ) STORED,
    available_fish INT NOT NULL DEFAULT 0,

    available_weight_kg DECIMAL(12,2) NOT NULL DEFAULT 0,

    sold_fish INT NOT NULL DEFAULT 0,

    sold_weight_kg DECIMAL(12,2) NOT NULL DEFAULT 0,

    distributed_fish INT NOT NULL DEFAULT 0,

distributed_weight_kg DECIMAL(12,2) NOT NULL DEFAULT 0,

    recipient VARCHAR(150) NULL,

    reference_no VARCHAR(100) NULL,

    reference_table VARCHAR(100) NULL,

    remarks TEXT NULL,

    created_by INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_movement_no (movement_no),

    INDEX idx_harvest (harvest_id),
    INDEX idx_source (movement_source),
    INDEX idx_date (movement_date),
    INDEX idx_reference (reference_no),

    CONSTRAINT fk_hm_harvest
        FOREIGN KEY (harvest_id)
        REFERENCES harvests(id)
        ON DELETE CASCADE

);



/*=====================================================================
TABLE : harvest_logs

Purpose

System audit trail.

Nothing operational is stored here.

Only audit information.

=====================================================================*/

CREATE TABLE IF NOT EXISTS harvest_logs (

    id INT AUTO_INCREMENT PRIMARY KEY,

    harvest_id INT NOT NULL,

    action VARCHAR(100) NOT NULL,

    description TEXT NULL,

    staff_id INT NULL,

    ip_address VARCHAR(45) NULL,

    user_agent VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_harvest (harvest_id),

    INDEX idx_action (action),

    INDEX idx_created (created_at),

    CONSTRAINT fk_log_harvest
        FOREIGN KEY (harvest_id)
        REFERENCES harvests(id)
        ON DELETE CASCADE

);



/*=====================================================================
RESERVED MOVEMENT SOURCES

sale

staff_welfare

company_use

donation

sample

loss

adjustment

Future Version 2

transfer

processing

cold_room

=====================================================================*/

/*======================================================================
PACKAGE 005
HARVEST MANAGEMENT

PART 3

Indexes
Business Rules
Verification
Deployment

Version : 1.0.0
======================================================================*/


/*======================================================================
ADDITIONAL INDEXES
======================================================================*/

CREATE INDEX idx_harvest_created_by
ON harvests(created_by);

CREATE INDEX idx_harvest_pond_created
ON harvest_ponds(created_at);

CREATE INDEX idx_movement_created_by
ON harvest_movements(created_by);

CREATE INDEX idx_log_staff
ON harvest_logs(staff_id);



/*======================================================================
BUSINESS RULES

(Implemented in PHP)

1. Only ONE open harvest per Fish Batch.

2. Harvest must belong to the current Farm.

3. Fish Batch must be ACTIVE.

4. Pond must belong to selected Fish Batch.

5. Harvest cannot be deleted once Sales exist.

6. Harvest cannot be closed until all movements
   have been completed.

7. Every Sale automatically creates one
   HARVEST_MOVEMENT.

8. Staff Welfare creates one HARVEST_MOVEMENT.

9. Company Use creates one HARVEST_MOVEMENT.

10. Donation creates one HARVEST_MOVEMENT.

11. All operations execute inside a database
    transaction.

======================================================================*/



/*======================================================================
VERIFY TABLES
======================================================================*/

SHOW TABLES LIKE 'harvest%';



/*======================================================================
VERIFY STRUCTURES
======================================================================*/

DESCRIBE harvests;

DESCRIBE harvest_ponds;

DESCRIBE harvest_movements;

DESCRIBE harvest_logs;



/*======================================================================
EXPECTED TABLES

harvests

harvest_ponds

harvest_movements

harvest_logs

======================================================================*/



/*======================================================================
DEPLOYMENT ORDER

1. Import SQL

2. Verify Tables

3. Upload Harvest Module

4. Test Create Harvest

5. Test Harvest History

6. Test View Harvest

7. Test Close Harvest

8. Begin Sales Module

======================================================================*/



/*======================================================================
ROLLBACK

If deployment fails

ROLLBACK;

Otherwise

======================================================================*/

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;



/*======================================================================

END OF PACKAGE 005

Harvest Management Database

Version 1.0.0

======================================================================*/