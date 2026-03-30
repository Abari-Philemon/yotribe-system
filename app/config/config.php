<?php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
    ? "https://"
    : "http://";

$host = $_SERVER['HTTP_HOST'];

define('BASE_URL', $protocol . $host . '/yotribe-system');
?>