<?php

require_once '../config/database.php';
require_once '../helpers/upload.php';

header('Content-Type: application/json');

try {
    $jenisSurat = $_POST['jenisSurat'] ?? '';
    $nomorSurat = $_POST['nomorSurat'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $perihal = $_POST['perihal'] ?? '';
    $pihakTerkait = $_POST['pihakTerkait'] ?? '';
    $statusDisposisi = $_POST['statusDisposisi'] ?? 'belum';
    
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
        'message' => 'Surat berhasil disimpan',
        'data' => [
            'jenisSurat' => $jenisSurat,
            'nomorSurat' => $nomorSurat,
            'tanggal' => $tanggal,
            'perihal' => $perihal,
            'pihakTerkait' => $pihakTerkait,
            'statusDisposisi' => $statusDisposisi,
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
