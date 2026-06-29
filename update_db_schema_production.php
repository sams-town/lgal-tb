<?php
require_once __DIR__ . '/config/database.php';

echo "Memulai migrasi database produksi aman (tanpa hapus data)...\n\n";

try {
    // 1. Konversi struktur tabel tenaga_medis secara aman
    $stmt = $pdo->query("SHOW COLUMNS FROM tenaga_medis LIKE 'nama_medis'");
    if ($stmt->rowCount() > 0) {
        echo "• Mengonversi kolom lama pada tabel 'tenaga_medis'...\n";
        
        // Ubah nama_medis ke nama_lengkap
        $pdo->exec("ALTER TABLE tenaga_medis CHANGE COLUMN nama_medis nama_lengkap VARCHAR(255) NOT NULL");
        
        // Ubah spesialisasi ke spesialis
        $pdo->exec("ALTER TABLE tenaga_medis CHANGE COLUMN spesialisasi spesialis VARCHAR(255) NOT NULL");
        
        // Ubah tanggal_kadaluarsa ke masa_berlaku_sk_akhir
        $pdo->exec("ALTER TABLE tenaga_medis CHANGE COLUMN tanggal_kadaluarsa masa_berlaku_sk_akhir DATE NOT NULL");
        
        // Ubah file_path ke file_sk
        $pdo->exec("ALTER TABLE tenaga_medis CHANGE COLUMN file_path file_sk VARCHAR(255) NULL");
        
        echo "✓ Konversi kolom lama selesai.\n";
    }

    // Tambahkan kolom baru yang belum ada pada tenaga_medis
    $newCols = [
        'unit_ruangan' => "VARCHAR(100) NULL AFTER nama_lengkap",
        'status_kepegawaian' => "VARCHAR(50) NOT NULL DEFAULT 'Tetap' AFTER unit_ruangan",
        'tipe_form' => "VARCHAR(50) NOT NULL DEFAULT 'komite-medik' AFTER status_kepegawaian",
        'no_str' => "VARCHAR(255) NULL AFTER tipe_form",
        'masa_berlaku_str_mulai' => "DATE NULL AFTER no_str",
        'masa_berlaku_str_akhir' => "DATE NULL AFTER masa_berlaku_str_mulai",
        'file_str' => "VARCHAR(255) NULL AFTER masa_berlaku_str_akhir",
        'no_sip' => "VARCHAR(255) NULL AFTER file_str",
        'masa_berlaku_sip_mulai' => "DATE NULL AFTER no_sip",
        'masa_berlaku_sip_akhir' => "DATE NULL AFTER masa_berlaku_sip_mulai",
        'file_sip' => "VARCHAR(255) NULL AFTER masa_berlaku_sip_akhir",
        'no_pks' => "VARCHAR(255) NULL AFTER file_sip",
        'masa_berlaku_pks_mulai' => "DATE NULL AFTER no_pks",
        'masa_berlaku_pks_akhir' => "DATE NULL AFTER masa_berlaku_pks_mulai",
        'file_pks' => "VARCHAR(255) NULL AFTER masa_berlaku_pks_akhir",
        'no_sk' => "VARCHAR(255) NULL AFTER file_pks",
        'masa_berlaku_sk_mulai' => "DATE NULL AFTER no_sk",
        'masa_berlaku_sk_akhir' => "DATE NULL AFTER masa_berlaku_sk_mulai",
        'kompetensi_klinis' => "TEXT NULL AFTER file_sk",
        'sertifikasi_kompetensi' => "JSON NULL AFTER kompetensi_klinis",
        'jabatan_keperawatan' => "VARCHAR(100) NULL AFTER sertifikasi_kompetensi",
        'nomor_pkwt' => "VARCHAR(255) NULL AFTER spesialis",
        'rincian_kewenangan_klinis' => "TEXT NULL AFTER nomor_pkwt",
        'lantai' => "VARCHAR(10) NULL AFTER rincian_kewenangan_klinis",
        'nomor_keputusan_direktur' => "VARCHAR(255) NULL AFTER lantai"
    ];

    foreach ($newCols as $col => $definition) {
        $stmt = $pdo->query("SHOW COLUMNS FROM tenaga_medis LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE tenaga_medis ADD COLUMN $col $definition");
            echo "✓ Kolom '$col' ditambahkan ke tabel 'tenaga_medis'.\n";
        }
    }

    // 2. Tambah no_hp dan division_id ke users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'no_hp'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN no_hp VARCHAR(50) NULL AFTER password");
        echo "✓ Kolom 'no_hp' ditambahkan ke tabel 'users'.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'division_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN division_id INT NULL AFTER role_id");
        // Hapus constraint jika sudah ada sebelum menambahkan
        try {
            $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_divisi FOREIGN KEY (division_id) REFERENCES divisi(id) ON DELETE SET NULL ON UPDATE CASCADE");
            echo "✓ Kolom 'division_id' dan foreign key ditambahkan ke tabel 'users'.\n";
        } catch (PDOException $ex) {
            echo "✓ Kolom 'division_id' ditambahkan (tanpa constraint ulang).\n";
        }
    }

    // 3. Tambah kolom file ke approval_documents
    $stmt = $pdo->query("SHOW COLUMNS FROM approval_documents LIKE 'file_original'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE approval_documents ADD COLUMN file_original VARCHAR(255) NULL AFTER step_status");
        echo "✓ Kolom 'file_original' ditambahkan ke tabel 'approval_documents'.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM approval_documents LIKE 'file_compressed'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE approval_documents ADD COLUMN file_compressed VARCHAR(255) NULL AFTER file_original");
        echo "✓ Kolom 'file_compressed' ditambahkan ke tabel 'approval_documents'.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM approval_documents LIKE 'division_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE approval_documents ADD COLUMN division_id INT NULL AFTER file_compressed");
        try {
            $pdo->exec("ALTER TABLE approval_documents ADD CONSTRAINT fk_approval_docs_divisi FOREIGN KEY (division_id) REFERENCES divisi(id) ON DELETE SET NULL ON UPDATE CASCADE");
            echo "✓ Kolom 'division_id' ditambahkan ke tabel 'approval_documents'.\n";
        } catch (PDOException $ex) {
            echo "✓ Kolom 'division_id' ditambahkan (tanpa constraint ulang).\n";
        }
    }

    // 4. Buat tabel document_logs
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

    // 5. Tambah WhatsApp Gateway ke system_integrations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_integrations WHERE provider_name = ?");
    $stmt->execute(['WhatsApp Gateway']);
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO system_integrations (provider_name, api_key, endpoint_url, status) 
            VALUES ('WhatsApp Gateway', 'YOUR_FONNTE_API_KEY', 'https://api.fonnte.com/send', 'Aktif')
        ");
        echo "✓ Konfigurasi 'WhatsApp Gateway' ditambahkan ke tabel 'system_integrations'.\n";
    }

    echo "\nMigrasi database produksi selesai dengan sukses!\n";

} catch (PDOException $e) {
    echo "\nGagal melakukan migrasi database produksi: " . $e->getMessage() . "\n";
}
?>
