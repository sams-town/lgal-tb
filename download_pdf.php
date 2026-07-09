<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    die("Akses Ditolak: Harap login terlebih dahulu.");
}

$file = $_GET['file'] ?? '';
if (empty($file) || strpos($file, 'uploads/') !== 0 || strpos($file, '..') !== false) {
    http_response_code(400);
    die("Error: Parameter file salah atau tidak valid.");
}

$filePath = __DIR__ . '/' . $file;
if (!file_exists($filePath)) {
    http_response_code(404);
    die("Error: File tidak ditemukan.");
}

$mimeType = mime_content_type($filePath);
header("Content-Type: " . $mimeType);
header("Content-Disposition: attachment; filename=\"" . basename($filePath) . "\"");
header("Content-Length: " . filesize($filePath));
header("Cache-Control: private, max-age=0, must-revalidate");
header("Pragma: public");

readfile($filePath);
exit;
?>
