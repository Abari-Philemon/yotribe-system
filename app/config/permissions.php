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
        'hatchery'
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
        'production'
    ],

    'staff' => 
    ['super_admin', 'owner']

];
