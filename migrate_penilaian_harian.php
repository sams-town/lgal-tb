<?php
require_once 'config/database.php';

$results = [];

try {
    // Cek apakah tabel sudah ada
    $check = $pdo->query("SHOW TABLES LIKE 'kpi_penilaian_harian'");
    if ($check->rowCount() > 0) {
        $results[] = ['status' => 'info', 'msg' => 'Tabel <b>kpi_penilaian_harian</b> sudah ada, tidak perlu dibuat ulang.'];
    } else {
        $pdo->exec("
            CREATE TABLE `kpi_penilaian_harian` (
                `id`           INT NOT NULL AUTO_INCREMENT,
                `karyawan_id`  INT NOT NULL,
                `kriteria_id`  INT NOT NULL,
                `hari`         TINYINT NOT NULL COMMENT '1-31',
                `bulan`        TINYINT NOT NULL COMMENT '1-12',
                `tahun`        SMALLINT NOT NULL,
                `nilai`        VARCHAR(10) DEFAULT NULL,
                `created_by`   VARCHAR(100) DEFAULT NULL,
                `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_karyawan_periode` (`karyawan_id`, `bulan`, `tahun`),
                KEY `idx_kriteria` (`kriteria_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $results[] = ['status' => 'success', 'msg' => 'Tabel <b>kpi_penilaian_harian</b> berhasil dibuat.'];
    }

    // Cek foreign key kpi_karyawan
    $checkKaryawan = $pdo->query("SHOW TABLES LIKE 'kpi_karyawan'");
    if ($checkKaryawan->rowCount() === 0) {
        $results[] = ['status' => 'warning', 'msg' => 'Tabel <b>kpi_karyawan</b> belum ada. Pastikan sudah ada sebelum menggunakan halaman ini.'];
    } else {
        $results[] = ['status' => 'success', 'msg' => 'Tabel <b>kpi_karyawan</b> ditemukan. ✓'];
    }

    // Cek foreign key kpi_kriteria
    $checkKriteria = $pdo->query("SHOW TABLES LIKE 'kpi_kriteria'");
    if ($checkKriteria->rowCount() === 0) {
        $results[] = ['status' => 'warning', 'msg' => 'Tabel <b>kpi_kriteria</b> belum ada. Pastikan sudah ada sebelum menggunakan halaman ini.'];
    } else {
        $results[] = ['status' => 'success', 'msg' => 'Tabel <b>kpi_kriteria</b> ditemukan. ✓'];
    }

} catch (PDOException $e) {
    $results[] = ['status' => 'error', 'msg' => 'Error: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrasi - kpi_penilaian_harian</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
        h2 { color: #0d9488; }
        .item { padding: 10px 16px; margin: 8px 0; border-radius: 8px; font-size: 14px; }
        .success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .note { margin-top: 24px; padding: 12px 16px; background: #fef9c3; border: 1px solid #fde047; border-radius: 8px; font-size: 13px; color: #713f12; }
        a { color: #0d9488; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Migrasi Database — kpi_penilaian_harian</h2>
    <?php foreach ($results as $r): ?>
    <div class="item <?= $r['status'] ?>"><?= $r['msg'] ?></div>
    <?php endforeach; ?>
    <div class="note">
        ⚠️ <strong>Penting:</strong> Hapus file ini dari server setelah migrasi selesai.<br>
        Lanjut ke halaman: <a href="sop_kpi_penilaian_harian.php">Penilaian Harian KPI</a>
    </div>
</body>
</html>
