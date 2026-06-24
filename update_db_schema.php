<?php
require_once 'config/database.php';

try {
    // Add columns to users table
    $pdo->exec("ALTER TABLE users ADD COLUMN pin VARCHAR(255) NULL AFTER password, ADD COLUMN tanda_tangan VARCHAR(255) NULL AFTER pin;");
    echo "Successfully altered users table.\n";
} catch (PDOException $e) {
    echo "Error altering users table (or columns already exist): " . $e->getMessage() . "\n";
}

try {
    // Create system_integrations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_integrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_name VARCHAR(100) NOT NULL UNIQUE,
            api_key VARCHAR(255) NOT NULL,
            api_secret VARCHAR(255) NULL,
            endpoint_url VARCHAR(255) NULL,
            status ENUM('Aktif', 'Nonaktif') DEFAULT 'Aktif',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Successfully created system_integrations table.\n";

    // Check if empty, then insert dummy data
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_integrations");
    if (false && $stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO system_integrations (provider_name, api_key, endpoint_url) VALUES
            ('WhatsApp Gateway', 'YOUR_WA_API_KEY', 'https://api.fonnte.com/send'),
            ('PrivyID E-Sign', 'YOUR_PRIVY_API_KEY', 'https://api.privy.id/');
        ");
        echo "Successfully inserted dummy data into system_integrations.\n";
    }

} catch (PDOException $e) {
    echo "Error creating system_integrations table: " . $e->getMessage() . "\n";
}
?>
