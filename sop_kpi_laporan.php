<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
if (!hasPermission('sop_view')) { header("Location: dashboard.php"); exit; }

$bulan       = $_GET['bulan']       ?? date('m');
$tahun       = $_GET['tahun']       ?? date('Y');
$karyawan_id = $_GET['karyawan_id'] ?? '';
$tab         = $_GET['tab']         ?? 'karyawan';

$months = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
           '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];

// ── Helper: konversi nilai harian (1-5) ke skala 0-100 ──────────────────────
// Nilai harian 1-5 → ×20 = 20-100
function nilaiHarianKeSkala100(float $rata): float {
    return round($rata * 20, 2);
}

// ── Helper: predikat berdasarkan skor ────────────────────────────────────────
function getPredikat(float $skor): array {
    if ($skor >= 90) return ['Sangat Baik', 'text-emerald-700', 'bg-emerald-100'];
    if ($skor >= 75) return ['Baik',        'text-blue-700',    'bg-blue-100'];
    if ($skor >= 60) return ['Cukup',       'text-yellow-700',  'bg-yellow-100'];
    return ['Kurang', 'text-red-700', 'bg-red-100'];
}

// ── Fetch dropdown karyawan ──────────────────────────────────────────────────
try {
    $karyawanList = $pdo->query("SELECT id,nama,jabatan,unit FROM kpi_karyawan WHERE status='Aktif' ORDER BY nama ASC")->fetchAll();
} catch (PDOException $e) { $karyawanList = []; }

// ── DATA LAPORAN PER KARYAWAN ────────────────────────────────────────────────
$detailKaryawan = null;
$nilaiList      = [];   // [kriteria_id => [nama_indikator, bobot, kategori, nilai_rata, nilai_akhir]]
$total_akhir    = 0;
$sumberData     = '';   // 'harian' | 'bulanan'

