<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user']) || !isAdmin()) {
    die('Akses ditolak. Login sebagai admin terlebih dahulu.');
}

$results = [];

$cols = [
    'kategori_bagian'       => "ALTER TABLE kpi_karyawan ADD COLUMN kategori_bagian VARCHAR(100) NULL AFTER jabatan",
    'atasan_langsung'       => "ALTER TABLE kpi_karyawan ADD COLUMN atasan_langsung VARCHAR(150) NULL AFTER kategori_bagian",
    'atasan_tidak_langsung' => "ALTER TABLE kpi_karyawan ADD COLUMN atasan_tidak_langsung VARCHAR(150) NULL AFTER atasan_langsung",
    'tanggal_bergabung'     => "ALTER TABLE kpi_karyawan ADD COLUMN tanggal_bergabung DATE NULL AFTER atasan_tidak_langsung",
    'user_id'               => "ALTER TABLE kpi_karyawan ADD COLUMN user_id INT NULL",
    'atasan_id'             => "ALTER TABLE kpi_karyawan ADD COLUMN atasan_id INT NULL",
];

foreach ($cols as $col => $sql) {
    try {
        $exists = $pdo->query("SHOW COLUMNS FROM kpi_karyawan LIKE '$col'")->rowCount();
        if ($exists == 0) {
            $pdo->exec($sql);
            $results[] = "✅ Kolom <strong>$col</strong> berhasil ditambahkan.";
        } else {
            $results[] = "⏭️ Kolom <strong>$col</strong> sudah ada, dilewati.";
        }
    } catch (Exception $e) {
        $results[] = "❌ Kolom <strong>$col</strong> gagal: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrate kpi_karyawan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-8">
    <div class="bg-white rounded-2xl shadow p-8 max-w-lg w-full space-y-4">
        <h1 class="text-xl font-bold text-gray-900">Migrasi Kolom kpi_karyawan</h1>
        <div class="space-y-2">
            <?php foreach ($results as $r): ?>
                <p class="text-sm text-gray-700"><?= $r ?></p>
            <?php endforeach; ?>
        </div>
        <a href="sop_kpi_karyawan.php" class="inline-block mt-4 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-medium hover:bg-teal-700">
            ← Kembali ke Data Karyawan
        </a>
    </div>
</body>
</html>
