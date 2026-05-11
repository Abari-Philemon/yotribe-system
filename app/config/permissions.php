<?php

return [
    'super_admin' => [
        'dashboard',
        'staff',
        'inventory',
        'sales',
        'cash',
        'hatchery',
        'production',
        'reports',
        'settings',
        'feed_store',
        'expenses',
        'finance'
    ],

    'owner' => [
        'dashboard',
        'inventory',
        'sales',
        'cash',
        'reports'
    ],

    'manager' => [
        'dashboard',
        'inventory',
        'sales',
        'production',
        'hatchery',
        'feeding'
    ],

    'storekeeper' => [
        'dashboard',
        'inventory'
    ],

    'hatchery' => [
        'dashboard',
        'hatchery'
    ],

    'production' => [
        'dashboard',
        'production',
        'feeding'
    ],

    'staff' => 
    ['super_admin', 'owner', 'production', 'hatchery', 'manager', 'storekeeper']

];
