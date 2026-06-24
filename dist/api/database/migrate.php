<?php

require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Disable foreign key checks first
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Get all table names in the database
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Drop all tables
    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        } catch (Exception $e) {
            // Continue even if a table can't be dropped
        }
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Execute schema creation
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Database schema reset and created successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to create database schema: ' . $e->getMessage()
    ]);
}
