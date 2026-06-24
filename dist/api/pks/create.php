<?php

require_once '../config/database.php';
require_once '../helpers/upload.php';

header('Content-Type: application/json');

try {
    $namaMitra = $_POST['namaMitra'] ?? '';
    $perihal = $_POST['perihal'] ?? '';
    $tanggalMulai = $_POST['tanggalMulai'] ?? '';
    $tanggalAkhir = $_POST['tanggalAkhir'] ?? '';
    $status = $_POST['status'] ?? 'aktif';
    
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
        'message' => 'Kontrak PKS berhasil disimpan',
        'data' => [
            'namaMitra' => $namaMitra,
            'perihal' => $perihal,
            'tanggalMulai' => $tanggalMulai,
            'tanggalAkhir' => $tanggalAkhir,
            'status' => $status,
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
