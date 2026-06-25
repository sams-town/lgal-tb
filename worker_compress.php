<?php
if (php_sapi_name() !== 'cli') {
    die("Hanya dapat dijalankan lewat CLI.\n");
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/DocumentCompressor.php';
require_once __DIR__ . '/includes/WhatsAppService.php';

$documentId = isset($argv[1]) ? (int)$argv[1] : 0;
$targetStep = isset($argv[2]) ? $argv[2] : null;

if (!$documentId) {
    die("Error: ID dokumen tidak diberikan.\n");
}

// Jika memicu notifikasi untuk step spesifik
if ($targetStep) {
    echo "Memicu notifikasi WhatsApp untuk langkah '{$targetStep}' pada dokumen ID: {$documentId}...\n";
    triggerStepNotification($documentId, $targetStep);
    exit;
}

echo "Memulai kompresi untuk dokumen ID: {$documentId}...\n";

try {
    // 1. Ambil data dokumen
    $stmt = $pdo->prepare("SELECT * FROM approval_documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $doc = $stmt->fetch();

    if (!$doc) {
        die("Error: Dokumen dengan ID {$documentId} tidak ditemukan.\n");
    }

    $input = $doc['file_original'];
    if (empty($input) || !file_exists($input)) {
        die("Error: Berkas asli tidak ditemukan di path: '{$input}'.\n");
    }

    // Tentukan path terkompresi
    $fileName = basename($input);
    $outputDir = __DIR__ . '/uploads/compressed/';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    $outputPath = $outputDir . $fileName;

    $extension = strtolower(pathinfo($input, PATHINFO_EXTENSION));
    $success = false;

    // Lakukan kompresi
    if ($extension === 'pdf') {
        $success = DocumentCompressor::compressPdf($input, $outputPath);
    } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
        $success = DocumentCompressor::compressImage($input, $outputPath, 75);
    }

    $finalPath = $success ? $outputPath : $input;
    $statusText = $success ? 'Compress Success' : 'Compress Fallback (Original Used)';
    $detailText = $success 
        ? "Original: " . filesize($input) . " bytes. Compressed: " . filesize($outputPath) . " bytes."
        : "Kompresi gagal/tidak didukung, berkas asli digunakan.";

    // 2. Update database dengan path terkompresi
    $stmtUpdate = $pdo->prepare("UPDATE approval_documents SET file_compressed = ? WHERE id = ?");
    $stmtUpdate->execute([$finalPath, $documentId]);

    // 3. Catat ke log
    $stmtLog = $pdo->prepare("INSERT INTO document_logs (document_id, aksi, detail) VALUES (?, ?, ?)");
    $stmtLog->execute([$documentId, $statusText, $detailText]);

    echo "Status: {$statusText}.\n";

    // 4. Picu WhatsApp notifikasi untuk step pertama ('km' - Komite Medik / Super Admin)
    triggerStepNotification($documentId, 'km');

} catch (Exception $e) {
    echo "Terjadi error: " . $e->getMessage() . "\n";
    error_log("Error di worker_compress.php: " . $e->getMessage());
}

/**
 * Fungsi untuk mengirim notifikasi WhatsApp kepada user dengan role tertentu pada step alur
 */
function triggerStepNotification($documentId, $stepId) {
    global $pdo;

    // Konfigurasi step statis
    $stepConfig = [
        'km' => ['name' => 'Komite Medik (KM)', 'role' => 'Super Admin', 'action' => 'Review'],
        'lg' => ['name' => 'Legal (LG)', 'role' => 'Staf Legal', 'action' => 'Tanda Tangan'],
        'sk' => ['name' => 'Sekretariat (SK)', 'role' => 'Staf Sekretariat', 'action' => 'Review'],
        'dk' => ['name' => 'Direktur Keuangan (DK)', 'role' => 'Direktur Keuangan', 'action' => 'Tanda Tangan'],
        'du' => ['name' => 'Direktur Utama (DU)', 'role' => 'Direktur Utama', 'action' => 'Tanda Tangan']
    ];

    if (!isset($stepConfig[$stepId])) return;

    $config = $stepConfig[$stepId];
    $targetRoleName = $config['role'];
    $actionName = $config['action'];

    // Ambil data dokumen
    $stmtDoc = $pdo->prepare("SELECT name, proposer, division_id FROM approval_documents WHERE id = ?");
    $stmtDoc->execute([$documentId]);
    $doc = $stmtDoc->fetch();
    if (!$doc) return;

    // Ambil nama divisi pengusul jika ada
    $divisiName = 'Umum';
    if ($doc['division_id']) {
        $stmtDiv = $pdo->prepare("SELECT nama_divisi FROM divisi WHERE id = ?");
        $stmtDiv->execute([$doc['division_id']]);
        $divName = $stmtDiv->fetchColumn();
        if ($divName) $divisiName = $divName;
    }

    // Cari users yang memiliki role ini
    $stmtUsers = $pdo->prepare("
        SELECT u.id, u.nama, u.no_hp 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.nama_role = ? AND u.is_active = 1
    ");
    $stmtUsers->execute([$targetRoleName]);
    $users = $stmtUsers->fetchAll();

    $wa = new WhatsAppService();
    foreach ($users as $user) {
        if (!empty($user['no_hp'])) {
            $link = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/new-hospital/approval.php";
            
            $message = sprintf(
                "Halo %s,\nAda dokumen baru \"%s\" dari Divisi %s yang membutuhkan %s Anda.\n\nSilakan buka tautan berikut untuk memproses:\n%s",
                $user['nama'],
                $doc['name'],
                $divisiName,
                $actionName,
                $link
            );

            $waResult = $wa->send($user['no_hp'], $message);

            // Log hasil pengiriman WhatsApp
            $stmtLog = $pdo->prepare("INSERT INTO document_logs (document_id, user_id, aksi, detail) VALUES (?, ?, ?, ?)");
            $actionLog = $waResult['success'] ? 'Send WA Success' : 'Send WA Failed';
            $detailLog = $waResult['success'] ? "Terkirim ke {$user['no_hp']} (Role: {$targetRoleName})" : "Gagal: " . $waResult['message'];
            $stmtLog->execute([$documentId, $user['id'], $actionLog, $detailLog]);

            echo "WhatsApp sent to {$user['nama']} ({$user['no_hp']}): " . ($waResult['success'] ? "Sukses" : "Gagal - " . $waResult['message']) . "\n";
        }
    }
}
?>
