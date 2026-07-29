<?php
require_once 'config/database.php';

try {
    $check = $pdo->query("SHOW COLUMNS FROM `tenaga_medis` LIKE 'jenjang_keperawatan'");
    if ($check->rowCount() > 0) {
        echo "✅ Kolom <b>jenjang_keperawatan</b> sudah ada, tidak perlu migrasi.";
    } else {
        $pdo->exec("ALTER TABLE `tenaga_medis` ADD COLUMN `jenjang_keperawatan` varchar(20) DEFAULT NULL AFTER `lantai`");
        echo "✅ Berhasil menambahkan kolom <b>jenjang_keperawatan</b> ke tabel <b>tenaga_medis</b>.";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
