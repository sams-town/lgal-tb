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

// Filter variables
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$karyawan_id = $_GET['karyawan_id'] ?? '';

// Fetch Dropdowns
$stmtKaryawan = $pdo->query("SELECT id, nama, jabatan FROM kpi_karyawan ORDER BY nama ASC");
$karyawanList = $stmtKaryawan->fetchAll();

// 1. Rekap Per Karyawan
$detailKaryawan = null;
$nilaiList = [];
$total_skor = 0;
$catatan = '';

if ($karyawan_id) {
    $stmt = $pdo->prepare("SELECT k.*, p.total_skor, p.catatan, p.id as penilaian_id 
                           FROM kpi_karyawan k 
                           LEFT JOIN kpi_penilaian p ON k.id = p.karyawan_id AND p.bulan = ? AND p.tahun = ?
                           WHERE k.id = ?");
    $stmt->execute([$bulan, $tahun, $karyawan_id]);
    $detailKaryawan = $stmt->fetch();
    
    if ($detailKaryawan && $detailKaryawan['penilaian_id']) {
        $total_skor = $detailKaryawan['total_skor'];
        $catatan = $detailKaryawan['catatan'];
        
        $stmtNilai = $pdo->prepare("SELECT kr.nama_indikator, kr.bobot, d.nilai 
                                    FROM kpi_penilaian_detail d
                                    JOIN kpi_kriteria kr ON d.kriteria_id = kr.id
                                    WHERE d.penilaian_id = ?");
        $stmtNilai->execute([$detailKaryawan['penilaian_id']]);
        $nilaiList = $stmtNilai->fetchAll();
    }
}

// 2. Rekap Direksi
$stmtRekap = $pdo->prepare("SELECT p.karyawan_id, k.nama, k.unit, k.jabatan, p.total_skor 
                            FROM kpi_penilaian p
                            JOIN kpi_karyawan k ON p.karyawan_id = k.id
                            WHERE p.bulan = ? AND p.tahun = ?
                            ORDER BY p.total_skor DESC");
$stmtRekap->execute([$bulan, $tahun]);
$rekapDireksi = $stmtRekap->fetchAll();

$avg_skor = 0;
if (count($rekapDireksi) > 0) {
    $sum = array_sum(array_column($rekapDireksi, 'total_skor'));
    $avg_skor = number_format($sum / count($rekapDireksi), 2);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan KPI - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 flex flex-col relative h-screen overflow-hidden">
        <?php include 'includes/header.php'; ?>
        
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Laporan KPI Karyawan</h1>
                        <p class="text-gray-500 mt-1">Rekapitulasi Penilaian Kinerja per Individu & Keseluruhan</p>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-2 inline-flex gap-2 w-full md:w-auto">
                    <button onclick="switchTab('tabKaryawan')" id="btnKaryawan" class="flex-1 md:flex-none px-6 py-2.5 rounded-xl font-bold text-sm bg-teal-600 text-white shadow-sm transition-all">Laporan Per Karyawan</button>
                    <button onclick="switchTab('tabDireksi')" id="btnDireksi" class="flex-1 md:flex-none px-6 py-2.5 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 transition-all">Laporan Direksi</button>
                </div>

                <!-- TAB: Laporan Per Karyawan -->
                <div id="tabKaryawan" class="space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                            <div class="w-full md:w-1/3">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih Karyawan</label>
                                <select name="karyawan_id" class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                    <option value="">-- Pilih Karyawan --</option>
                                    <?php foreach($karyawanList as $k): ?>
                                    <option value="<?= $k['id'] ?>" <?= ($karyawan_id == $k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="w-full md:w-1/4">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Bulan</label>
                                <select name="bulan" class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                    <?php 
                                    $months = ['01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'];
                                    foreach($months as $k => $v) {
                                        $sel = ($bulan == $k) ? 'selected' : '';
                                        echo "<option value='$k' $sel>$v</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="w-full md:w-1/4">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun</label>
                                <select name="tahun" class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                    <?php 
                                    $curY = date('Y');
                                    for($i = $curY; $i >= $curY - 2; $i--) {
                                        $sel = ($tahun == $i) ? 'selected' : '';
                                        echo "<option value='$i' $sel>$i</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="w-full md:w-auto px-6 py-2.5 bg-gray-800 text-white rounded-xl hover:bg-gray-900 font-bold shadow-md transition-colors flex items-center justify-center gap-2">
                                <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
                            </button>
                        </form>
                    </div>

                    <?php if($karyawan_id && $detailKaryawan): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="printAreaKaryawan">
                        <div class="p-8">
                            <div class="text-center mb-8 pb-8 border-b border-gray-200">
                                <h2 class="text-2xl font-bold text-gray-900">RAPORT KPI KARYAWAN</h2>
                                <p class="text-gray-600 mt-1">Periode: <?= $months[$bulan] ?> <?= $tahun ?></p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                <div class="space-y-3">
                                    <div class="flex"><span class="w-32 font-semibold text-gray-600">NAMA</span> <span class="font-bold text-gray-900">: <?= htmlspecialchars($detailKaryawan['nama']) ?></span></div>
                                    <div class="flex"><span class="w-32 font-semibold text-gray-600">NIK</span> <span class="font-bold text-gray-900">: <?= htmlspecialchars($detailKaryawan['nik']) ?></span></div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex"><span class="w-32 font-semibold text-gray-600">JABATAN</span> <span class="font-bold text-gray-900">: <?= htmlspecialchars($detailKaryawan['jabatan']) ?></span></div>
                                    <div class="flex"><span class="w-32 font-semibold text-gray-600">UNIT</span> <span class="font-bold text-gray-900">: <?= htmlspecialchars($detailKaryawan['unit']) ?></span></div>
                                </div>
                            </div>

                            <?php if($detailKaryawan['penilaian_id']): ?>
                            <table class="w-full border-collapse border border-gray-200 mb-8">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-200 p-3 text-left font-bold text-gray-800">No</th>
                                        <th class="border border-gray-200 p-3 text-left font-bold text-gray-800">Indikator Penilaian</th>
                                        <th class="border border-gray-200 p-3 text-center font-bold text-gray-800">Bobot</th>
                                        <th class="border border-gray-200 p-3 text-center font-bold text-gray-800">Nilai</th>
                                        <th class="border border-gray-200 p-3 text-center font-bold text-gray-800">Nilai Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no=1; 
                                    $total_akhir = 0;
                                    foreach($nilaiList as $n): 
                                        $nilai_akhir = $n['nilai'] * ($n['bobot']/100);
                                        $total_akhir += $nilai_akhir;
                                    ?>
                                    <tr>
                                        <td class="border border-gray-200 p-3 text-center"><?= $no++ ?></td>
                                        <td class="border border-gray-200 p-3"><?= htmlspecialchars($n['nama_indikator']) ?></td>
                                        <td class="border border-gray-200 p-3 text-center"><?= $n['bobot'] ?>%</td>
                                        <td class="border border-gray-200 p-3 text-center"><?= $n['nilai'] ?></td>
                                        <td class="border border-gray-200 p-3 text-center font-semibold text-teal-600"><?= number_format($nilai_akhir, 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-teal-50">
                                        <td colspan="4" class="border border-gray-200 p-3 text-right font-bold text-gray-800">TOTAL SKOR AKHIR</td>
                                        <td class="border border-gray-200 p-3 text-center font-bold text-teal-700 text-lg"><?= number_format($total_akhir, 2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="mb-8">
                                <h4 class="font-bold text-gray-800 mb-2">Catatan Evaluator:</h4>
                                <div class="p-4 bg-gray-50 rounded-xl border border-gray-200 text-gray-700 min-h-[100px]">
                                    <?= nl2br(htmlspecialchars($catatan)) ?>
                                </div>
                            </div>

                            <div class="flex justify-between items-end mt-12 pt-8">
                                <div class="text-center w-48">
                                    <p class="mb-16 text-sm text-gray-600">Karyawan,</p>
                                    <p class="font-bold border-b border-gray-900 pb-1"><?= htmlspecialchars($detailKaryawan['nama']) ?></p>
                                </div>
                                <div class="text-center w-48">
                                    <p class="mb-16 text-sm text-gray-600">Evaluator / Penilai,</p>
                                    <p class="font-bold border-b border-gray-900 pb-1">(..................................)</p>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-12 text-gray-500">
                                <i data-lucide="file-warning" class="w-16 h-16 mx-auto text-gray-300 mb-4"></i>
                                <p>Belum ada data penilaian untuk karyawan ini di bulan dan tahun yang dipilih.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if($detailKaryawan['penilaian_id']): ?>
                        <div class="px-8 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                            <button onclick="window.print()" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-md hover:bg-blue-700 transition-colors flex items-center gap-2">
                                <i data-lucide="printer" class="w-4 h-4"></i> Cetak Laporan
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- TAB: Laporan Direksi -->
                <div id="tabDireksi" class="space-y-6 hidden">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                            <input type="hidden" name="tab" value="direksi">
                            <div class="w-full md:w-1/4">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Bulan</label>
                                <select name="bulan" class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                    <?php 
                                    $months = ['01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'];
                                    foreach($months as $k => $v) {
                                        $sel = ($bulan == $k) ? 'selected' : '';
                                        echo "<option value='$k' $sel>$v</option>";
                                    } ?>
                                </select>
                            </div>
                            <div class="w-full md:w-1/4">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun</label>
                                <select name="tahun" class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                    <?php 
                                    $curY = date('Y');
                                    for($i = $curY; $i >= $curY - 2; $i--) {
                                        $sel = ($tahun == $i) ? 'selected' : '';
                                        echo "<option value='$i' $sel>$i</option>";
                                    } ?>
                                </select>
                            </div>
                            <button type="submit" class="w-full md:w-auto px-6 py-2.5 bg-gray-800 text-white rounded-xl hover:bg-gray-900 font-bold shadow-md transition-colors flex items-center justify-center gap-2">
                                <i data-lucide="search" class="w-4 h-4"></i> Tampilkan Rekap
                            </button>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-teal-100 text-teal-600 flex items-center justify-center">
                                <i data-lucide="users" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Karyawan Dinilai</p>
                                <h3 class="text-2xl font-bold text-gray-900"><?= count($rekapDireksi) ?> Orang</h3>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                                <i data-lucide="activity" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Rata-rata Skor RS</p>
                                <h3 class="text-2xl font-bold text-gray-900"><?= $avg_skor ?></h3>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                            <h3 class="font-bold text-gray-800">Tabel Rekapitulasi - <?= $months[$bulan] ?> <?= $tahun ?></h3>
                            <button onclick="window.print()" class="text-sm text-teal-600 font-semibold hover:text-teal-700 flex items-center gap-1 border border-teal-200 px-3 py-1.5 rounded-lg bg-white">
                                <i data-lucide="printer" class="w-4 h-4"></i> Cetak
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-gray-100 border-b border-gray-200">
                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Peringkat</th>
                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama Karyawan</th>
                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit</th>
                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Jabatan</th>
                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Skor Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if(empty($rekapDireksi)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">Data rekapitulasi belum tersedia untuk bulan ini.</td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $rank = 1;
                                    foreach($rekapDireksi as $r): 
                                        $isTop = ($rank <= 3);
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <?php if($isTop): ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-bold bg-amber-100 text-amber-700"><?= $rank ?></span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-bold bg-gray-100 text-gray-700"><?= $rank ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 font-semibold text-gray-800"><?= htmlspecialchars($r['nama']) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($r['unit']) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($r['jabatan']) ?></td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="font-bold text-teal-600 text-lg"><?= number_format($r['total_skor'], 2) ?></span>
                                        </td>
                                    </tr>
                                    <?php 
                                    $rank++;
                                    endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
    <script>
        lucide.createIcons();

        function switchTab(tabId) {
            // Hide all tabs
            $('#tabKaryawan, #tabDireksi').addClass('hidden');
            // Show selected tab
            $('#' + tabId).removeClass('hidden');

            // Update buttons styling
            $('#btnKaryawan, #btnDireksi').removeClass('bg-teal-600 text-white').addClass('text-gray-600 hover:bg-gray-50');
            
            if (tabId === 'tabKaryawan') {
                $('#btnKaryawan').removeClass('text-gray-600 hover:bg-gray-50').addClass('bg-teal-600 text-white');
            } else {
                $('#btnDireksi').removeClass('text-gray-600 hover:bg-gray-50').addClass('bg-teal-600 text-white');
            }
        }
        
        <?php if(isset($_GET['tab']) && $_GET['tab'] === 'direksi'): ?>
            switchTab('tabDireksi');
        <?php endif; ?>
    </script>
</body>
</html>
