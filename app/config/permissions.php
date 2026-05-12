<?php

/**
 * =========================================================
 * SYSTEM ROLE PERMISSIONS
 * =========================================================
 *
 * Each role can access only the listed modules.
 *
 * Module Keys:
 *
 * dashboard
 * staff
 * stocking
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
        'staff',
        'stocking',
        'ponds',
        'mortality',
        'growth',
        'feeding',
        'feed_store',
        'hatchery',
        'maggot',
        'finance',
        'reports',
        'water'

    ],


    /**
     * =====================================================
     * OWNER
     * FULL FARM ACCESS
     * =====================================================
     */
    'owner' => [

        'dashboard',
        'staff',
        'stocking',
        'ponds',
        'mortality',
        'growth',
        'feeding',
        'feed_store',
        'hatchery',
        'maggot',
        'finance',
        'reports',
        'water'

    ],


    /**
     * =====================================================
     * MANAGER
     * OPERATIONS + REPORTS
     * =====================================================
     */
    'manager' => [

        'dashboard',
        'stocking',
        'ponds',
        'mortality',
        'growth',
        'feeding',
        'feed_store',
        'hatchery',
        'maggot',
        'reports',
        'water'

    ],


    /**
     * =====================================================
     * STOREKEEPER
     * FEED STORE + FEEDING
     * =====================================================
     */
    'storekeeper' => [

        'dashboard',
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
        'feeding',
        'growth',
        'mortality',
        'ponds',
        'reports',
        'water'

    ]

];