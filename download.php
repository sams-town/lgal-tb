<?php
session_start();
require_once __DIR__ . '/config/database.php';

// 1. Verifikasi pengguna telah login
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    die("Akses Ditolak: Harap login terlebih dahulu.");
}

$user = $_SESSION['user'];
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'original';

if (!$documentId) {
    http_response_code(400);
    die("Error: Parameter ID dokumen salah.");
}

// 2. Ambil metadata dokumen dari database
$stmt = $pdo->prepare("SELECT * FROM approval_documents WHERE id = ?");
$stmt->execute([$documentId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die("Error: Dokumen tidak ditemukan.");
}

// 3. Otorisasi Keamanan: Mencegah akses lintas divisi
// Super Admin diizinkan mengakses semua dokumen divisi mana pun.
// Untuk peran lain, periksa apakah divisi pengguna cocok dengan divisi kepemilikan dokumen.
$userRoleName = $user['nama_role'] ?? $user['role'] ?? '';
$userDivisionId = $user['division_id'] ?? null;

if ($userRoleName !== 'Super Admin' && $userDivisionId !== null && (int)$userDivisionId !== (int)$doc['division_id']) {
    http_response_code(403);
    die("Akses Ditolak: Anda tidak memiliki otoritas untuk mengakses berkas milik divisi lain.");
}

// 4. Pilih berkas berdasarkan tipe request
$filePath = ($type === 'compressed') ? $doc['file_compressed'] : $doc['file_original'];

if (empty($filePath) || !file_exists($filePath)) {
    http_response_code(404);
    die("Error: Berkas fisik tidak ditemukan di sistem penyimpanan.");
}

// 5. Kirim file ke browser secara aman dengan header yang tepat
$fileName = basename($filePath);
$mimeType = mime_content_type($filePath);

// Menghindari eksploitasi mime sniffing
header("Content-Type: " . $mimeType);
header("Content-Disposition: inline; filename=\"" . addslashes($fileName) . "\"");
header("Content-Length: " . filesize($filePath));
header("Cache-Control: private, max-age=0, must-revalidate");
header("Pragma: public");

// Baca berkas secara langsung
readfile($filePath);
exit;
?>
