<?php

require_once '../config/database.php';
require_once '../helpers/upload.php';

header('Content-Type: application/json');

try {
    $judul = $_POST['judul'] ?? '';
    $nomorSOP = $_POST['nomorSOP'] ?? '';
    $unitKerja = $_POST['unitKerja'] ?? '';
    $tanggalTerbit = $_POST['tanggalTerbit'] ?? '';
    $tanggalExpired = $_POST['tanggalExpired'] ?? null;
    
    $filePath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = secureUpload($_FILES['file']);
        if ($uploadResult['success']) {
            $filePath = $uploadResult['file_path'];
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => $uploadResult['message']
            ]);
            exit;
        }
    }

    // For now, just return success - we'll add DB later
    echo json_encode([
        'status' => 'success',
        'message' => 'Dokumen SOP berhasil disimpan',
        'data' => [
            'judul' => $judul,
            'nomorSOP' => $nomorSOP,
            'unitKerja' => $unitKerja,
            'tanggalTerbit' => $tanggalTerbit,
            'tanggalExpired' => $tanggalExpired,
            'filePath' => $filePath
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
