<?php
session_start();
require_once 'config/database.php';

// Proteksi halaman
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Ambil data ringkasan eksekutif dengan default 0
$totalDokumenLegal = 0;
$sipExpiring = 0;
$strExpiring = 0;
$progressAkreditasi = 0;
$totalSuratMasuk = 0;
$totalSuratKeluar = 0;
$kpiDireksi = 0;
$monitoringRisiko = 0;

try {
    // 1. Total Dokumen Legal (dari dokumen_legal)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dokumen_legal WHERE status = 'Publish/Aktif'");
    $totalDokumenLegal = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dokumen_legal WHERE status = 'Publish/Aktif' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
    $legalBaru = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) { $legalBaru = 0; }

try {
    // 2. SIP Akan Expired (H-30)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tenaga_medis WHERE masa_berlaku_sip_akhir BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $sipExpiring = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {}

try {
    // 3. STR Akan Expired (H-60)
    // Asumsi menggunakan tabel yang sama atau kolom lain jika ada, kita set 0 jika gagal
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tenaga_medis WHERE masa_berlaku_sk_akhir BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)");
    $stmt->execute();
    $strExpiring = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {}

try {
    // 4. Progress Akreditasi
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dokumen_akreditasi");
    $total_akreditasi = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as terpenuhi FROM dokumen_akreditasi WHERE status_pemenuhan = 'Sudah Terpenuhi'");
    $terpenuhi_akreditasi = $stmt->fetch()['terpenuhi'] ?? 0;
    
    $progressAkreditasi = $total_akreditasi > 0 ? round(($terpenuhi_akreditasi / $total_akreditasi) * 100) : 0;
} catch (PDOException $e) {}

try {
    // 5. Total Surat Masuk
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM manajemen_surat WHERE kategori = 'Surat Masuk'");
    $totalSuratMasuk = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM manajemen_surat WHERE kategori = 'Surat Masuk' AND MONTH(tanggal_surat) = MONTH(CURDATE()) AND YEAR(tanggal_surat) = YEAR(CURDATE())");
    $suratMasukBaru = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) { $suratMasukBaru = 0; }

try {
    // 6. Total Surat Keluar
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM manajemen_surat WHERE kategori = 'Surat Keluar'");
    $totalSuratKeluar = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM manajemen_surat WHERE kategori = 'Surat Keluar' AND MONTH(tanggal_surat) = MONTH(CURDATE()) AND YEAR(tanggal_surat) = YEAR(CURDATE())");
    $suratKeluarBaru = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) { $suratKeluarBaru = 0; }

try {
    // 7. KPI Direksi
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dokumen_corsec WHERE kategori = 'KPI Direksi'");
    $kpiDireksi = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {}

try {
    // 8. Monitoring Risiko
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dokumen_corsec WHERE kategori = 'Risk Management'");
    $monitoringRisiko = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <?php include 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-8">
                <!-- Greeting -->
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Selamat Datang, Direktur</h1>
                    <p class="text-gray-600 mt-2">Dashboard Overview Sistem Informasi Legal & Corporate Secretary Rumah Sakit</p>
                </div>

                <!-- Stat Cards First Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Dokumen Legal -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Dokumen Legal</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDokumenLegal; ?></h3>
                                <p class="text-sm text-emerald-600 mt-1 font-medium">+<?php echo $legalBaru; ?> Bulan Ini</p>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">
                                📄
                            </div>
                        </div>
                    </div>

                    <!-- SIP Akan Expired -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">SIP Akan Expired</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $sipExpiring; ?></h3>
                                <p class="text-sm text-amber-600 mt-1 font-medium">Dalam 30 Hari</p>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-500 rounded-2xl flex items-center justify-center text-3xl">
                                ⚠️
                            </div>
                        </div>
                    </div>

                    <!-- STR Akan Expired -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">STR Akan Expired</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $strExpiring; ?></h3>
                                <p class="text-sm text-orange-600 mt-1 font-medium">Dalam 60 Hari</p>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-red-500 rounded-2xl flex items-center justify-center text-3xl">
                                👨‍⚕️
                            </div>
                        </div>
                    </div>

                    <!-- Progress Akreditasi -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Progress Akreditasi</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $progressAkreditasi; ?>%</h3>
                                <p class="text-sm text-emerald-600 mt-1 font-medium"><?php echo $terpenuhi_akreditasi; ?> dari <?php echo $total_akreditasi; ?> Dokumen</p>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-2xl flex items-center justify-center text-3xl">
                                📈
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Surat Masuk -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Surat Masuk</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalSuratMasuk; ?></h3>
                                <p class="text-sm text-emerald-600 mt-1 font-medium">+<?php echo $suratMasukBaru; ?> Bulan Ini</p>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">
                                ✉️
                            </div>
                        </div>
                    </div>

                    <!-- Total Surat Keluar -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Surat Keluar</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalSuratKeluar; ?></h3>
                                <p class="text-sm text-emerald-600 mt-1 font-medium">+<?php echo $suratKeluarBaru; ?> Bulan Ini</p>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">
                                📤
                            </div>
                        </div>
                    </div>

                    <!-- KPI Direksi -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">KPI Direksi</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $kpiDireksi; ?>%</h3>
                                <p class="text-sm text-emerald-600 mt-1 font-medium">Tercapai</p>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-violet-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">
                                🎯
                            </div>
                        </div>
                    </div>

                    <!-- Monitoring Risiko -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Monitoring Risiko</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $monitoringRisiko; ?></h3>
                                <p class="text-sm text-red-600 mt-1 font-medium">Risiko Terdaftar</p>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl flex items-center justify-center text-3xl">
                                🛡️
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
