<?php
// FILE DEBUG SEMENTARA — HAPUS SETELAH SELESAI
session_start();
require_once 'config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG RKK TABLES ===\n\n";

// 1. Test koneksi
echo "1. Koneksi DB: OK\n\n";

// 2. Cek tabel kpi_rkk_template
try {
    $r = $pdo->query("SHOW TABLES LIKE 'kpi_rkk_template'")->rowCount();
    echo "2. Tabel kpi_rkk_template: " . ($r > 0 ? "ADA" : "TIDAK ADA") . "\n";
} catch (Exception $e) { echo "2. ERROR: " . $e->getMessage() . "\n"; }

// 3. Cek tabel kpi_rkk_tugas
try {
    $r = $pdo->query("SHOW TABLES LIKE 'kpi_rkk_tugas'")->rowCount();
    echo "3. Tabel kpi_rkk_tugas: " . ($r > 0 ? "ADA" : "TIDAK ADA") . "\n";
} catch (Exception $e) { echo "3. ERROR: " . $e->getMessage() . "\n"; }

// 4. Cek kolom kpi_rkk_tugas
try {
    $cols = $pdo->query("SHOW COLUMNS FROM kpi_rkk_tugas")->fetchAll(PDO::FETCH_ASSOC);
    echo "4. Kolom kpi_rkk_tugas:\n";
    foreach ($cols as $c) echo "   - " . $c['Field'] . " (" . $c['Type'] . ")\n";
} catch (Exception $e) { echo "4. ERROR: " . $e->getMessage() . "\n"; }

// 5. Coba SELECT
try {
    $r = $pdo->query("SELECT COUNT(*) FROM kpi_rkk_tugas")->fetchColumn();
    echo "\n5. Jumlah data kpi_rkk_tugas: $r\n";
} catch (Exception $e) { echo "\n5. ERROR SELECT: " . $e->getMessage() . "\n"; }

// 6. Coba JOIN query
try {
    $r = $pdo->query("
        SELECT t.*, COUNT(r.id) as jumlah_tugas
        FROM kpi_rkk_template t
        LEFT JOIN kpi_rkk_tugas r ON t.id = r.template_id
        GROUP BY t.id
    ")->fetchAll();
    echo "\n6. JOIN query: OK, " . count($r) . " template ditemukan\n";
    foreach ($r as $row) echo "   - [{$row['id']}] {$row['jabatan']} ({$row['jumlah_tugas']} tugas)\n";
} catch (Exception $e) { echo "\n6. ERROR JOIN: " . $e->getMessage() . "\n"; }

// 7. Test ?open=6
try {
    $s = $pdo->prepare("SELECT * FROM kpi_rkk_template WHERE id=?");
    $s->execute([6]);
    $d = $s->fetch();
    echo "\n7. Template id=6: " . ($d ? $d['jabatan'] : "TIDAK DITEMUKAN") . "\n";

    $s2 = $pdo->prepare("SELECT * FROM kpi_rkk_tugas WHERE template_id=? ORDER BY id ASC");
    $s2->execute([6]);
    $tugas = $s2->fetchAll();
    echo "   Tugas: " . count($tugas) . " item\n";
} catch (Exception $e) { echo "\n7. ERROR open=6: " . $e->getMessage() . "\n"; }

echo "\n=== SELESAI ===\n";
echo "\nHAPUS FILE INI (debug_rkk.php) SETELAH SELESAI!\n";
?>
