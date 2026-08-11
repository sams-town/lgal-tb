<?php
require_once 'config/database.php';

$cols = [
    'no_rkk'                 => "VARCHAR(100) DEFAULT NULL",
    'masa_berlaku_rkk_mulai' => "DATE DEFAULT NULL",
    'masa_berlaku_rkk_akhir' => "DATE DEFAULT NULL",
    'file_rkk'               => "VARCHAR(500) DEFAULT NULL",
];

$results = [];
foreach ($cols as $col => $def) {
    try {
        $exists = $pdo->query("SHOW COLUMNS FROM `tenaga_medis` LIKE '$col'")->rowCount();
        if ($exists == 0) {
            $pdo->exec("ALTER TABLE `tenaga_medis` ADD COLUMN `$col` $def");
            $results[] = ['status'=>'success', 'msg'=>"✅ Kolom <b>$col</b> berhasil ditambahkan."];
        } else {
            $results[] = ['status'=>'info', 'msg'=>"ℹ️ Kolom <b>$col</b> sudah ada."];
        }
    } catch (PDOException $e) {
        $results[] = ['status'=>'error', 'msg'=>"❌ Error pada <b>$col</b>: " . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Migrasi RKK Columns</title>
<style>
body{font-family:sans-serif;max-width:600px;margin:40px auto;padding:0 20px}
h2{color:#0d9488}
.item{padding:10px 16px;margin:8px 0;border-radius:8px;font-size:14px}
.success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.info   {background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
.error  {background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.note{margin-top:20px;padding:12px;background:#fef9c3;border:1px solid #fde047;border-radius:8px;font-size:13px;color:#713f12}
a{color:#0d9488;font-weight:bold}
</style>
</head>
<body>
<h2>Migrasi Kolom RKK</h2>
<?php foreach ($results as $r): ?>
<div class="item <?= $r['status'] ?>"><?= $r['msg'] ?></div>
<?php endforeach; ?>
<div class="note">⚠️ Hapus file ini setelah migrasi selesai.<br>
<a href="komite-medik.php">← Kembali ke Komite Medik</a></div>
</body>
</html>
