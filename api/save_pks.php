<?php

require_once 'config/database.php';
require_once 'helpers/upload.php';

header('Content-Type: application/json');

try {
    // Get input data for pengajuan
    $tanggal = $_POST['tanggal'] ?? '';
    $unitPengusul = $_POST['unitPengusul'] ?? '';
    $jenisKerjasama = $_POST['jenisKerjasama'] ?? '';
    $objekKerjasama = $_POST['objekKerjasama'] ?? '';
    $analisa = $_POST['analisa'] ?? '';
    $mitra = $_POST['mitra'] ?? '[]';
    $keunggulan = $_POST['keunggulan'] ?? '';
    $kekurangan = $_POST['kekurangan'] ?? '';
    $biaya = $_POST['biaya'] ?? '';
    $referensi = $_POST['referensi'] ?? '';
    $capaianMutu = $_POST['capaianMutu'] ?? '';

    // Validate required fields
    if (empty($tanggal) || empty($unitPengusul) || empty($jenisKerjasama) || empty($objekKerjasama) || empty($analisa)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Field dasar (Tanggal, Unit, Jenis, Objek, Analisa) harus diisi'
        ]);
        exit;
    }

    // Convert jenis_kerjasama to enum format
    $jenisKerjasamaDb = $jenisKerjasama === 'klinis' ? 'Klinis' : 'Non Klinis';
    $mitraJson = json_encode(json_decode($mitra, true) ?: []);

    // Insert into pengajuan_pks using prepared statement
    $stmt = $pdo->prepare("
        INSERT INTO pengajuan_pks 
        (tanggal, unit_pengusul, jenis_kerjasama, objek_kerjasama, analisa, mitra, keunggulan, kekurangan, biaya, referensi, capaian_mutu, status)
        VALUES 
        (:tanggal, :unit_pengusul, :jenis_kerjasama, :objek_kerjasama, :analisa, :mitra, :keunggulan, :kekurangan, :biaya, :referensi, :capaian_mutu, 'Draft')
    ");

    $stmt->bindParam(':tanggal', $tanggal);
    $stmt->bindParam(':unit_pengusul', $unitPengusul);
    $stmt->bindParam(':jenis_kerjasama', $jenisKerjasamaDb);
    $stmt->bindParam(':objek_kerjasama', $objekKerjasama);
    $stmt->bindParam(':analisa', $analisa);
    $stmt->bindParam(':mitra', $mitraJson);
    $stmt->bindParam(':keunggulan', $keunggulan);
    $stmt->bindParam(':kekurangan', $kekurangan);
    $stmt->bindParam(':biaya', $biaya);
    $stmt->bindParam(':referensi', $referensi);
    $stmt->bindParam(':capaian_mutu', $capaianMutu);

    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Pengajuan PKS berhasil disimpan',
        'data' => [
            'id' => $pdo->lastInsertId(),
            'tanggal' => $tanggal,
            'unitPengusul' => $unitPengusul,
            'jenisKerjasama' => $jenisKerjasamaDb,
            'objekKerjasama' => $objekKerjasama,
            'status' => 'Draft'
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
