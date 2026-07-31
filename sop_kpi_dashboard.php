<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!hasPermission('sop_view')) {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION['user'];

// --- Backend Logic ---
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Total Karyawan Dinilai (Bulan & Tahun terkait)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT karyawan_id) as total FROM kpi_penilaian WHERE bulan = ? AND tahun = ?");
$stmt->execute([$bulan, $tahun]);
$karyawanDinilai = $stmt->fetchColumn() ?: 0;

// Total Karyawan (Semua)
$stmt = $pdo->query("SELECT COUNT(*) FROM kpi_karyawan WHERE status = 'Aktif'");
$totalKaryawan = $stmt->fetchColumn() ?: 0;

// Rata-rata Skor RS (Bulan & Tahun)
$stmt = $pdo->prepare("SELECT AVG(total_skor) as rata_rata FROM kpi_penilaian WHERE bulan = ? AND tahun = ?");
$stmt->execute([$bulan, $tahun]);
$rataRataSkor = number_format((float)$stmt->fetchColumn(), 1);

$predikatSkor = "Kurang";
if ($rataRataSkor >= 85) $predikatSkor = "Sangat Baik";
elseif ($rataRataSkor >= 70) $predikatSkor = "Baik";
elseif ($rataRataSkor >= 60) $predikatSkor = "Cukup";

// Unit Terbaik (Berdasarkan rata-rata nilai karyawan di unit tersebut)
$stmt = $pdo->prepare("
    SELECT k.unit, AVG(p.total_skor) as avg_skor 
    FROM kpi_penilaian p
    JOIN kpi_karyawan k ON p.karyawan_id = k.id
    WHERE p.bulan = ? AND p.tahun = ?
    GROUP BY k.unit
    ORDER BY avg_skor DESC LIMIT 1
");
$stmt->execute([$bulan, $tahun]);
$unitTerbaikData = $stmt->fetch();
$unitTerbaik = $unitTerbaikData ? $unitTerbaikData['unit'] : "-";

// Jumlah Unit
$stmt = $pdo->query("SELECT COUNT(DISTINCT unit) FROM kpi_karyawan WHERE status = 'Aktif'");
$jumlahUnit = $stmt->fetchColumn() ?: 0;

// Data untuk Chart 1: Rata-rata per Unit
$stmt = $pdo->prepare("
    SELECT k.unit, AVG(p.total_skor) as avg_skor 
    FROM kpi_penilaian p
    JOIN kpi_karyawan k ON p.karyawan_id = k.id
    WHERE p.bulan = ? AND p.tahun = ?
    GROUP BY k.unit
");
$stmt->execute([$bulan, $tahun]);
$chartUnitLabels = [];
$chartUnitData = [];
while ($row = $stmt->fetch()) {
    $chartUnitLabels[] = $row['unit'];
    $chartUnitData[] = round($row['avg_skor'], 1);
}

// Data untuk Chart 2: Tren 12 Bulan (Tahun yang dipilih)
$stmt = $pdo->prepare("
    SELECT bulan, AVG(total_skor) as avg_skor 
    FROM kpi_penilaian 
    WHERE tahun = ?
    GROUP BY bulan
    ORDER BY bulan ASC
");
$stmt->execute([$tahun]);
$trenBulanan = array_fill(1, 12, 0); // Inisialisasi 12 bulan = 0
while ($row = $stmt->fetch()) {
    $trenBulanan[(int)$row['bulan']] = round($row['avg_skor'], 1);
}
$chartTrenLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
$chartTrenData = array_values($trenBulanan);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard KPI Karyawan - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <?php include 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header Title & Filter -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Dashboard KPI</h1>
                        <p class="text-gray-500 mt-1">Ringkasan Performa Karyawan Rumah Sakit</p>
                    </div>
                    <form class="flex flex-wrap items-end gap-3" method="GET">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
                            <select name="bulan" class="w-32 bg-gray-50 border border-gray-200 text-gray-700 py-2 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all">
                                <?php 
                                $months = ['01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'];
                                foreach($months as $k => $v) {
                                    $selected = ($bulan == $k) ? 'selected' : '';
                                    echo "<option value='$k' $selected>$v</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
                            <select name="tahun" class="w-32 bg-gray-50 border border-gray-200 text-gray-700 py-2 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all">
                                <?php 
                                $currentYear = date('Y');
                                for ($i = $currentYear; $i >= 2022; $i--) {
                                    $selected = ($tahun == $i) ? 'selected' : '';
                                    echo "<option value='$i' $selected>$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2 px-6 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                            <i data-lucide="filter" class="w-4 h-4"></i> Tampilkan
                        </button>
                    </form>
                </div>
                
                <!-- Stat Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-blue-200">
                            <i data-lucide="users" class="w-7 h-7"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Karyawan Dinilai</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= $karyawanDinilai ?> <span class="text-xs font-normal text-gray-500">/ <?= $totalKaryawan ?> Orang</span></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-teal-500 to-emerald-600 flex items-center justify-center text-white shadow-lg shadow-teal-200">
                            <i data-lucide="star" class="w-7 h-7"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Rata-rata Skor RS</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= $rataRataSkor ?> <span class="text-xs font-normal text-emerald-600 font-semibold"><?= $predikatSkor ?></span></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white shadow-lg shadow-amber-200">
                            <i data-lucide="award" class="w-7 h-7"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Unit Terbaik</p>
                            <h3 class="text-xl font-bold text-gray-900 line-clamp-1"><?= htmlspecialchars($unitTerbaik) ?></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-fuchsia-600 flex items-center justify-center text-white shadow-lg shadow-purple-200">
                            <i data-lucide="building" class="w-7 h-7"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Jumlah Unit</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= $jumlahUnit ?> <span class="text-xs font-normal text-gray-500">Unit</span></h3>
                        </div>
                    </div>
                </div>

                <!-- Charts Area -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Chart 1 -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-gray-800">Rata-rata Nilai per Unit</h3>
                        </div>
                        <div class="h-64 relative">
                            <canvas id="chartUnit"></canvas>
                        </div>
                    </div>
                    <!-- Chart 2 -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-gray-800">Tren Rata-rata Nilai <?= htmlspecialchars($tahun) ?></h3>
                        </div>
                        <div class="h-64 relative">
                            <canvas id="chartTren"></canvas>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
    <script>
        lucide.createIcons();

        // Chart Unit
        const ctxUnit = document.getElementById('chartUnit').getContext('2d');
        new Chart(ctxUnit, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartUnitLabels) ?>,
                datasets: [{
                    label: 'Skor Rata-rata',
                    data: <?= json_encode($chartUnitData) ?>,
                    backgroundColor: 'rgba(13, 148, 136, 0.8)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });

        // Chart Tren
        const ctxTren = document.getElementById('chartTren').getContext('2d');
        new Chart(ctxTren, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartTrenLabels) ?>,
                datasets: [{
                    label: 'Skor Rata-rata RS',
                    data: <?= json_encode($chartTrenData) ?>,
                    borderColor: 'rgba(99, 102, 241, 1)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
    </script>
</body>
</html>
