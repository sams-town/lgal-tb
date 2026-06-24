<?php
require 'config/database.php';
$stmt = $pdo->query('SHOW TABLES');
while($row = $stmt->fetch(PDO::FETCH_NUM)){
    $table = $row[0];
    if ($table != 'users' && $table != 'roles') {
        try {
            $pdo->exec('DELETE FROM ' . $table);
        } catch(Exception $e) {
            echo "Error deleting from $table: " . $e->getMessage() . "\n";
        }
    }
}
echo "Cleaned all tables except users and roles.\n";
