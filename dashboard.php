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
    <main class="flex-1 flex flex-col bg-gray-50 min-h-screen">
        <?php include 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 px-8 py-6 overflow-y-auto">
            <div class="space-y-8">
                <!-- Greeting -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_12px_rgba(0,0,0,0.08)] p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex flex-col">
                        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Selamat Datang, <?php echo htmlspecialchars($user['nama'] ?? $user['name'] ?? 'Direktur'); ?></h1>
                        <p class="text-slate-500 mt-1 text-sm font-medium">Sistem Informasi Legal & Corporate Secretary RS Taman Harapan Baru</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500 bg-gray-50 border border-gray-200 px-4 py-2 rounded-xl self-start md:self-auto font-semibold">
                        <i data-lucide="calendar" class="w-4 h-4 text-teal-600"></i>
                        <span><?php echo date('d M Y'); ?></span>
                    </div>
                </div>

                <!-- Stat Cards First Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Dokumen Legal -->
                    <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Dokumen Legal</span>
                                <span class="text-3xl font-bold text-slate-900 mt-2"><?php echo $totalDokumenLegal; ?></span>
                            </div>
                            <div class="w-10 h-10 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="file-text" class="w-5 h-5 stroke-blue-600 fill-blue-50/50"></i>
                            </div>
                        </div>
                        
                        <!-- Thin Subtle Progress Bar -->
                        <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                            <div class="bg-blue-600 h-full rounded-full" style="width: 75%"></div>
                        </div>

                        <div class="flex justify-between items-center mt-3">
                            <span class="text-[10px] text-blue-700 font-bold bg-blue-50/70 border border-blue-100 px-2 py-0.5 rounded-md">+<?php echo $legalBaru; ?> Bulan Ini</span>
                        </div>
                    </div>

                    <!-- SIP Akan Expired -->
                    <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">SIP Akan Expired</span>
                                <span class="text-3xl font-bold text-slate-900 mt-2"><?php echo $sipExpiring; ?></span>
                            </div>
                            <div class="w-10 h-10 bg-amber-50 border border-amber-100 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="alert-triangle" class="w-5 h-5 stroke-amber-600 fill-amber-50/50"></i>
                            </div>
                        </div>
                        
                        <!-- Thin Subtle Progress Bar -->
                        <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                            <div class="bg-amber-500 h-full rounded-full" style="width: 15%"></div>
                        </div>

                        <div class="flex justify-between items-center mt-3">
                            <span class="text-[10px] text-amber-700 font-bold bg-amber-50/70 border border-amber-100 px-2 py-0.5 rounded-md">Dalam 30 Hari</span>
                        </div>
                    </div>

                    <!-- STR Akan Expired -->
                    <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">STR Akan Expired</span>
                                <span class="text-3xl font-bold text-slate-900 mt-2"><?php echo $strExpiring; ?></span>
                            </div>
                            <div class="w-10 h-10 bg-orange-50 border border-orange-100 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="user-check" class="w-5 h-5 stroke-orange-600 fill-orange-50/50"></i>
                            </div>
                        </div>
                        
                        <!-- Thin Subtle Progress Bar -->
                        <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                            <div class="bg-orange-500 h-full rounded-full" style="width: 20%"></div>
                        </div>

                        <div class="flex justify-between items-center mt-3">
                            <span class="text-[10px] text-orange-700 font-bold bg-orange-50/70 border border-orange-100 px-2 py-0.5 rounded-md">Dalam 60 Hari</span>
                        </div>
                    </div>

                    <!-- Progress Akreditasi -->
                    <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Progress Akreditasi</span>
                                <span class="text-3xl font-bold text-slate-900 mt-2"><?php echo $progressAkreditasi; ?>%</span>
                            </div>
                            <div class="w-10 h-10 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="trending-up" class="w-5 h-5 stroke-emerald-600 fill-emerald-50/50"></i>
                            </div>
                        </div>
                        
                        <!-- Thin Subtle Progress Bar -->
                        <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                            <div class="bg-emerald-600 h-full rounded-full" style="width: <?php echo max(5, $progressAkreditasi); ?>%"></div>
                        </div>

                        <div class="flex justify-between items-center mt-3">
                            <span class="text-[10px] text-emerald-700 font-bold bg-emerald-50/70 border border-emerald-100 px-2 py-0.5 rounded-md text-emerald-700"><?php echo $terpenuhi_akreditasi; ?> / <?php echo $total_akreditasi; ?> Dokumen</span>
                        </div>
                    </div>
                </div>

                <!-- Second Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Surat Masuk -->
                    <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Surat Masuk</span>
                                <span class="text-3xl font-bold text-slate-900 mt-2"><?php echo $totalSuratMasuk; ?></span>
                            </div>
                            <div class="w-10 h-10 bg-sky-50 border border-sky-100 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="mail" class="w-5 h-5 stroke-sky-600 fill-sky-50/50"></i>
                            </div>
                        </div>
                        
                        <!-- Thin Subtle Progress Bar -->
                        <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                            <div class="bg-sky-500 h-full rounded-full" style="width: 50%"></div>
                        </div>

                        <div class="flex justify-between items-center mt-3">
                            <span class="text-[10px] text-sky-700 font-bold bg-sky-50/70 border border-sky-100 px-2 py-0.5 rounded-md">+<?php echo $suratMasukBaru; ?> Bulan Ini</span>
                        </div>
                    </div>

                    <!-- Total Surat Keluar -->
                    <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Surat Keluar</span>
                                <span class="text-3xl font-bold text-slate-900 mt-2"><?php echo $totalSuratKeluar; ?></span>
                            </div>
                            <div class="w-10 h-10 bg-indigo-50 border border-indigo-100 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="send" class="w-5 h-5 stroke-indigo-600 fill-indigo-50/50"></i>
                            </div>
                        </div>
                        
                        <!-- Thin Subtle Progress Bar -->
                        <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                            <div class="bg-indigo-600 h-full rounded-full" style="width: 40%"></div>
                        </div>

                        <div class="flex justify-between items-center mt-3">
                            <span class="text-[10px] text-indigo-700 font-bold bg-indigo-50/70 border border-indigo-100 px-2 py-0.5 rounded-md">+<?php echo $suratKeluarBaru; ?> Bulan Ini</span>
                        </div>
                    </div>

                    <!-- KPI Direksi -->
                    <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">KPI Direksi</span>
                                <span class="text-3xl font-bold text-slate-900 mt-2"><?php echo $kpiDireksi; ?>%</span>
                            </div>
                            <div class="w-10 h-10 bg-purple-50 border border-purple-100 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="target" class="w-5 h-5 stroke-purple-600 fill-purple-50/50"></i>
                            </div>
                        </div>
                        
                        <!-- Thin Subtle Progress Bar -->
                        <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                            <div class="bg-purple-650 h-full rounded-full" style="width: <?php echo max(5, $kpiDireksi); ?>%"></div>
                        </div>

                        <div class="flex justify-between items-center mt-3">
                            <span class="text-[10px] text-purple-700 font-bold bg-purple-50/70 border border-purple-100 px-2 py-0.5 rounded-md">Tercapai</span>
                        </div>
                    </div>

                    <!-- Monitoring Risiko -->
                    <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Monitoring Risiko</span>
                                <span class="text-3xl font-bold text-slate-900 mt-2"><?php echo $monitoringRisiko; ?></span>
                            </div>
                            <div class="w-10 h-10 bg-rose-50 border border-rose-100 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="shield-alert" class="w-5 h-5 stroke-rose-600 fill-rose-50/50"></i>
                            </div>
                        </div>
                        
                        <!-- Thin Subtle Progress Bar -->
                        <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                            <div class="bg-rose-500 h-full rounded-full" style="width: 10%"></div>
                        </div>

                        <div class="flex justify-between items-center mt-3">
                            <span class="text-[10px] text-rose-700 font-bold bg-rose-50/70 border border-rose-100 px-2 py-0.5 rounded-md font-semibold">Risiko Terdaftar</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
