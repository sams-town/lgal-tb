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

// --- Simpan Penilaian Harian ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $karyawan_id = (int)$_POST['karyawan_id'];
    $bulan       = (int)$_POST['bulan'];
    $tahun       = (int)$_POST['tahun'];
    $nilai_arr   = $_POST['nilai'] ?? []; // [kriteria_id][hari] = nilai

    try {
        $pdo->beginTransaction();

        // Hapus data lama untuk bulan/tahun/karyawan ini
        $stmtDel = $pdo->prepare("DELETE FROM kpi_penilaian_harian WHERE karyawan_id = ? AND bulan = ? AND tahun = ?");
        $stmtDel->execute([$karyawan_id, $bulan, $tahun]);

        // Insert data baru
        $stmtIns = $pdo->prepare("INSERT INTO kpi_penilaian_harian (karyawan_id, kriteria_id, hari, bulan, tahun, nilai, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($nilai_arr as $kriteria_id => $hari_arr) {
            foreach ($hari_arr as $hari => $nilai) {
                $nilai = trim($nilai);
                if ($nilai !== '' && $nilai !== null) {
                    $stmtIns->execute([$karyawan_id, (int)$kriteria_id, (int)$hari, $bulan, $tahun, $nilai, $user['nama'] ?? 'Admin']);
                }
            }
        }

        $pdo->commit();
        header("Location: sop_kpi_penilaian_harian.php?karyawan_id={$karyawan_id}&bulan={$bulan}&tahun={$tahun}&success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// --- Parameter Filter ---
$bulan_sel      = (int)($_GET['bulan'] ?? date('m'));
$tahun_sel      = (int)($_GET['tahun'] ?? date('Y'));
$karyawan_id    = (int)($_GET['karyawan_id'] ?? 0);

// Jumlah hari dalam bulan
$jumlah_hari = cal_days_in_month(CAL_GREGORIAN, $bulan_sel, $tahun_sel);

// Nama hari singkat (S=Senin, S=Selasa, dst.) per tanggal
$nama_hari_singkat = ['M', 'S', 'S', 'R', 'K', 'J', 'S']; // 0=Minggu..6=Sabtu => mapping

function getNamaHari($hari, $bulan, $tahun) {
    $map = ['M', 'S', 'S', 'R', 'K', 'J', 'S']; // Sun, Mon, Tue, Wed, Thu, Fri, Sat
    $dow = date('w', mktime(0, 0, 0, $bulan, $hari, $tahun));
    return $map[$dow];
}

// Fetch Karyawan Aktif
$stmtK = $pdo->query("SELECT id, nama, jabatan, unit FROM kpi_karyawan WHERE status='Aktif' ORDER BY nama ASC");
$karyawanList = $stmtK->fetchAll();

// Fetch Kriteria dikelompokkan per kategori
$stmtKr = $pdo->query("SELECT * FROM kpi_kriteria ORDER BY kategori ASC, id ASC");
$kriteriaRaw = $stmtKr->fetchAll();
$kriteriaByKategori = [];
foreach ($kriteriaRaw as $kr) {
    $kriteriaByKategori[$kr['kategori']][] = $kr;
}

// Fetch nilai yang sudah ada
$nilaiExisting = [];
if ($karyawan_id) {
    $stmtN = $pdo->prepare("SELECT kriteria_id, hari, nilai FROM kpi_penilaian_harian WHERE karyawan_id = ? AND bulan = ? AND tahun = ?");
    $stmtN->execute([$karyawan_id, $bulan_sel, $tahun_sel]);
    foreach ($stmtN->fetchAll() as $row) {
        $nilaiExisting[$row['kriteria_id']][$row['hari']] = $row['nilai'];
    }
}

// Bulan list
$bulanList = [
    1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
    5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
    9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
];

// Nomor urut indikator global
$noUrut = 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penilaian Harian KPI - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .tbl-penilaian th, .tbl-penilaian td {
            padding: 6px 4px !important;
            font-size: 11px !important;
            text-align: center !important;
            border: 1px solid #e2e8f0 !important;
            white-space: nowrap;
        }
        .tbl-penilaian input[type="text"] {
            width: 32px !important;
            height: 28px !important;
            text-align: center !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 4px !important;
            padding: 2px !important;
            font-size: 11px !important;
            background: #fff !important;
        }
        .tbl-penilaian input[type="text"]:focus {
            border-color: #0d9488 !important;
            box-shadow: 0 0 0 2px rgba(13,148,136,0.15) !important;
            outline: none !important;
        }
        .kategori-header {
            background-color: #e2e8f0 !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            font-size: 11px !important;
            color: #475569 !important;
            text-align: left !important;
        }
        .col-no    { width: 30px; }
        .col-nama  { width: 140px; text-align: left !important; }
        .col-bobot { width: 52px; }
        .col-hari  { width: 34px; }
        /* Freeze first 3 columns */
        .tbl-penilaian th:nth-child(1), .tbl-penilaian td:nth-child(1) { position: sticky; left: 0; z-index: 2; background: #f8fafc; }
        .tbl-penilaian th:nth-child(2), .tbl-penilaian td:nth-child(2) { position: sticky; left: 30px; z-index: 2; background: #f8fafc; }
        .tbl-penilaian th:nth-child(3), .tbl-penilaian td:nth-child(3) { position: sticky; left: 170px; z-index: 2; background: #f8fafc; }
        .tbl-penilaian thead th { z-index: 3 !important; top: 0; position: sticky; }
        .tbl-penilaian thead th:nth-child(1),
        .tbl-penilaian thead th:nth-child(2),
        .tbl-penilaian thead th:nth-child(3) { z-index: 4 !important; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 flex flex-col relative h-screen overflow-hidden">
        <?php include 'includes/header.php'; ?>

        <div class="flex-1 p-6 overflow-y-auto">
            <!-- Page Title -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Penilaian Harian KPI</h1>
                    <p class="text-gray-500 text-sm mt-1">Input penilaian harian per indikator kinerja</p>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
                <span>Penilaian harian berhasil disimpan!</span>
            </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-5 h-5"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Karyawan</label>
                        <select name="karyawan_id" class="w-full border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 focus:ring-2 focus:ring-teal-500 outline-none">
                            <option value="">-- Pilih Karyawan --</option>
                            <?php foreach ($karyawanList as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $karyawan_id == $k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama'] . ' (' . $k['unit'] . ')') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
                        <select name="bulan" class="border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 focus:ring-2 focus:ring-teal-500 outline-none">
                            <?php foreach ($bulanList as $b => $nama): ?>
                            <option value="<?= $b ?>" <?= $bulan_sel == $b ? 'selected' : '' ?>><?= $nama ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
                        <select name="tahun" class="border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 focus:ring-2 focus:ring-teal-500 outline-none">
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                            <option value="<?= $y ?>" <?= $tahun_sel == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="px-5 py-2 bg-teal-600 text-white rounded-xl font-semibold text-sm hover:bg-teal-700 transition-colors flex items-center gap-2">
                        <i data-lucide="filter" class="w-4 h-4"></i> Tampilkan
                    </button>
                </div>
            </form>

            <?php if ($karyawan_id && !empty($kriteriaRaw)): ?>
            <?php
                // Cari data karyawan terpilih
                $karyawanTerpilih = null;
                foreach ($karyawanList as $k) {
                    if ($k['id'] == $karyawan_id) { $karyawanTerpilih = $k; break; }
                }
            ?>
            <!-- Info Karyawan -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center">
                    <i data-lucide="user" class="w-5 h-5 text-teal-700"></i>
                </div>
                <div>
                    <p class="font-bold text-gray-800"><?= htmlspecialchars($karyawanTerpilih['nama'] ?? '-') ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars(($karyawanTerpilih['jabatan'] ?? '') . ' · ' . ($karyawanTerpilih['unit'] ?? '')) ?></p>
                </div>
                <div class="ml-auto text-sm font-semibold text-gray-600">
                    <?= $bulanList[$bulan_sel] ?> <?= $tahun_sel ?>
                </div>
            </div>

            <!-- Tabel Penilaian -->
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="karyawan_id" value="<?= $karyawan_id ?>">
                <input type="hidden" name="bulan" value="<?= $bulan_sel ?>">
                <input type="hidden" name="tahun" value="<?= $tahun_sel ?>">

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-4">
                    <div class="overflow-x-auto">
                        <table class="tbl-penilaian w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="col-no">NO</th>
                                    <th class="col-nama" style="text-align:left!important">KATEGORI / INDIKATOR</th>
                                    <th class="col-bobot">BOBOT</th>
                                    <?php for ($h = 1; $h <= $jumlah_hari; $h++): ?>
                                    <th class="col-hari"><?= $h ?></th>
                                    <?php endfor; ?>
                                </tr>
                                <tr class="bg-gray-50">
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <?php for ($h = 1; $h <= $jumlah_hari; $h++): ?>
                                    <th style="font-size:10px!important;color:#64748b!important;font-weight:500!important;">
                                        <?= getNamaHari($h, $bulan_sel, $tahun_sel) ?>
                                    </th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $noUrut = 1; ?>
                                <?php foreach ($kriteriaByKategori as $kategori => $items): ?>
                                <!-- Kategori Header -->
                                <tr>
                                    <td colspan="<?= 3 + $jumlah_hari ?>" class="kategori-header" style="text-align:left!important;padding:8px 10px!important;">
                                        <?= htmlspecialchars($kategori) ?>
                                    </td>
                                </tr>
                                <!-- Indikator rows -->
                                <?php foreach ($items as $kr): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="col-no text-gray-500"><?= $noUrut++ ?></td>
                                    <td class="col-nama" style="text-align:left!important;padding-left:10px!important;">
                                        <?= htmlspecialchars($kr['nama_indikator']) ?>
                                    </td>
                                    <td class="col-bobot text-gray-600"><?= number_format($kr['bobot'], 1) ?>%</td>
                                    <?php for ($h = 1; $h <= $jumlah_hari; $h++): ?>
                                    <?php $val = $nilaiExisting[$kr['id']][$h] ?? ''; ?>
                                    <td class="col-hari">
                                        <input type="text"
                                               name="nilai[<?= $kr['id'] ?>][<?= $h ?>]"
                                               value="<?= htmlspecialchars($val) ?>"
                                               maxlength="3"
                                               inputmode="numeric"
                                               title="<?= htmlspecialchars($kr['nama_indikator']) ?> - Hari <?= $h ?>">
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tombol Simpan -->
                <div class="flex justify-end gap-3">
                    <a href="sop_kpi_penilaian_harian.php?karyawan_id=<?= $karyawan_id ?>&bulan=<?= $bulan_sel ?>&tahun=<?= $tahun_sel ?>"
                       class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium transition-colors text-sm">
                        Reset
                    </a>
                    <button type="submit" class="px-6 py-2.5 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-semibold shadow-md transition-colors flex items-center gap-2 text-sm">
                        <i data-lucide="save" class="w-4 h-4"></i> Simpan Penilaian Harian
                    </button>
                </div>
            </form>

            <?php elseif ($karyawan_id && empty($kriteriaRaw)): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-4 rounded-xl text-sm">
                <strong>Perhatian:</strong> Belum ada kriteria penilaian. Silakan tambahkan terlebih dahulu di menu
                <a href="sop_kpi_kriteria.php" class="underline font-semibold">Kriteria Penilaian</a>.
            </div>
            <?php else: ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
                <i data-lucide="clipboard-list" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
                <p class="text-sm">Pilih karyawan dan periode untuk menampilkan tabel penilaian harian.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
