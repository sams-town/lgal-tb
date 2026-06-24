<?php
require_once 'config/database.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS divisi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_divisi VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Successfully created divisi table.\n";
} catch (PDOException $e) {
    echo "Error creating divisi table: " . $e->getMessage() . "\n";
}
?>