if ($karyawan_id) {
    // Info karyawan
    try {
        $s = $pdo->prepare("SELECT * FROM kpi_karyawan WHERE id=?");
        $s->execute([$karyawan_id]);
        $detailKaryawan = $s->fetch();
    } catch (PDOException $e) {}

    if ($detailKaryawan) {
        // ── Coba ambil dari kpi_penilaian_harian dulu ────────────────────────
        try {
            $stH = $pdo->prepare("
                SELECT
                    ph.kriteria_id,
                    kr.nama_indikator,
                    kr.bobot,
                    kr.kategori,
                    AVG(ph.nilai) AS rata_harian,
                    COUNT(ph.id)  AS jumlah_hari
                FROM kpi_penilaian_harian ph
                JOIN kpi_kriteria kr ON kr.id = ph.kriteria_id
                WHERE ph.karyawan_id = ?
                  AND ph.bulan = ?
                  AND ph.tahun = ?
                GROUP BY ph.kriteria_id, kr.nama_indikator, kr.bobot, kr.kategori
                ORDER BY kr.kategori ASC, kr.id ASC
            ");
            $stH->execute([$karyawan_id, (int)$bulan, (int)$tahun]);
            $rows = $stH->fetchAll();
        } catch (PDOException $e) { $rows = []; }

        // Ambil semua kriteria agar indikator tanpa nilai harian tetap muncul
        try {
            $allKriteria = $pdo->query("SELECT * FROM kpi_kriteria ORDER BY kategori ASC, id ASC")->fetchAll();
        } catch (PDOException $e) { $allKriteria = []; }

        // Index hasil harian
        $harianIdx = [];
        foreach ($rows as $r) $harianIdx[$r['kriteria_id']] = $r;

        // Cek apakah ada nilai harian
        if (!empty($rows)) {
            $sumberData = 'harian';
            $total_akhir = 0;
            foreach ($allKriteria as $kr) {
                if (isset($harianIdx[$kr['id']])) {
                    $r    = $harianIdx[$kr['id']];
                    $rata = (float)$r['rata_harian'];
                } else {
                    // Indikator tidak ada nilai harian → nilai 0
                    $rata = 0;
                }
                $nilai100   = nilaiHarianKeSkala100($rata);
                $nilai_akhir = round($nilai100 * ($kr['bobot'] / 100), 2);
                $total_akhir += $nilai_akhir;
                $nilaiList[] = [
                    'nama_indikator' => $kr['nama_indikator'],
                    'kategori'       => $kr['kategori'],
                    'bobot'          => $kr['bobot'],
                    'nilai'          => $nilai100,
                    'nilai_harian'   => $rata,
                    'nilai_akhir'    => $nilai_akhir,
                ];
            }
        } else {
            // ── Fallback: ambil dari kpi_penilaian_detail ────────────────────
            try {
                $stP = $pdo->prepare("
                    SELECT p.id as penilaian_id, p.total_skor, p.catatan
                    FROM kpi_penilaian p
                    WHERE p.karyawan_id=? AND p.bulan=? AND p.tahun=?
                    LIMIT 1
                ");
                $stP->execute([$karyawan_id, $bulan, $tahun]);
                $penilaian = $stP->fetch();
            } catch (PDOException $e) { $penilaian = null; }

            if ($penilaian) {
                $sumberData = 'bulanan';
                try {
                    $stD = $pdo->prepare("
                        SELECT kr.nama_indikator, kr.bobot, kr.kategori, d.nilai
                        FROM kpi_penilaian_detail d
                        JOIN kpi_kriteria kr ON d.kriteria_id = kr.id
                        WHERE d.penilaian_id = ?
                        ORDER BY kr.kategori ASC, kr.id ASC
                    ");
                    $stD->execute([$penilaian['penilaian_id']]);
                    $detailRows = $stD->fetchAll();
                } catch (PDOException $e) { $detailRows = []; }

                $total_akhir = 0;
                foreach ($detailRows as $d) {
                    $nilai_akhir  = round((float)$d['nilai'] * ($d['bobot'] / 100), 2);
                    $total_akhir += $nilai_akhir;
                    $nilaiList[] = [
                        'nama_indikator' => $d['nama_indikator'],
                        'kategori'       => $d['kategori'],
                        'bobot'          => $d['bobot'],
                        'nilai'          => $d['nilai'],
                        'nilai_harian'   => null,
                        'nilai_akhir'    => $nilai_akhir,
                    ];
                }
            }
        }
    }
}

// ── DATA REKAP DIREKSI ───────────────────────────────────────────────────────
// Hitung dari penilaian_harian (prioritas) + fallback ke kpi_penilaian
try {
    // Karyawan yang punya data harian bulan ini
    $stRekap = $pdo->prepare("
        SELECT
            ph.karyawan_id,
            k.nama, k.unit, k.jabatan,
            SUM(ph.nilai * kr.bobot / 100 / 5 * 20) / COUNT(DISTINCT ph.hari) AS total_skor_raw,
            COUNT(DISTINCT ph.hari) AS jumlah_hari_dinilai
        FROM kpi_penilaian_harian ph
        JOIN kpi_karyawan k ON k.id = ph.karyawan_id
        JOIN kpi_kriteria kr ON kr.id = ph.kriteria_id
        WHERE ph.bulan=? AND ph.tahun=?
        GROUP BY ph.karyawan_id, k.nama, k.unit, k.jabatan
        ORDER BY total_skor_raw DESC
    ");
    $stRekap->execute([(int)$bulan, (int)$tahun]);
    $rekapHarian = $stRekap->fetchAll();
} catch (PDOException $e) { $rekapHarian = []; }

// Hitung rata-rata skor per karyawan dari harian secara benar
// = rata-rata per kriteria → ke skala 100 → weighted by bobot
$rekapDireksi = [];
if (!empty($rekapHarian)) {
    // Hitung ulang dengan query yang lebih akurat per karyawan
    foreach ($rekapHarian as $rk) {
        try {
            $stCalc = $pdo->prepare("
                SELECT SUM(sub.nilai_akhir) as total
                FROM (
                    SELECT kr.bobot, AVG(ph.nilai)*20*(kr.bobot/100) AS nilai_akhir
                    FROM kpi_penilaian_harian ph
                    JOIN kpi_kriteria kr ON kr.id = ph.kriteria_id
                    WHERE ph.karyawan_id=? AND ph.bulan=? AND ph.tahun=?
                    GROUP BY ph.kriteria_id, kr.bobot
                ) sub
            ");
            $stCalc->execute([$rk['karyawan_id'], (int)$bulan, (int)$tahun]);
            $tot = (float)($stCalc->fetchColumn() ?? 0);
        } catch (PDOException $e) { $tot = 0; }
        $rekapDireksi[] = [
            'karyawan_id' => $rk['karyawan_id'],
            'nama'        => $rk['nama'],
            'unit'        => $rk['unit'],
            'jabatan'     => $rk['jabatan'],
            'total_skor'  => round($tot, 2),
        ];
    }
    // Urutkan descending
    usort($rekapDireksi, fn($a,$b) => $b['total_skor'] <=> $a['total_skor']);
} else {
    // Fallback ke kpi_penilaian
    try {
        $stFB = $pdo->prepare("
            SELECT p.karyawan_id, k.nama, k.unit, k.jabatan, p.total_skor
            FROM kpi_penilaian p JOIN kpi_karyawan k ON p.karyawan_id=k.id
            WHERE p.bulan=? AND p.tahun=?
            ORDER BY p.total_skor DESC
        ");
        $stFB->execute([$bulan, $tahun]);
        $rekapDireksi = $stFB->fetchAll();
    } catch (PDOException $e) { $rekapDireksi = []; }
}

$avg_skor = 0;
if (count($rekapDireksi) > 0) {
    $avg_skor = round(array_sum(array_column($rekapDireksi, 'total_skor')) / count($rekapDireksi), 2);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan KPI - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 flex flex-col relative h-screen overflow-hidden">
        <?php include 'includes/header.php'; ?>

        <div class="flex-1 p-6 overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- Page Header -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Laporan KPI Karyawan</h1>
                        <p class="text-gray-500 text-sm mt-1">Rekapitulasi Penilaian Kinerja per Individu & Keseluruhan</p>
                    </div>
                </div>

                <!-- Tab -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 inline-flex gap-1 no-print">
                    <button onclick="switchTab('tabKaryawan')" id="btnKaryawan"
                        class="px-6 py-2.5 rounded-xl font-bold text-sm transition-all <?= $tab!=='direksi' ? 'bg-teal-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>">
                        Laporan Per Karyawan
                    </button>
                    <button onclick="switchTab('tabDireksi')" id="btnDireksi"
                        class="px-6 py-2.5 rounded-xl font-bold text-sm transition-all <?= $tab==='direksi' ? 'bg-teal-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>">
                        Laporan Direksi
                    </button>
                </div>

                <!-- ══ TAB: PER KARYAWAN ═══════════════════════════════════════ -->
                <div id="tabKaryawan" class="space-y-6 <?= $tab==='direksi' ? 'hidden' : '' ?>">
                    <!-- Filter -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 no-print">
                        <form method="GET" class="flex flex-wrap gap-4 items-end">
                            <div class="flex-1 min-w-[200px]">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih Karyawan</label>
                                <select name="karyawan_id" class="w-full border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50 text-sm">
                                    <option value="">-- Pilih Karyawan --</option>
                                    <?php foreach($karyawanList as $k): ?>
                                    <option value="<?= $k['id'] ?>" <?= $karyawan_id==$k['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($k['nama']) ?> — <?= htmlspecialchars($k['jabatan']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Bulan</label>
                                <select name="bulan" class="border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50 text-sm">
                                    <?php foreach($months as $k=>$v): ?>
                                    <option value="<?=$k?>" <?=$bulan==$k?'selected':''?>><?=$v?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun</label>
                                <select name="tahun" class="border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50 text-sm">
                                    <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
                                    <option value="<?=$y?>" <?=$tahun==$y?'selected':''?>><?=$y?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <button type="submit" class="px-6 py-2.5 bg-gray-800 text-white rounded-xl hover:bg-gray-900 font-bold text-sm flex items-center gap-2">
                                <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
                            </button>
                        </form>
                    </div>

                    <?php if ($karyawan_id && $detailKaryawan): ?>
                    <div id="printArea" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <!-- Raport Header -->
                        <div class="p-8 border-b border-gray-100">
                            <div class="text-center mb-8">
                                <h2 class="text-xl font-bold text-gray-900 tracking-wide">RAPORT KPI KARYAWAN</h2>
                                <p class="text-gray-500 text-sm mt-1">Periode: <?= $months[sprintf('%02d',$bulan)] ?? $months[$bulan] ?? '' ?> <?= $tahun ?></p>
                                <?php if ($sumberData==='harian'): ?>
                                <span class="inline-block mt-2 px-3 py-1 bg-teal-100 text-teal-700 rounded-full text-xs font-semibold">
                                    Data dari Penilaian Harian
                                </span>
                                <?php elseif ($sumberData==='bulanan'): ?>
                                <span class="inline-block mt-2 px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                                    Data dari Penilaian Bulanan
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- Info Karyawan -->
                            <div class="grid grid-cols-2 gap-6 mb-8 p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <div class="space-y-2">
                                    <div class="flex text-sm"><span class="w-28 font-semibold text-gray-500">NAMA</span><span class="font-bold text-gray-900">: <?= htmlspecialchars($detailKaryawan['nama']) ?></span></div>
                                    <div class="flex text-sm"><span class="w-28 font-semibold text-gray-500">NIK</span><span class="font-bold text-gray-900">: <?= htmlspecialchars($detailKaryawan['nik']) ?></span></div>
                                    <div class="flex text-sm"><span class="w-28 font-semibold text-gray-500">UNIT</span><span class="font-bold text-gray-900">: <?= htmlspecialchars($detailKaryawan['unit']) ?></span></div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex text-sm"><span class="w-28 font-semibold text-gray-500">JABATAN</span><span class="font-bold text-gray-900">: <?= htmlspecialchars($detailKaryawan['jabatan']) ?></span></div>
                                    <div class="flex text-sm"><span class="w-28 font-semibold text-gray-500">PERIODE</span><span class="font-bold text-gray-900">: <?= ($months[sprintf('%02d',(int)$bulan)] ?? $bulan) ?> <?= $tahun ?></span></div>
                                </div>
                            </div>

                            <?php if (!empty($nilaiList)): ?>
                            <!-- Tabel Nilai -->
                            <?php
                            $currentKat = '';
                            $no = 1;
                            ?>
                            <table class="w-full border-collapse border border-gray-300 mb-6 text-sm">
                                <thead>
                                    <tr class="bg-gray-800 text-white">
                                        <th class="border border-gray-600 p-3 text-center w-10">No</th>
                                        <th class="border border-gray-600 p-3 text-left">Indikator Penilaian</th>
                                        <th class="border border-gray-600 p-3 text-center w-20">Bobot (%)</th>
                                        <th class="border border-gray-600 p-3 text-center w-24">Nilai (0-100)</th>
                                        <th class="border border-gray-600 p-3 text-center w-24">Nilai Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($nilaiList as $n):
                                    if ($n['kategori'] !== $currentKat):
                                        $currentKat = $n['kategori'];
                                ?>
                                    <tr class="bg-teal-700 text-white">
                                        <td colspan="5" class="border border-teal-600 p-2 px-3 font-bold text-xs tracking-widest uppercase">
                                            <?= htmlspecialchars($currentKat) ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                    <tr class="<?= $no%2===0 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-teal-50">
                                        <td class="border border-gray-200 p-2.5 text-center text-gray-500"><?= $no++ ?></td>
                                        <td class="border border-gray-200 p-2.5 text-gray-800 font-medium"><?= htmlspecialchars($n['nama_indikator']) ?></td>
                                        <td class="border border-gray-200 p-2.5 text-center font-semibold text-gray-700"><?= number_format($n['bobot'],1) ?>%</td>
                                        <td class="border border-gray-200 p-2.5 text-center">
                                            <?php if ((float)$n['nilai'] > 0): ?>
                                                <span class="font-bold text-gray-900"><?= number_format((float)$n['nilai'],1) ?></span>
                                                <?php if ($sumberData==='harian' && $n['nilai_harian'] !== null): ?>
                                                <span class="text-xs text-gray-400 ml-1">(rata: <?= number_format($n['nilai_harian'],2) ?>)</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-300">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="border border-gray-200 p-2.5 text-center font-bold text-teal-700"><?= number_format($n['nilai_akhir'],2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <?php
                                    [$predikat, $txtClass, $bgClass] = getPredikat($total_akhir);
                                    ?>
                                    <tr class="bg-gray-100 font-bold">
                                        <td colspan="4" class="border border-gray-300 p-3 text-right text-gray-800 text-sm">TOTAL SKOR AKHIR</td>
                                        <td class="border border-gray-300 p-3 text-center">
                                            <span class="text-xl font-black text-teal-700"><?= number_format($total_akhir,2) ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="border border-gray-300 p-3 text-center">
                                            <span class="inline-block px-4 py-1.5 rounded-full font-bold text-sm <?= $bgClass ?> <?= $txtClass ?>">
                                                Predikat: <?= $predikat ?>
                                            </span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>

                            <!-- Keterangan skala -->
                            <div class="grid grid-cols-4 gap-3 mb-8 text-xs text-center no-print">
                                <div class="p-2 rounded-lg bg-emerald-50 border border-emerald-200">
                                    <div class="font-bold text-emerald-700">≥ 90</div>
                                    <div class="text-emerald-600">Sangat Baik</div>
                                </div>
                                <div class="p-2 rounded-lg bg-blue-50 border border-blue-200">
                                    <div class="font-bold text-blue-700">75 – 89</div>
                                    <div class="text-blue-600">Baik</div>
                                </div>
                                <div class="p-2 rounded-lg bg-yellow-50 border border-yellow-200">
                                    <div class="font-bold text-yellow-700">60 – 74</div>
                                    <div class="text-yellow-600">Cukup</div>
                                </div>
                                <div class="p-2 rounded-lg bg-red-50 border border-red-200">
                                    <div class="font-bold text-red-700">< 60</div>
                                    <div class="text-red-600">Kurang</div>
                                </div>
                            </div>

                            <!-- Tanda Tangan -->
                            <div class="flex justify-between items-end mt-10 pt-6 border-t border-gray-200">
                                <div class="text-center">
                                    <p class="text-sm text-gray-500 mb-16">Karyawan Ybs,</p>
                                    <div class="border-b-2 border-gray-800 w-48 pb-1">
                                        <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($detailKaryawan['nama']) ?></p>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-500 mb-16">Evaluator / Penilai,</p>
                                    <div class="border-b-2 border-gray-800 w-48 pb-1">
                                        <p class="font-bold text-gray-800 text-sm">( ........................................ )</p>
                                    </div>
                                </div>
                            </div>

                            <?php else: ?>
                            <div class="text-center py-16 text-gray-400">
                                <i data-lucide="file-x" class="w-16 h-16 mx-auto mb-4 opacity-20"></i>
                                <p class="font-medium text-gray-500">Belum ada data penilaian untuk karyawan ini di periode ini.</p>
                                <a href="sop_kpi_penilaian_harian.php?karyawan_id=<?= $karyawan_id ?>&bulan=<?= (int)$bulan ?>&tahun=<?= $tahun ?>"
                                   class="inline-block mt-4 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-semibold hover:bg-teal-700">
                                    Buka Form Penilaian Harian
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Footer Aksi -->
                        <?php if (!empty($nilaiList)): ?>
                        <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3 no-print">
                            <a href="sop_kpi_penilaian_harian.php?karyawan_id=<?= $karyawan_id ?>&bulan=<?= (int)$bulan ?>&tahun=<?= $tahun ?>"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-50 flex items-center gap-2">
                                <i data-lucide="edit" class="w-4 h-4"></i> Edit Penilaian
                            </a>
                            <button onclick="window.print()"
                                class="px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 flex items-center gap-2">
                                <i data-lucide="printer" class="w-4 h-4"></i> Cetak Laporan
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ══ TAB: DIREKSI ═══════════════════════════════════════════ -->
                <div id="tabDireksi" class="space-y-6 <?= $tab!=='direksi' ? 'hidden' : '' ?>">
                    <!-- Filter -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 no-print">
                        <form method="GET" class="flex flex-wrap gap-4 items-end">
                            <input type="hidden" name="tab" value="direksi">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Bulan</label>
                                <select name="bulan" class="border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50 text-sm">
                                    <?php foreach($months as $k=>$v): ?>
                                    <option value="<?=$k?>" <?=$bulan==$k?'selected':''?>><?=$v?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun</label>
                                <select name="tahun" class="border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50 text-sm">
                                    <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
                                    <option value="<?=$y?>" <?=$tahun==$y?'selected':''?>><?=$y?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <button type="submit" class="px-6 py-2.5 bg-gray-800 text-white rounded-xl hover:bg-gray-900 font-bold text-sm flex items-center gap-2">
                                <i data-lucide="search" class="w-4 h-4"></i> Tampilkan Rekap
                            </button>
                        </form>
                    </div>

                    <!-- Stat -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-teal-100 flex items-center justify-center">
                                <i data-lucide="users" class="w-6 h-6 text-teal-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-500">Karyawan Dinilai</p>
                                <h3 class="text-2xl font-bold text-gray-900"><?= count($rekapDireksi) ?> Orang</h3>
                            </div>
                        </div>
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                <i data-lucide="bar-chart-2" class="w-6 h-6 text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-500">Rata-rata Skor RS</p>
                                <h3 class="text-2xl font-bold text-gray-900"><?= number_format($avg_skor,2) ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Rekap -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="printArea">
                        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50 no-print">
                            <h3 class="font-bold text-gray-800">Rekapitulasi <?= $months[$bulan] ?? $bulan ?> <?= $tahun ?></h3>
                            <button onclick="window.print()" class="text-sm border border-teal-300 text-teal-700 px-3 py-1.5 rounded-lg bg-white flex items-center gap-1 hover:bg-teal-50">
                                <i data-lucide="printer" class="w-4 h-4"></i> Cetak
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100 border-b border-gray-200">
                                    <tr>
                                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-center w-16">Rank</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-left">Nama Karyawan</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-left">Unit</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-left">Jabatan</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-right w-32">Skor</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-center w-32">Predikat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($rekapDireksi)): ?>
                                    <tr>
                                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                                            Belum ada data penilaian untuk bulan ini.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php $rank=1; foreach ($rekapDireksi as $r):
                                        [$pred,$tcls,$bcls] = getPredikat((float)$r['total_skor']);
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 text-center">
                                            <?php if ($rank<=3): ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-bold bg-amber-100 text-amber-700"><?=$rank?></span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-bold bg-gray-100 text-gray-600"><?=$rank?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3 font-semibold text-gray-800"><?= htmlspecialchars($r['nama']) ?></td>
                                        <td class="px-5 py-3 text-gray-500 text-xs"><?= htmlspecialchars($r['unit']) ?></td>
                                        <td class="px-5 py-3 text-gray-500 text-xs"><?= htmlspecialchars($r['jabatan']) ?></td>
                                        <td class="px-5 py-3 text-right font-black text-teal-700 text-base"><?= number_format((float)$r['total_skor'],2) ?></td>
                                        <td class="px-5 py-3 text-center">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $bcls ?> <?= $tcls ?>"><?= $pred ?></span>
                                        </td>
                                    </tr>
                                    <?php $rank++; endforeach; ?>
                                    <?php endif; ?>
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

        function switchTab(id) {
            ['tabKaryawan','tabDireksi'].forEach(t => document.getElementById(t).classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            ['btnKaryawan','btnDireksi'].forEach(b => {
                document.getElementById(b).className = 'px-6 py-2.5 rounded-xl font-bold text-sm transition-all text-gray-500 hover:bg-gray-50';
            });
            const btnMap = {tabKaryawan:'btnKaryawan', tabDireksi:'btnDireksi'};
            document.getElementById(btnMap[id]).className = 'px-6 py-2.5 rounded-xl font-bold text-sm transition-all bg-teal-600 text-white shadow-sm';
        }
        <?php if ($tab==='direksi'): ?>switchTab('tabDireksi');<?php endif; ?>
    </script>
</body>
</html>
