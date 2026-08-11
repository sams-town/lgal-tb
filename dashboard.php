<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// ── 1. Dokumen Legal per jenis ────────────────────────────
$countPKS       = 0; $newPKS       = 0;
$countRegulasi  = 0; $newRegulasi  = 0;
$countPerizinan = 0; $newPerizinan = 0;

try {
    $countPKS      = (int)$pdo->query("SELECT COUNT(*) FROM dokumen_arsip_legal")->fetchColumn();
    $newPKS        = (int)$pdo->query("SELECT COUNT(*) FROM dokumen_arsip_legal WHERE MONTH(tanggal_mulai)=MONTH(CURDATE()) AND YEAR(tanggal_mulai)=YEAR(CURDATE())")->fetchColumn();
} catch (PDOException $e) {}
try {
    $countRegulasi = (int)$pdo->query("SELECT COUNT(*) FROM dokumen_regulasi")->fetchColumn();
    $newRegulasi   = (int)$pdo->query("SELECT COUNT(*) FROM dokumen_regulasi WHERE MONTH(tanggal_terbit)=MONTH(CURDATE()) AND YEAR(tanggal_terbit)=YEAR(CURDATE())")->fetchColumn();
} catch (PDOException $e) {}
try {
    $countPerizinan= (int)$pdo->query("SELECT COUNT(*) FROM dokumen_perizinan")->fetchColumn();
    $newPerizinan  = (int)$pdo->query("SELECT COUNT(*) FROM dokumen_perizinan WHERE MONTH(masa_berlaku_mulai)=MONTH(CURDATE()) AND YEAR(masa_berlaku_mulai)=YEAR(CURDATE())")->fetchColumn();
} catch (PDOException $e) {}

// ── 2. Tenaga Medis per kategori ──────────────────────────
$totalDokterSpesialis = 0;
$totalPerawat         = 0;
$totalNakes           = 0;

try {
    $totalDokterSpesialis = (int)$pdo->query("SELECT COUNT(*) FROM tenaga_medis WHERE tipe_form='komite-medik'")->fetchColumn();
} catch (PDOException $e) {}
try {
    $totalPerawat         = (int)$pdo->query("SELECT COUNT(*) FROM tenaga_medis WHERE tipe_form='komite-keperawatan'")->fetchColumn();
} catch (PDOException $e) {}
try {
    $totalNakes           = (int)$pdo->query("SELECT COUNT(*) FROM tenaga_medis WHERE tipe_form NOT IN ('komite-medik','komite-keperawatan')")->fetchColumn();
} catch (PDOException $e) {}

// ── 3. Surat Masuk & Keluar ───────────────────────────────
$totalSuratMasuk  = 0; $suratMasukBaru  = 0;
$totalSuratKeluar = 0; $suratKeluarBaru = 0;
$monitoringRisiko = 0;

try {
    $inMasuk = "'Surat Masuk','Internal Memo','Notulen','Surat Tugas','Surat Keterangan','Surat Pernyataan','Surat Undangan','Surat Edaran','Surat Kuasa','Sertifikat','Surat Perintah Kerja (SPK)','Berita Acara','Perjanjian Kerjasama (PKS)','SPO','Peraturan Direktur (Perdir)'";
    $totalSuratMasuk  = (int)$pdo->query("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inMasuk)")->fetchColumn();
    $suratMasukBaru   = (int)$pdo->query("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inMasuk) AND MONTH(tanggal_surat)=MONTH(CURDATE()) AND YEAR(tanggal_surat)=YEAR(CURDATE())")->fetchColumn();
} catch (PDOException $e) {}
try {
    $inKeluar = "'Internal Memo','Disposisi','Surat Keluar','Surat Tugas','Surat Keterangan','Surat Pernyataan','Surat Undangan','Surat Edaran','Surat Kuasa','Sertifikat','Surat Perintah Kerja (SPK)','Berita Acara','Perjanjian Kerjasama (PKS)','SPO','Peraturan Direktur (Perdir)'";
    $totalSuratKeluar = (int)$pdo->query("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inKeluar)")->fetchColumn();
    $suratKeluarBaru  = (int)$pdo->query("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inKeluar) AND MONTH(tanggal_surat)=MONTH(CURDATE()) AND YEAR(tanggal_surat)=YEAR(CURDATE())")->fetchColumn();
} catch (PDOException $e) {}
try {
    $monitoringRisiko = (int)$pdo->query("SELECT COUNT(*) FROM dokumen_corsec WHERE kategori='Risk Management'")->fetchColumn();
} catch (PDOException $e) {}

