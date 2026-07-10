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
    // 1. Total Dokumen Legal (Sum of Regulasi, Perizinan, and Arsip Legal)
    $countRegulasi = $pdo->query("SELECT COUNT(*) FROM dokumen_regulasi")->fetchColumn();
    $countPerizinan = $pdo->query("SELECT COUNT(*) FROM dokumen_perizinan")->fetchColumn();
    $countArsip = $pdo->query("SELECT COUNT(*) FROM dokumen_arsip_legal")->fetchColumn();
    
    $totalDokumenLegal = $countRegulasi + $countPerizinan + $countArsip;
    
    // New documents this month
    $newRegulasi = $pdo->query("SELECT COUNT(*) FROM dokumen_regulasi WHERE MONTH(tanggal_terbit) = MONTH(CURDATE()) AND YEAR(tanggal_terbit) = YEAR(CURDATE())")->fetchColumn();
    $newPerizinan = $pdo->query("SELECT COUNT(*) FROM dokumen_perizinan WHERE MONTH(masa_berlaku_mulai) = MONTH(CURDATE()) AND YEAR(masa_berlaku_mulai) = YEAR(CURDATE())")->fetchColumn();
    $newArsip = $pdo->query("SELECT COUNT(*) FROM dokumen_arsip_legal WHERE MONTH(tanggal_mulai) = MONTH(CURDATE()) AND YEAR(tanggal_mulai) = YEAR(CURDATE())")->fetchColumn();
    
    $legalBaru = $newRegulasi + $newPerizinan + $newArsip;
} catch (PDOException $e) { 
    $totalDokumenLegal = 0;
    $legalBaru = 0; 
}

try {
    // 2. SIP Akan Expired (H-30)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tenaga_medis WHERE masa_berlaku_sip_akhir BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $sipExpiring = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {}

try {
    // 3. STR Akan Expired (H-60)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tenaga_medis WHERE masa_berlaku_str_akhir BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)");
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
                <div class="bg-white rounded-2xl shadow-sm border border-gray-150 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Selamat Datang, <?php echo htmlspecialchars($user['nama'] ?? $user['name'] ?? 'Direktur'); ?></h1>
                        <p class="text-gray-500 mt-1 text-sm font-medium">Sistem Informasi Legal & Corporate Secretary RS Taman Harapan Baru</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 bg-gray-50 border border-gray-200 px-4 py-2 rounded-xl self-start md:self-auto font-semibold">
                        <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                        <span><?php echo date('d M Y'); ?></span>
                    </div>
                </div>

                <!-- Stat Cards First Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Dokumen Legal -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:-translate-y-1 transition-all duration-300 transform">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Dokumen Legal</p>
                                <h3 class="text-3xl font-extrabold text-gray-900"><?php echo $totalDokumenLegal; ?></h3>
                                <p class="text-xs text-emerald-600 mt-2 font-bold bg-emerald-50 px-2 py-0.5 rounded-md inline-block">+<?php echo $legalBaru; ?> Bulan Ini</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-teal-600 rounded-xl flex items-center justify-center shadow-lg shadow-teal-500/20 text-white">
                                <i data-lucide="file-text" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>

                    <!-- SIP Akan Expired -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:-translate-y-1 transition-all duration-300 transform">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">SIP Akan Expired</p>
                                <h3 class="text-3xl font-extrabold text-gray-900"><?php echo $sipExpiring; ?></h3>
                                <p class="text-xs text-amber-600 mt-2 font-bold bg-amber-50 px-2 py-0.5 rounded-md inline-block">Dalam 30 Hari</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-orange-500/20 text-white">
                                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>

                    <!-- STR Akan Expired -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:-translate-y-1 transition-all duration-300 transform">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">STR Akan Expired</p>
                                <h3 class="text-3xl font-extrabold text-gray-900"><?php echo $strExpiring; ?></h3>
                                <p class="text-xs text-orange-600 mt-2 font-bold bg-orange-50 px-2 py-0.5 rounded-md inline-block">Dalam 60 Hari</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-red-500 rounded-xl flex items-center justify-center shadow-lg shadow-red-500/20 text-white">
                                <i data-lucide="user-check" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Akreditasi -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:-translate-y-1 transition-all duration-300 transform">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Progress Akreditasi</p>
                                <h3 class="text-3xl font-extrabold text-gray-900"><?php echo $progressAkreditasi; ?>%</h3>
                                <p class="text-xs text-blue-600 mt-2 font-bold bg-blue-50 px-2 py-0.5 rounded-md inline-block"><?php echo $terpenuhi_akreditasi; ?> / <?php echo $total_akreditasi; ?> Dokumen</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 text-white">
                                <i data-lucide="trending-up" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Surat Masuk -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:-translate-y-1 transition-all duration-300 transform">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Surat Masuk</p>
                                <h3 class="text-3xl font-extrabold text-gray-900"><?php echo $totalSuratMasuk; ?></h3>
                                <p class="text-xs text-teal-600 mt-2 font-bold bg-teal-50 px-2 py-0.5 rounded-md inline-block">+<?php echo $suratMasukBaru; ?> Bulan Ini</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-teal-400 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/20 text-white">
                                <i data-lucide="mail" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Total Surat Keluar -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:-translate-y-1 transition-all duration-300 transform">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Surat Keluar</p>
                                <h3 class="text-3xl font-extrabold text-gray-900"><?php echo $totalSuratKeluar; ?></h3>
                                <p class="text-xs text-sky-600 mt-2 font-bold bg-sky-50 px-2 py-0.5 rounded-md inline-block">+<?php echo $suratKeluarBaru; ?> Bulan Ini</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-sky-400 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20 text-white">
                                <i data-lucide="send" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Direksi -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:-translate-y-1 transition-all duration-300 transform">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">KPI Direksi</p>
                                <h3 class="text-3xl font-extrabold text-gray-900"><?php echo $kpiDireksi; ?>%</h3>
                                <p class="text-xs text-purple-600 mt-2 font-bold bg-purple-50 px-2 py-0.5 rounded-md inline-block">Tercapai</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-fuchsia-600 rounded-xl flex items-center justify-center shadow-lg shadow-fuchsia-500/20 text-white">
                                <i data-lucide="target" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Monitoring Risiko -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:-translate-y-1 transition-all duration-300 transform">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Monitoring Risiko</p>
                                <h3 class="text-3xl font-extrabold text-gray-900"><?php echo $monitoringRisiko; ?></h3>
                                <p class="text-xs text-rose-600 mt-2 font-bold bg-rose-50 px-2 py-0.5 rounded-md inline-block">Risiko Terdaftar</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-rose-400 to-red-600 rounded-xl flex items-center justify-center shadow-lg shadow-red-500/20 text-white">
                                <i data-lucide="shield-alert" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
