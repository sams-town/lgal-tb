<?php

require_once 'config/database.php';

header('Content-Type: application/json');

try {
    // If database is not connected, return mock data
    $stats = [
        'totalDokumenLegal' => 1247,
        'sipExpiring' => 23,
        'strExpiring' => 45,
        'progressAkreditasi' => 87,
        'totalSuratMasuk' => 856,
        'totalSuratKeluar' => 623,
        'kpiDireksi' => 92,
        'monitoringRisiko' => 12
    ];

    // Try to query real data if tables exist
    try {
        $pdo->exec("SELECT 1 FROM surat_masuk LIMIT 1");
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM surat_masuk");
        $totalSuratMasuk = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM surat_keluar");
        $totalSuratKeluar = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_sop");
        $totalDokumenLegal = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM kontrak_pks");
        $totalKontrak = $stmt->fetchColumn();
        
        $stats['totalSuratMasuk'] = (int)$totalSuratMasuk;
        $stats['totalSuratKeluar'] = (int)$totalSuratKeluar;
        $stats['totalDokumenLegal'] = (int)$totalDokumenLegal;
        
    } catch (Exception $e) {
        // Use default mock data
    }

    echo json_encode([
        'status' => 'success',
        'data' => $stats
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
