<?php
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../middleware/authorize.php';

authorize('inventory');
