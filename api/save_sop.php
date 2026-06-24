<?php

require_once 'config/database.php';
require_once 'helpers/upload.php';

header('Content-Type: application/json');

try {
    // Get input data
    $judul = $_POST['judul'] ?? '';
    $nomorSOP = $_POST['nomorSOP'] ?? '';
    $unitKerja = $_POST['unitKerja'] ?? '';
    $tanggalTerbit = $_POST['tanggalTerbit'] ?? '';
    $tanggalExpired = !empty($_POST['tanggalExpired']) ? $_POST['tanggalExpired'] : null;

    // Validate input
    if (empty($judul) || empty($nomorSOP) || empty($unitKerja) || empty($tanggalTerbit)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Semua field wajib harus diisi'
        ]);
        exit;
    }

    // Handle file upload
    $filePath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/storage/sop/';
        $uploadResult = secureUpload($_FILES['file'], $uploadDir, ['pdf', 'doc', 'docx']);
        if (!$uploadResult['success']) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $uploadResult['message']
            ]);
            exit;
        }
        $filePath = $uploadResult['file_path'];
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'File SOP wajib diunggah'
        ]);
        exit;
    }

    // Insert into database using prepared statement
    $stmt = $pdo->prepare("
        INSERT INTO dokumen_sop (judul, nomor_sop, unit_kerja, tanggal_terbit, tanggal_expired, file_path)
        VALUES (:judul, :nomor_sop, :unit_kerja, :tanggal_terbit, :tanggal_expired, :file_path)
    ");

    $stmt->bindParam(':judul', $judul);
    $stmt->bindParam(':nomor_sop', $nomorSOP);
    $stmt->bindParam(':unit_kerja', $unitKerja);
    $stmt->bindParam(':tanggal_terbit', $tanggalTerbit);
    $stmt->bindParam(':tanggal_expired', $tanggalExpired);
    $stmt->bindParam(':file_path', $filePath);

    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Dokumen SOP berhasil disimpan',
        'data' => [
            'id' => $pdo->lastInsertId(),
            'judul' => $judul,
            'nomorSOP' => $nomorSOP,
            'unitKerja' => $unitKerja,
            'tanggalTerbit' => $tanggalTerbit,
            'tanggalExpired' => $tanggalExpired,
            'filePath' => $filePath
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
