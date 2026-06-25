<?php
require_once __DIR__ . '/config/database.php';

echo "Memulai migrasi database...\n\n";

try {
    // 1. Tambah no_hp ke users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'no_hp'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN no_hp VARCHAR(50) NULL AFTER password");
        echo "✓ Kolom 'no_hp' berhasil ditambahkan ke tabel 'users'.\n";
    } else {
        echo "• Kolom 'no_hp' sudah ada di tabel 'users'.\n";
    }

    // 2. Tambah division_id ke users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'division_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN division_id INT NULL AFTER role_id");
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_divisi FOREIGN KEY (division_id) REFERENCES divisi(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "✓ Kolom 'division_id' dan foreign key berhasil ditambahkan ke tabel 'users'.\n";
    } else {
        echo "• Kolom 'division_id' sudah ada di tabel 'users'.\n";
    }

    // 3. Tambah file_original ke approval_documents
    $stmt = $pdo->query("SHOW COLUMNS FROM approval_documents LIKE 'file_original'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE approval_documents ADD COLUMN file_original VARCHAR(255) NULL AFTER step_status");
        echo "✓ Kolom 'file_original' berhasil ditambahkan ke tabel 'approval_documents'.\n";
    } else {
        echo "• Kolom 'file_original' sudah ada di tabel 'approval_documents'.\n";
    }

    // 4. Tambah file_compressed ke approval_documents
    $stmt = $pdo->query("SHOW COLUMNS FROM approval_documents LIKE 'file_compressed'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE approval_documents ADD COLUMN file_compressed VARCHAR(255) NULL AFTER file_original");
        echo "✓ Kolom 'file_compressed' berhasil ditambahkan ke tabel 'approval_documents'.\n";
    } else {
        echo "• Kolom 'file_compressed' sudah ada di tabel 'approval_documents'.\n";
    }

    // 5. Tambah division_id ke approval_documents (untuk segmentasi divisi)
    $stmt = $pdo->query("SHOW COLUMNS FROM approval_documents LIKE 'division_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE approval_documents ADD COLUMN division_id INT NULL AFTER file_compressed");
        $pdo->exec("ALTER TABLE approval_documents ADD CONSTRAINT fk_approval_docs_divisi FOREIGN KEY (division_id) REFERENCES divisi(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "✓ Kolom 'division_id' berhasil ditambahkan ke tabel 'approval_documents'.\n";
    } else {
        echo "• Kolom 'division_id' sudah ada di tabel 'approval_documents'.\n";
    }

    // 6. Buat tabel document_logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            user_id INT NULL,
            aksi VARCHAR(100) NOT NULL,
            detail TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (document_id) REFERENCES approval_documents(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Tabel 'document_logs' siap/berhasil dibuat.\n";

    // 7. Insert data default WhatsApp Gateway ke system_integrations jika belum ada
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_integrations WHERE provider_name = ?");
    $stmt->execute(['WhatsApp Gateway']);
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO system_integrations (provider_name, api_key, endpoint_url, status) 
            VALUES ('WhatsApp Gateway', 'YOUR_FONNTE_API_KEY', 'https://api.fonnte.com/send', 'Aktif')
        ");
        echo "✓ Konfigurasi default 'WhatsApp Gateway' ditambahkan ke tabel 'system_integrations'.\n";
    } else {
        echo "• Konfigurasi 'WhatsApp Gateway' sudah ada di tabel 'system_integrations'.\n";
    }

    // 8. Tambahkan no_hp dummy ke user admin untuk kemudahan pengujian
    $pdo->exec("UPDATE users SET no_hp = '08123456789' WHERE id = 1 AND (no_hp IS NULL OR no_hp = '')");
    echo "✓ Set data dummy 'no_hp' ke Super Admin untuk kemudahan testing.\n";

    echo "\nMigrasi database selesai dengan sukses!\n";

} catch (PDOException $e) {
    echo "\nGagal melakukan migrasi database: " . $e->getMessage() . "\n";
}
?>