// Helper card renderer
function statCard(string $label, $value, string $icon, string $color, string $badgeText, string $badgeColor, string $barPct = '50'): string {
    return <<<HTML
    <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
        <div class="flex justify-between items-start">
            <div class="flex flex-col">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">$label</span>
                <span class="text-3xl font-bold text-slate-900 mt-2">$value</span>
            </div>
            <div class="w-10 h-10 bg-{$color}-50 border border-{$color}-100 rounded-xl flex items-center justify-center shrink-0">
                <i data-lucide="$icon" class="w-5 h-5 stroke-{$color}-600"></i>
            </div>
        </div>
        <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
            <div class="bg-{$color}-500 h-full rounded-full" style="width:{$barPct}%"></div>
        </div>
        <div class="mt-3">
            <span class="text-[10px] text-{$badgeColor}-700 font-bold bg-{$badgeColor}-50 border border-{$badgeColor}-100 px-2 py-0.5 rounded-md">$badgeText</span>
        </div>
    </div>
    HTML;
}
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

    <main class="flex-1 flex flex-col bg-gray-50 min-h-screen">
        <?php include 'includes/header.php'; ?>

        <div class="flex-1 px-8 py-6 overflow-y-auto">
            <div class="space-y-8">

                <!-- Greeting -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_12px_rgba(0,0,0,0.08)] p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">
                            Selamat Datang, <?= htmlspecialchars($user['nama'] ?? $user['name'] ?? 'Pengguna') ?>
                        </h1>
                        <p class="text-slate-500 mt-1 text-sm font-medium">Sistem Informasi Legal &amp; Corporate Secretary RS Taman Harapan Baru</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500 bg-gray-50 border border-gray-200 px-4 py-2 rounded-xl self-start md:self-auto font-semibold">
                        <i data-lucide="calendar" class="w-4 h-4 text-teal-600"></i>
                        <span><?= date('l, d M Y') ?></span>
                    </div>
                </div>

                <!-- ═══ BARIS 1: Dokumen Legal per jenis ═══ -->
                <div>
                    <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <i data-lucide="folder-open" class="w-4 h-4 text-blue-500"></i> Dokumen Legal
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                        <!-- PKS -->
                        <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Perjanjian Kerjasama (PKS)</span>
                                    <div class="text-3xl font-bold text-slate-900 mt-2"><?= $countPKS ?></div>
                                </div>
                                <div class="w-10 h-10 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-center shrink-0">
                                    <i data-lucide="file-signature" class="w-5 h-5 stroke-blue-600"></i>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                                <div class="bg-blue-500 h-full rounded-full" style="width:<?= $countPKS>0?'70':'5' ?>%"></div>
                            </div>
                            <div class="mt-3">
                                <span class="text-[10px] text-blue-700 font-bold bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-md">+<?= $newPKS ?> Bulan Ini</span>
                            </div>
                        </div>

                        <!-- Regulasi -->
                        <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Regulasi</span>
                                    <div class="text-3xl font-bold text-slate-900 mt-2"><?= $countRegulasi ?></div>
                                </div>
                                <div class="w-10 h-10 bg-violet-50 border border-violet-100 rounded-xl flex items-center justify-center shrink-0">
                                    <i data-lucide="book-open" class="w-5 h-5 stroke-violet-600"></i>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                                <div class="bg-violet-500 h-full rounded-full" style="width:<?= $countRegulasi>0?'65':'5' ?>%"></div>
                            </div>
                            <div class="mt-3">
                                <span class="text-[10px] text-violet-700 font-bold bg-violet-50 border border-violet-100 px-2 py-0.5 rounded-md">+<?= $newRegulasi ?> Bulan Ini</span>
                            </div>
                        </div>

                        <!-- Perizinan -->
                        <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Perizinan</span>
                                    <div class="text-3xl font-bold text-slate-900 mt-2"><?= $countPerizinan ?></div>
                                </div>
                                <div class="w-10 h-10 bg-teal-50 border border-teal-100 rounded-xl flex items-center justify-center shrink-0">
                                    <i data-lucide="badge-check" class="w-5 h-5 stroke-teal-600"></i>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                                <div class="bg-teal-500 h-full rounded-full" style="width:<?= $countPerizinan>0?'60':'5' ?>%"></div>
                            </div>
                            <div class="mt-3">
                                <span class="text-[10px] text-teal-700 font-bold bg-teal-50 border border-teal-100 px-2 py-0.5 rounded-md">+<?= $newPerizinan ?> Bulan Ini</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ═══ BARIS 2: Tenaga Medis ═══ -->
                <div>
                    <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <i data-lucide="users" class="w-4 h-4 text-emerald-500"></i> Tenaga Medis
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                        <!-- Dokter Spesialis -->
                        <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Dokter Spesialis</span>
                                    <div class="text-3xl font-bold text-slate-900 mt-2"><?= $totalDokterSpesialis ?></div>
                                </div>
                                <div class="w-10 h-10 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center justify-center shrink-0">
                                    <i data-lucide="stethoscope" class="w-5 h-5 stroke-emerald-600"></i>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                                <div class="bg-emerald-500 h-full rounded-full" style="width:<?= $totalDokterSpesialis>0?'75':'5' ?>%"></div>
                            </div>
                            <div class="mt-3">
                                <span class="text-[10px] text-emerald-700 font-bold bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-md">Komite Medik</span>
                            </div>
                        </div>

                        <!-- Total Perawat -->
                        <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Perawat</span>
                                    <div class="text-3xl font-bold text-slate-900 mt-2"><?= $totalPerawat ?></div>
                                </div>
                                <div class="w-10 h-10 bg-sky-50 border border-sky-100 rounded-xl flex items-center justify-center shrink-0">
                                    <i data-lucide="heart-pulse" class="w-5 h-5 stroke-sky-600"></i>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                                <div class="bg-sky-500 h-full rounded-full" style="width:<?= $totalPerawat>0?'70':'5' ?>%"></div>
                            </div>
                            <div class="mt-3">
                                <span class="text-[10px] text-sky-700 font-bold bg-sky-50 border border-sky-100 px-2 py-0.5 rounded-md">Komite Keperawatan</span>
                            </div>
                        </div>

                        <!-- Tenaga Kesehatan Lainnya -->
                        <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tenaga Kesehatan Lainnya</span>
                                    <div class="text-3xl font-bold text-slate-900 mt-2"><?= $totalNakes ?></div>
                                </div>
                                <div class="w-10 h-10 bg-amber-50 border border-amber-100 rounded-xl flex items-center justify-center shrink-0">
                                    <i data-lucide="user-plus" class="w-5 h-5 stroke-amber-600"></i>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                                <div class="bg-amber-500 h-full rounded-full" style="width:<?= $totalNakes>0?'60':'5' ?>%"></div>
                            </div>
                            <div class="mt-3">
                                <span class="text-[10px] text-amber-700 font-bold bg-amber-50 border border-amber-100 px-2 py-0.5 rounded-md">Nakes &amp; Lainnya</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ═══ BARIS 3: Surat & Risiko ═══ -->
                <div>
                    <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <i data-lucide="mail" class="w-4 h-4 text-indigo-500"></i> Sekretariat
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                        <!-- Total Surat Masuk -->
                        <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Surat Masuk</span>
                                    <div class="text-3xl font-bold text-slate-900 mt-2"><?= $totalSuratMasuk ?></div>
                                </div>
                                <div class="w-10 h-10 bg-indigo-50 border border-indigo-100 rounded-xl flex items-center justify-center shrink-0">
                                    <i data-lucide="mail" class="w-5 h-5 stroke-indigo-600"></i>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                                <div class="bg-indigo-500 h-full rounded-full" style="width:<?= $totalSuratMasuk>0?'55':'5' ?>%"></div>
                            </div>
                            <div class="mt-3">
                                <span class="text-[10px] text-indigo-700 font-bold bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded-md">+<?= $suratMasukBaru ?> Bulan Ini</span>
                            </div>
                        </div>

                        <!-- Total Surat Keluar -->
                        <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Surat Keluar</span>
                                    <div class="text-3xl font-bold text-slate-900 mt-2"><?= $totalSuratKeluar ?></div>
                                </div>
                                <div class="w-10 h-10 bg-purple-50 border border-purple-100 rounded-xl flex items-center justify-center shrink-0">
                                    <i data-lucide="send" class="w-5 h-5 stroke-purple-600"></i>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                                <div class="bg-purple-500 h-full rounded-full" style="width:<?= $totalSuratKeluar>0?'45':'5' ?>%"></div>
                            </div>
                            <div class="mt-3">
                                <span class="text-[10px] text-purple-700 font-bold bg-purple-50 border border-purple-100 px-2 py-0.5 rounded-md">+<?= $suratKeluarBaru ?> Bulan Ini</span>
                            </div>
                        </div>

                        <!-- Monitoring Risiko -->
                        <div class="bg-white rounded-2xl shadow-[0_4px_12px_rgba(0,0,0,0.08)] border border-gray-100 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Monitoring Risiko</span>
                                    <div class="text-3xl font-bold text-slate-900 mt-2"><?= $monitoringRisiko ?></div>
                                </div>
                                <div class="w-10 h-10 bg-rose-50 border border-rose-100 rounded-xl flex items-center justify-center shrink-0">
                                    <i data-lucide="shield-alert" class="w-5 h-5 stroke-rose-600"></i>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full mt-4 overflow-hidden">
                                <div class="bg-rose-500 h-full rounded-full" style="width:<?= $monitoringRisiko>0?'30':'5' ?>%"></div>
                            </div>
                            <div class="mt-3">
                                <span class="text-[10px] text-rose-700 font-bold bg-rose-50 border border-rose-100 px-2 py-0.5 rounded-md">Risiko Terdaftar</span>
                            </div>
                        </div>

                    </div>
                </div>

            </div><!-- /space-y-8 -->
        </div><!-- /px-8 -->
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.lucide) lucide.createIcons();
        });
    </script>
</body>
</html>
