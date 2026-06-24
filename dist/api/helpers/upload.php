<?php

function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function secureUpload($file, $uploadDir = null, $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'], $maxSize = 10485760) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload failed'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large (max 10MB)'];
    }

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    if ($uploadDir === null) {
        $uploadDir = __DIR__ . '/../uploads/';
    }

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = generateUUID() . '.' . $fileExt;
    $destination = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Calculate relative path from api/ directory
        $relativePath = str_replace(__DIR__ . '/../', '', $destination);
        return ['success' => true, 'file_path' => $relativePath, 'file_name' => $fileName];
    }

    return ['success' => false, 'message' => 'Failed to save file'];
}
