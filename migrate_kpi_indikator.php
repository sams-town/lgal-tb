<?php
require_once 'config/database.php';

$results = [];

// Cek apakah tabel kpi_kriteria ada
try {
    $pdo->query("SELECT 1 FROM kpi_kriteria LIMIT 1");
} catch (PDOException $e) {
    die("❌ Tabel kpi_kriteria belum ada. Buka halaman utama dulu agar auto-create berjalan.");
}

// Cek apakah sudah ada data
$existing = (int)$pdo->query("SELECT COUNT(*) FROM kpi_kriteria")->fetchColumn();
if ($existing > 0) {
    // Tanya konfirmasi via GET param
    if (!isset($_GET['force'])) {
        echo '<div style="font-family:sans-serif;max-width:600px;margin:40px auto;padding:0 20px">';
        echo '<h2 style="color:#0d9488">Migrasi Indikator KPI</h2>';
        echo '<div style="background:#fffbeb;border:1px solid #fde68a;padding:12px 16px;border-radius:8px;color:#92400e;margin-bottom:16px">';
        echo "⚠️ Sudah ada <strong>{$existing} indikator</strong> di database.<br>";
        echo 'Lanjutkan akan <strong>menghapus semua data lama</strong> dan menggantinya dengan 19 indikator baru.';
        echo '</div>';
        echo '<a href="?force=1" style="background:#0d9488;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold">✅ Ya, Ganti Sekarang</a> &nbsp;';
        echo '<a href="sop_kpi_kriteria.php" style="background:#e2e8f0;color:#334155;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold">Batal</a>';
        echo '</div>';
        exit;
    }
    // Force: hapus data lama
    $pdo->exec("DELETE FROM kpi_kriteria");
    $results[] = ['status'=>'info', 'msg'=>"Data lama ({$existing} indikator) dihapus."];
}

// 19 Indikator sesuai referensi
// Kolom: kategori, nama_indikator, deskripsi, bobot (float/decimal)
// Bobot total = 100%:
// ATTITUDE   : 3 × 5.3% + 1 × 2.1% = 5.3+5.3+2.1 = 12.7% (namun kita pakai nilai asli dari gambar)
// Nilai bobot dari gambar (dibulatkan ke 1 desimal)
$indikator = [
    // ATTITUDE
    ['ATTITUDE', 'Kehadiran',                    'Kehadiran karyawan dalam bekerja',                  5.3],
    ['ATTITUDE', 'Penampilan',                   'Penampilan dan kerapian saat bertugas',              5.3],
    ['ATTITUDE', 'Caring',                       'Sikap caring terhadap pasien dan rekan kerja',       2.1],

    // KNOWLEDGE
    ['KNOWLEDGE', 'Kehadiran Kelas Edukasi',     'Kehadiran dalam kelas edukasi / pelatihan',          3.2],
    ['KNOWLEDGE', 'Pemahaman SPO',               'Pemahaman dan penerapan Standar Prosedur Operasional', 3.2],
    ['KNOWLEDGE', 'Melakukan Coaching/Mentoring','Aktif melakukan coaching atau mentoring',             5.3],
    ['KNOWLEDGE', 'Product Knowledge',           'Penguasaan product knowledge rumah sakit',           3.2],

    // SKILL
    ['SKILL', 'Kesesuaian RKK/Job Des',          'Otomatis dari Log RKK/Job Des',                     21.1],
    ['SKILL', 'Kemampuan Coaching/Leadership',   'Kemampuan memimpin dan melatih tim',                 5.3],
    ['SKILL', 'Kemampuan Handling Komplain',     'Kemampuan menangani keluhan pasien/keluarga',        5.3],
    ['SKILL', 'Pelaksanaan Pelaporan',           'Ketepatan dan kelengkapan pelaporan',                5.3],

    // KOMPLAIN
    ['KOMPLAIN', 'Keterkaitan YBS dengan Komplain Karyawan', 'Keterlibatan dalam komplain internal',   5.3],
    ['KOMPLAIN', 'Teguran Lisan/Tertulis',       'Tidak ada teguran lisan maupun tertulis',            5.3],
    ['KOMPLAIN', 'Adanya Keluhan dari Unit Lain','Tidak ada keluhan dari unit lain',                   2.1],
    ['KOMPLAIN', 'Terjadinya Insiden karena Kelalaian', 'Tidak terjadi insiden akibat kelalaian',      5.3],

    // REWARD
    ['REWARD', 'Pujian Lisan/Tertulis',          'Mendapat pujian lisan atau tertulis',                5.3],
    ['REWARD', 'Tidak Ada Komplain',             'Tidak ada komplain dalam periode ini',               5.3],
    ['REWARD', 'Memberikan Ide dan Inovasi',     'Aktif memberikan ide atau inovasi',                  5.3],
    ['REWARD', 'Loyalitas',                      'Loyalitas terhadap institusi',                       2.1],
];

try {
    $stmt = $pdo->prepare("INSERT INTO kpi_kriteria (kategori, nama_indikator, deskripsi, bobot) VALUES (?, ?, ?, ?)");
    foreach ($indikator as $row) {
        $stmt->execute($row);
    }
    $total = array_sum(array_column($indikator, 3));
    $results[] = ['status'=>'success', 'msg'=>'✅ Berhasil memasukkan <strong>'.count($indikator).' indikator</strong> KPI.'];
    $results[] = ['status'=>'info',    'msg'=>'📊 Total bobot: <strong>'.number_format($total,1).'%</strong>'];
} catch (PDOException $e) {
    $results[] = ['status'=>'error', 'msg'=>'❌ Error: '.$e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrasi Indikator KPI</title>
    <style>
        body{font-family:sans-serif;max-width:600px;margin:40px auto;padding:0 20px}
        h2{color:#0d9488}
        .item{padding:10px 16px;margin:8px 0;border-radius:8px;font-size:14px}
        .success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
        .info   {background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
        .warning{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
        .error  {background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        .note{margin-top:24px;padding:12px 16px;background:#fef9c3;border:1px solid #fde047;border-radius:8px;font-size:13px;color:#713f12}
        a{color:#0d9488;font-weight:bold}
    </style>
</head>
<body>
    <h2>Migrasi Indikator KPI</h2>
    <?php foreach ($results as $r): ?>
    <div class="item <?= $r['status'] ?>"><?= $r['msg'] ?></div>
    <?php endforeach; ?>
    <div class="note">
        ⚠️ <strong>Hapus file ini setelah migrasi selesai.</strong><br><br>
        Lanjut ke:
        <a href="sop_kpi_kriteria.php">Lihat Kriteria Penilaian</a> &nbsp;|&nbsp;
        <a href="sop_kpi_penilaian_harian.php">Penilaian Harian</a>
    </div>
</body>
</html>
