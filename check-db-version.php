<?php
require 'vendor/autoload.php';
require 'app/config/database.php';

$version = $pdo->query('SELECT VERSION()')->fetchColumn();
echo 'Database server version: ' . $version . PHP_EOL;
