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

// ── Helper: hitung skor dari kpi_penilaian_harian per karyawan ───────────────
// Rata-rata nilai harian (1-5) per kriteria × 20 = skala 100, lalu × bobot/100
function hitungSkorHarian(PDO $pdo, int $kid, int $bln, int $thn): float {
    try {
        $st = $pdo->prepare("
            SELECT SUM(sub.nilai_akhir) as total
            FROM (
                SELECT AVG(ph.nilai)*20*(kr.bobot/100) AS nilai_akhir
                FROM kpi_penilaian_harian ph
                JOIN kpi_kriteria kr ON kr.id = ph.kriteria_id
                WHERE ph.karyawan_id=? AND ph.bulan=? AND ph.tahun=?
                GROUP BY ph.kriteria_id, kr.bobot
            ) sub
        ");
        $st->execute([$kid, $bln, $thn]);
        return round((float)($st->fetchColumn() ?? 0), 2);
    } catch (Exception $e) { return 0; }
}

// Total Karyawan (Semua)
try {
    $totalKaryawan = (int)$pdo->query("SELECT COUNT(*) FROM kpi_karyawan WHERE status = 'Aktif'")->fetchColumn();
} catch (PDOException $e) { $totalKaryawan = 0; }

// Jumlah Unit
try {
    $jumlahUnit = (int)$pdo->query("SELECT COUNT(DISTINCT unit) FROM kpi_karyawan WHERE status='Aktif'")->fetchColumn();
} catch (PDOException $e) { $jumlahUnit = 0; }

// ── Cek apakah ada data harian bulan ini ─────────────────────────────────────
try {
    $cekHarian = (int)$pdo->prepare("
        SELECT COUNT(DISTINCT karyawan_id) FROM kpi_penilaian_harian WHERE bulan=? AND tahun=?
    ")->execute([(int)$bulan, (int)$tahun]) ? $pdo->query("
        SELECT COUNT(DISTINCT karyawan_id) FROM kpi_penilaian_harian WHERE bulan=".((int)$bulan)." AND tahun=".((int)$tahun)
    )->fetchColumn() : 0;
} catch (PDOException $e) { $cekHarian = 0; }

// Query yang lebih bersih untuk cek harian
try {
    $stCH = $pdo->prepare("SELECT COUNT(DISTINCT karyawan_id) FROM kpi_penilaian_harian WHERE bulan=? AND tahun=?");
    $stCH->execute([(int)$bulan,(int)$tahun]);
    $cekHarian = (int)$stCH->fetchColumn();
} catch (PDOException $e) { $cekHarian = 0; }

// ── Hitung statistik dari sumber terbaik ────────────────────────────────────
$skorPerKaryawan = []; // [karyawan_id => ['nama','unit','jabatan','skor']]

if ($cekHarian > 0) {
    // Sumber: kpi_penilaian_harian
    try {
        $stK = $pdo->query("SELECT id,nama,unit,jabatan FROM kpi_karyawan WHERE status='Aktif'");
        foreach ($stK->fetchAll() as $k) {
            // Cek apakah karyawan ini punya data harian bulan ini
            $stCK = $pdo->prepare("SELECT COUNT(*) FROM kpi_penilaian_harian WHERE karyawan_id=? AND bulan=? AND tahun=?");
            $stCK->execute([$k['id'],(int)$bulan,(int)$tahun]);
            if ((int)$stCK->fetchColumn() > 0) {
                $skor = hitungSkorHarian($pdo, (int)$k['id'], (int)$bulan, (int)$tahun);
                $skorPerKaryawan[$k['id']] = ['nama'=>$k['nama'],'unit'=>$k['unit'],'jabatan'=>$k['jabatan'],'skor'=>$skor];
            }
        }
    } catch (PDOException $e) {}
} else {
    // Fallback: kpi_penilaian
    try {
        $stP = $pdo->prepare("
            SELECT p.karyawan_id,k.nama,k.unit,k.jabatan,p.total_skor as skor
            FROM kpi_penilaian p JOIN kpi_karyawan k ON k.id=p.karyawan_id
            WHERE p.bulan=? AND p.tahun=?
        ");
        $stP->execute([$bulan,$tahun]);
        foreach ($stP->fetchAll() as $r) {
            $skorPerKaryawan[$r['karyawan_id']] = ['nama'=>$r['nama'],'unit'=>$r['unit'],'jabatan'=>$r['jabatan'],'skor'=>(float)$r['skor']];
        }
    } catch (PDOException $e) {}
}

$karyawanDinilai = count($skorPerKaryawan);
$rataRataSkor    = $karyawanDinilai > 0 ? round(array_sum(array_column($skorPerKaryawan,'skor')) / $karyawanDinilai, 1) : 0;

$predikatSkor = "Kurang";
if ($rataRataSkor >= 90)     $predikatSkor = "Sangat Baik";
elseif ($rataRataSkor >= 75) $predikatSkor = "Baik";
elseif ($rataRataSkor >= 60) $predikatSkor = "Cukup";

// Unit Terbaik
$unitSkor = [];
foreach ($skorPerKaryawan as $r) {
    $unitSkor[$r['unit']][] = $r['skor'];
}
$unitTerbaik = '-';
$bestAvg = 0;
foreach ($unitSkor as $u => $skors) {
    $avg = array_sum($skors) / count($skors);
    if ($avg > $bestAvg) { $bestAvg = $avg; $unitTerbaik = $u; }
}

// Chart 1: Rata-rata per Unit
$chartUnitLabels = [];
$chartUnitData   = [];
foreach ($unitSkor as $u => $skors) {
    $chartUnitLabels[] = $u;
    $chartUnitData[]   = round(array_sum($skors)/count($skors), 1);
}

// Chart 2: Tren 12 bulan (dari harian jika ada, else kpi_penilaian)
$trenBulanan = array_fill(1, 12, 0);
try {
    // Cek apakah ada data harian tahun ini
    $stTH = $pdo->prepare("SELECT COUNT(*) FROM kpi_penilaian_harian WHERE tahun=?");
    $stTH->execute([(int)$tahun]);
    $adaHarianTahun = (int)$stTH->fetchColumn();

    if ($adaHarianTahun > 0) {
        // Hitung rata-rata skor per bulan dari harian
        $stTren = $pdo->prepare("
            SELECT ph.bulan,
                   AVG(sub.nilai_akhir) as avg_skor
            FROM (
                SELECT ph2.karyawan_id, ph2.bulan,
                       SUM(AVG(ph2.nilai)*20*(kr.bobot/100)) OVER (PARTITION BY ph2.karyawan_id,ph2.bulan) as nilai_akhir
                FROM kpi_penilaian_harian ph2
                JOIN kpi_kriteria kr ON kr.id=ph2.kriteria_id
                WHERE ph2.tahun=?
                GROUP BY ph2.karyawan_id, ph2.bulan, ph2.kriteria_id, kr.bobot
            ) sub
            JOIN kpi_penilaian_harian ph ON ph.karyawan_id=sub.karyawan_id AND ph.bulan=sub.bulan
            WHERE ph.tahun=?
            GROUP BY ph.bulan
        ");
        $stTren->execute([(int)$tahun,(int)$tahun]);
        // Fallback jika query kompleks gagal
    } else {
        throw new Exception('no harian');
    }
} catch (Exception $e) {
    // Fallback sederhana: kpi_penilaian
    try {
        $stTren = $pdo->prepare("SELECT bulan, AVG(total_skor) as avg_skor FROM kpi_penilaian WHERE tahun=? GROUP BY bulan ORDER BY bulan ASC");
        $stTren->execute([$tahun]);
        while ($row = $stTren->fetch()) {
            $trenBulanan[(int)$row['bulan']] = round($row['avg_skor'], 1);
        }
    } catch (Exception $ex) {}
    $stTren = null;
}

// Hitung tren bulanan dari data harian secara per-bulan (lebih reliable)
if ($adaHarianTahun > 0) {
    for ($b = 1; $b <= 12; $b++) {
        try {
            $stB = $pdo->prepare("SELECT COUNT(DISTINCT karyawan_id) FROM kpi_penilaian_harian WHERE bulan=? AND tahun=?");
            $stB->execute([$b,(int)$tahun]);
            if ((int)$stB->fetchColumn() === 0) continue;

            $stBS = $pdo->prepare("
                SELECT SUM(sub.na) / COUNT(DISTINCT sub.kid) as avg
                FROM (
                    SELECT ph.karyawan_id as kid, SUM(AVG(ph.nilai)*20*(kr.bobot/100)) as na
                    FROM kpi_penilaian_harian ph
                    JOIN kpi_kriteria kr ON kr.id=ph.kriteria_id
                    WHERE ph.bulan=? AND ph.tahun=?
                    GROUP BY ph.karyawan_id, ph.kriteria_id, kr.bobot
                ) sub
            ");
            $stBS->execute([$b,(int)$tahun]);
            $avg = (float)($stBS->fetchColumn() ?? 0);
            if ($avg > 0) $trenBulanan[$b] = round($avg, 1);
        } catch (Exception $e) {}
    }
}

$chartTrenLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
$chartTrenData   = array_values($trenBulanan);
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
                        <p class="text-gray-500 mt-1">Ringkasan Performa Karyawan Rumah Sakit
                            <?php if ($cekHarian > 0): ?>
                            <span class="ml-2 inline-block px-2 py-0.5 bg-teal-100 text-teal-700 rounded-full text-xs font-semibold">Data Harian</span>
                            <?php else: ?>
                            <span class="ml-2 inline-block px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs font-semibold">Data Bulanan</span>
                            <?php endif; ?>
                        </p>
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
