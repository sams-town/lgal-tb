<?php
require_once 'config/database.php';

try {
    // Cek dulu apakah kolom sudah ada
    $check = $pdo->query("SHOW COLUMNS FROM `dokumen_perizinan` LIKE 'nomor_izin'");
    if ($check->rowCount() > 0) {
        echo "✅ Kolom <b>nomor_izin</b> sudah ada, tidak perlu migrasi.";
    } else {
        $pdo->exec("ALTER TABLE `dokumen_perizinan` ADD COLUMN `nomor_izin` varchar(255) DEFAULT NULL AFTER `nama_izin`");
        echo "✅ Berhasil menambahkan kolom <b>nomor_izin</b> ke tabel <b>dokumen_perizinan</b>.";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
