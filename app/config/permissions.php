<?php

/**
 * =========================================================
 * SYSTEM ROLE PERMISSIONS
 * =========================================================
 *
 * Each role can access only the listed modules and
 * dashboard features.
 *
 * MODULE KEYS:
 *
 * dashboard
 * staff
 * stocking
 * batches
 * ponds
 * mortality
 * growth
 * feeding
 * feed_store
 * hatchery
 * maggot
 * finance
 * reports
 * water
 * harvest
 * sales
 *
 * DASHBOARD FEATURE KEYS:
 *
 * analytics
 * intelligence
 * farm_health
 * notifications
 * quick_actions
 *
 */

return [

    /**
     * =====================================================
     * SUPER ADMIN
     * FULL SYSTEM ACCESS
     * =====================================================
     */
    'super_admin' => [

        'dashboard',

        // Dashboard Features
        'analytics',
        'intelligence',
        'farm_health',
        'notifications',
        'quick_actions',

        // System Modules
        'staff',
        'stocking',
        'batches',
        'ponds',
        'mortality',
        'growth',
        'feeding',
        'feed_store',
        'hatchery',
        'maggot',
        'finance',
        'reports',
        'water',
        'harvest',
        'sales'

    ],


    /**
     * =====================================================
     * OWNER
     * FULL FARM ACCESS
     * =====================================================
     */
    'owner' => [

        'dashboard',

        // Dashboard Features
        'analytics',
        'intelligence',
        'farm_health',
        'notifications',
        'quick_actions',

        // System Modules
        'staff',
        'stocking',
        'batches',
        'ponds',
        'mortality',
        'growth',
        'feeding',
        'feed_store',
        'hatchery',
        'maggot',
        'finance',
        'reports',
        'water',
        'harvest',
        'sales'

    ],


    /**
     * =====================================================
     * MANAGER
     * OPERATIONS + REPORTS
     * =====================================================
     */
    'manager' => [

        'dashboard',

        // Dashboard Features
        'analytics',
        'intelligence',
        'farm_health',
        'notifications',
        'quick_actions',

        // System Modules
        'batches',
        'stocking',
        'ponds',
        'mortality',
        'growth',
        'feeding',
        'feed_store',
        'hatchery',
        'maggot',
        'reports',
        'water',
        'harvest'

    ],


    /**
     * =====================================================
     * STOREKEEPER
     * FEED STORE + FEEDING
     * =====================================================
     */
    'storekeeper' => [

        'dashboard',

        // Dashboard Features
        'analytics',
        'farm_health',
        'notifications',
        'quick_actions',

        // System Modules
        'feed_store',
        'feeding',
        'reports'

    ],


    /**
     * =====================================================
     * HATCHERY STAFF
     * HATCHERY OPERATIONS
     * =====================================================
     */
    'hatchery' => [

        'dashboard',

        // Dashboard Features
        'analytics',
        'farm_health',
        'notifications',
        'quick_actions',

        // System Modules
        'hatchery',
        'stocking',
        'growth',
        'reports'

    ],


    /**
     * =====================================================
     * PRODUCTION STAFF
     * DAILY FARM OPERATIONS
     * =====================================================
     */
    'production' => [

        'dashboard',

        // Dashboard Features
        'analytics',
        'farm_health',
        'notifications',
        'quick_actions',

        // System Modules
        'feeding',
        'growth',
        'mortality',
        'reports',
        'water',
        'harvest'

    ]

];