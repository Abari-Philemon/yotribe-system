<?php
try {
    $pdo = new PDO(
        "mysql:host=192.168.1.187;dbname=yotribe_system;charset=utf8mb4",
        "devuser",
        "Judith1998."
    );
    echo "Connected successfully";
} catch (PDOException $e) {
    echo $e->getMessage();
}
