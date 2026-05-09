<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '0183304fh');
    $pdo->exec('CREATE DATABASE IF NOT EXISTS Umrah_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "Database created successfully\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}