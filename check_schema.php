<?php
require_once 'config/database.php';
$stmt = $pdo->query("DESCRIBE roles");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
