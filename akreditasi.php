<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!hasPermission('akreditasi_view')) {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION['user'];

// Define STARKES Bab array
$STARKES_BAB = [
    '1'  => '1. TKRS (Tata Kelola Rumah Sakit)',
    '2'  => '2. PKPO (Pelayanan Kefarmasian dan Penggunaan Obat)',
    '3'  => '3. KPS (Kualifikasi Pendidikan dan Staf)',
    '4'  => '4. MFK (Manajemen Fasilitas dan Keselamatan)',
    '5'  => '5. PMKP (Peningkatan Mutu dan Keselamatan Pasien)',
    '6'  => '6. PPI (Pencegahan dan Pengendalian Infeksi)',
    '7'  => '7. HPK (Hak Pasien dan Keluarga)',
    '8'  => '8. MRMIK (Manajemen Rekam Medik dan Informasi Kesehatan)',
    '9'  => '9. SKP (Sasaran Keselamatan Pasien)',
    '10' => '10. KE (Komunikasi dan Edukasi)',
    '11' => '11. AKP (Akses dan Kontinuitas Pelayanan)',
    '12' => '12. PP (Pengkajian Pasien)',
    '13' => '13. PAP (Pelayanan dan Asuhan Pasien)',
    '14' => '14. PAB (Pelayanan Anastesi Bedah)',
    '15' => '15. PROGNAS (Program Nasional)',
];

// Helper: hitung skor dari grading
function hitungSkor($grading) {
    if ($grading === 'Selesai')          return 100;
    if ($grading === 'Selesai Sebagian') return 80;
    if ($grading === 'Belum Selesai')    return 0;
    return 0;
}

// Auto-migrate: tambah kolom baru jika belum ada
try {
    $cols = $pdo->query("SHOW COLUMNS FROM dokumen_akreditasi")->fetchAll(PDO::FETCH_COLUMN);
    $newCols = [
        'kode_standar'       => "ALTER TABLE dokumen_akreditasi ADD COLUMN `kode_standar` varchar(50) DEFAULT NULL AFTER `bab`",
        'uraian_standar'     => "ALTER TABLE dokumen_akreditasi ADD COLUMN `uraian_standar` text DEFAULT NULL AFTER `kode_standar`",
        'no_ep'              => "ALTER TABLE dokumen_akreditasi ADD COLUMN `no_ep` varchar(50) DEFAULT NULL AFTER `uraian_standar`",
        'elemen_penilaian'   => "ALTER TABLE dokumen_akreditasi ADD COLUMN `elemen_penilaian` text DEFAULT NULL AFTER `no_ep`",
        'kelengkapan_bukti'  => "ALTER TABLE dokumen_akreditasi ADD COLUMN `kelengkapan_bukti` text DEFAULT NULL AFTER `elemen_penilaian`",
        'fakta_analisis'     => "ALTER TABLE dokumen_akreditasi ADD COLUMN `fakta_analisis` text DEFAULT NULL AFTER `kelengkapan_bukti`",
        'rekomendasi'        => "ALTER TABLE dokumen_akreditasi ADD COLUMN `rekomendasi` text DEFAULT NULL AFTER `fakta_analisis`",
        'grading_kelengkapan'=> "ALTER TABLE dokumen_akreditasi ADD COLUMN `grading_kelengkapan` enum('Selesai','Selesai Sebagian','Belum Selesai') NOT NULL DEFAULT 'Belum Selesai' AFTER `rekomendasi`",
        'skor'               => "ALTER TABLE dokumen_akreditasi ADD COLUMN `skor` tinyint(3) NOT NULL DEFAULT 0 AFTER `grading_kelengkapan`",
        'catatan'            => "ALTER TABLE dokumen_akreditasi ADD COLUMN `catatan` text DEFAULT NULL AFTER `skor`",
    ];
    foreach ($newCols as $colName => $sql) {
        if (!in_array($colName, $cols)) {
            $pdo->exec($sql);
        }
    }
} catch (PDOException $e) {
    // Tabel belum ada, akan dibuat di bawah
}

// ── CREATE TABLE jika belum ada ──────────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `dokumen_akreditasi` (
            `id`                   int(11)      NOT NULL AUTO_INCREMENT,
            `bab`                  varchar(2)   NOT NULL,
            `kode_standar`         varchar(50)  DEFAULT NULL,
            `uraian_standar`       text         DEFAULT NULL,
            `no_ep`                varchar(50)  DEFAULT NULL,
            `elemen_penilaian`     text         DEFAULT NULL,
            `kelengkapan_bukti`    text         DEFAULT NULL,
            `fakta_analisis`       text         DEFAULT NULL,
            `rekomendasi`          text         DEFAULT NULL,
            `grading_kelengkapan`  enum('Selesai','Selesai Sebagian','Belum Selesai') NOT NULL DEFAULT 'Belum Selesai',
            `skor`                 tinyint(3)   NOT NULL DEFAULT 0,
            `catatan`              text         DEFAULT NULL,
            `nama_dokumen`         varchar(255) NOT NULL DEFAULT '',
            `kode_ep`              varchar(50)  NOT NULL DEFAULT '',
            `tanggal_review`       date         NOT NULL,
            `target_capaian`       int(3)       NOT NULL DEFAULT 100,
            `status_pemenuhan`     enum('Belum Lengkap','Dalam Review','Sudah Terpenuhi') NOT NULL DEFAULT 'Belum Lengkap',
            `file_path`            varchar(255) DEFAULT NULL,
            `file_status`          enum('Tidak Ada','Ada') NOT NULL DEFAULT 'Tidak Ada',
            `created_by`           int(11)      DEFAULT NULL,
            `created_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    // sudah ada, abaikan
}

$message = '';

// ── TAMBAH DOKUMEN ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_document'])) {
    if (!canUserEditOrDelete('akreditasi')) {
        $message = 'Anda tidak memiliki akses untuk menambah dokumen!';
    } else {
        $bab                 = trim($_POST['bab']);
        $kode_standar        = trim($_POST['kode_standar']        ?? '');
        $uraian_standar      = trim($_POST['uraian_standar']      ?? '');
        $no_ep               = trim($_POST['no_ep']               ?? '');
        $elemen_penilaian    = trim($_POST['elemen_penilaian']    ?? '');
        $kelengkapan_bukti   = trim($_POST['kelengkapan_bukti']   ?? '');
        $fakta_analisis      = trim($_POST['fakta_analisis']      ?? '');
        $rekomendasi         = trim($_POST['rekomendasi']         ?? '');
        $grading_kelengkapan = trim($_POST['grading_kelengkapan'] ?? 'Belum Selesai');
        $catatan             = trim($_POST['catatan']             ?? '');
        $skor                = hitungSkor($grading_kelengkapan);
        $nama_dokumen        = trim($_POST['nama_dokumen']        ?? '');
        $kode_ep             = trim($_POST['kode_ep']             ?? '');
        $tanggal_review      = date('Y-m-d');
        $target_capaian      = (int)($_POST['target_capaian']     ?? 100);

        // Upload berkas
        $file_path = null;
        if (isset($_FILES['berkas']) && $_FILES['berkas']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/akreditasi/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['berkas']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','doc','docx','jpg','jpeg','png'])) {
                $unique_name = uniqid('akreditasi_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['berkas']['tmp_name'], $upload_dir . $unique_name)) {
                    $file_path = $unique_name;
                }
            }
        }

        try {
            $file_status = $file_path ? 'Ada' : 'Tidak Ada';
            $stmt = $pdo->prepare("
                INSERT INTO dokumen_akreditasi
                (bab, kode_standar, uraian_standar, no_ep, elemen_penilaian,
                 kelengkapan_bukti, fakta_analisis, rekomendasi,
                 grading_kelengkapan, skor, catatan,
                 nama_dokumen, kode_ep, tanggal_review, target_capaian,
                 status_pemenuhan, file_path, file_status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Belum Lengkap',?,?)
            ");
            $stmt->execute([
                $bab, $kode_standar, $uraian_standar, $no_ep, $elemen_penilaian,
                $kelengkapan_bukti, $fakta_analisis, $rekomendasi,
                $grading_kelengkapan, $skor, $catatan,
                $nama_dokumen, $kode_ep, $tanggal_review, $target_capaian,
                $file_path, $file_status
            ]);
            $message = 'Dokumen berhasil ditambahkan!';
        } catch (PDOException $e) {
            $message = 'Gagal menambah dokumen: ' . $e->getMessage();
        }
    }
}

// ── EDIT DOKUMEN ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_document'])) {
    if (!canUserEditOrDelete('akreditasi')) {
        $message = 'Anda tidak memiliki akses untuk mengedit dokumen!';
    } else {
        $edit_id             = (int)$_POST['edit_id'];
        $bab                 = trim($_POST['bab']);
        $kode_standar        = trim($_POST['kode_standar']        ?? '');
        $uraian_standar      = trim($_POST['uraian_standar']      ?? '');
        $no_ep               = trim($_POST['no_ep']               ?? '');
        $elemen_penilaian    = trim($_POST['elemen_penilaian']    ?? '');
        $kelengkapan_bukti   = trim($_POST['kelengkapan_bukti']   ?? '');
        $fakta_analisis      = trim($_POST['fakta_analisis']      ?? '');
        $rekomendasi         = trim($_POST['rekomendasi']         ?? '');
        $grading_kelengkapan = trim($_POST['grading_kelengkapan'] ?? 'Belum Selesai');
        $catatan             = trim($_POST['catatan']             ?? '');
        $skor                = hitungSkor($grading_kelengkapan);
        $nama_dokumen        = trim($_POST['nama_dokumen']        ?? '');
        $kode_ep             = trim($_POST['kode_ep']             ?? '');
        $tanggal_review      = date('Y-m-d');
        $target_capaian      = (int)($_POST['target_capaian']     ?? 100);

        // Ambil file lama
        $stmt = $pdo->prepare("SELECT file_path FROM dokumen_akreditasi WHERE id = ?");
        $stmt->execute([$edit_id]);
        $current_doc = $stmt->fetch();
        $file_path = $current_doc['file_path'] ?? null;

        if (isset($_FILES['berkas']) && $_FILES['berkas']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/akreditasi/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['berkas']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','doc','docx','jpg','jpeg','png'])) {
                if ($file_path && file_exists($upload_dir . $file_path)) {
                    unlink($upload_dir . $file_path);
                }
                $unique_name = uniqid('akreditasi_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['berkas']['tmp_name'], $upload_dir . $unique_name)) {
                    $file_path = $unique_name;
                }
            }
        }

        try {
            $file_status = $file_path ? 'Ada' : 'Tidak Ada';
            $stmt = $pdo->prepare("
                UPDATE dokumen_akreditasi SET
                    bab=?, kode_standar=?, uraian_standar=?, no_ep=?, elemen_penilaian=?,
                    kelengkapan_bukti=?, fakta_analisis=?, rekomendasi=?,
                    grading_kelengkapan=?, skor=?, catatan=?,
                    nama_dokumen=?, kode_ep=?, tanggal_review=?, target_capaian=?,
                    file_path=?, file_status=?
                WHERE id=?
            ");
            $stmt->execute([
                $bab, $kode_standar, $uraian_standar, $no_ep, $elemen_penilaian,
                $kelengkapan_bukti, $fakta_analisis, $rekomendasi,
                $grading_kelengkapan, $skor, $catatan,
                $nama_dokumen, $kode_ep, $tanggal_review, $target_capaian,
                $file_path, $file_status,
                $edit_id
            ]);
            $message = 'Dokumen berhasil diperbarui!';
        } catch (PDOException $e) {
            $message = 'Gagal memperbarui dokumen: ' . $e->getMessage();
        }
    }
}

// ── HAPUS DOKUMEN ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!canUserEditOrDelete('akreditasi')) {
        $message = 'Anda tidak memiliki akses untuk menghapus dokumen!';
    } else {
        $delete_id = (int)$_POST['delete_id'];
        try {
            $stmt = $pdo->prepare("SELECT file_path FROM dokumen_akreditasi WHERE id = ?");
            $stmt->execute([$delete_id]);
            $doc = $stmt->fetch();
            if ($doc && $doc['file_path']) {
                $fp = 'uploads/akreditasi/' . $doc['file_path'];
                if (file_exists($fp)) unlink($fp);
            }
            $stmt = $pdo->prepare("DELETE FROM dokumen_akreditasi WHERE id = ?");
            $stmt->execute([$delete_id]);
            $message = 'Dokumen berhasil dihapus!';
        } catch (PDOException $e) {
            $message = 'Gagal menghapus dokumen: ' . $e->getMessage();
        }
    }
}

// ── AMBIL DATA ────────────────────────────────────────────────────────────────
$filter_bab = isset($_GET['bab']) ? $_GET['bab'] : '';
try {
    $sql    = "SELECT * FROM dokumen_akreditasi";
    $params = [];
    if ($filter_bab) {
        $sql    .= " WHERE bab = ?";
        $params[] = $filter_bab;
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documents = $stmt->fetchAll();

    $stats = ['total'=>0,'selesai'=>0,'sebagian'=>0,'belum'=>0];
    foreach ($documents as $doc) {
        $stats['total']++;
        $g = $doc['grading_kelengkapan'] ?? '';
        if ($g === 'Selesai')                $stats['selesai']++;
        elseif ($g === 'Selesai Sebagian')   $stats['sebagian']++;
        else                                 $stats['belum']++;
    }
} catch (PDOException $e) {
    $documents = [];
    $stats = ['total'=>0,'selesai'=>0,'sebagian'=>0,'belum'=>0];
}

// ── REKAP PER BAB ─────────────────────────────────────────────────────────────
// Nama bab sesuai gambar (kode singkat → nama panjang)
$BAB_NAMA = [
    '1'  => ['kode'=>'TKRS',   'nama'=>'Tata Kelola Rumah Sakit'],
    '2'  => ['kode'=>'PKPO',   'nama'=>'Pelayanan Kefarmasian dan Penggunaan Obat'],
    '3'  => ['kode'=>'KPS',    'nama'=>'Kualifikasi Pendidikan dan Staf'],
    '4'  => ['kode'=>'MFK',    'nama'=>'Manajemen Fasilitas dan Keselamatan'],
    '5'  => ['kode'=>'PMKP',   'nama'=>'Peningkatan Mutu dan Keselamatan Pasien'],
    '6'  => ['kode'=>'PPI',    'nama'=>'Pencegahan dan Pengendalian Infeksi'],
    '7'  => ['kode'=>'HPK',    'nama'=>'Hak Pasien dan Keluarga'],
    '8'  => ['kode'=>'MRMIK',  'nama'=>'Manajemen Rekam Medik dan Informasi Kesehatan'],
    '9'  => ['kode'=>'SKP',    'nama'=>'Sasaran Keselamatan Pasien'],
    '10' => ['kode'=>'KE',     'nama'=>'Komunikasi dan Edukasi'],
    '11' => ['kode'=>'AKP',    'nama'=>'Akses dan Kontinuitas Pelayanan'],
    '12' => ['kode'=>'PP',     'nama'=>'Pengkajian Pasien'],
    '13' => ['kode'=>'PAP',    'nama'=>'Pelayanan dan Asuhan Pasien'],
    '14' => ['kode'=>'PAB',    'nama'=>'Pelayanan Anastesi Bedah'],
    '15' => ['kode'=>'PROGNAS','nama'=>'Program Nasional'],
];

// Hitung rekap per bab dari semua dokumen (tanpa filter bab)
try {
    $allDocs = $pdo->query("SELECT bab, grading_kelengkapan, skor FROM dokumen_akreditasi")->fetchAll();
} catch (PDOException $e) {
    $allDocs = [];
}

$rekap = [];
foreach ($BAB_NAMA as $key => $info) {
    $rekap[$key] = [
        'kode'     => $info['kode'],
        'nama'     => $info['nama'],
        'total'    => 0,
        'selesai'  => 0,
        'sebagian' => 0,
        'belum'    => 0,
        'na'       => 0,
        'sum_skor' => 0,
    ];
}

foreach ($allDocs as $d) {
    $b = $d['bab'];
    if (!isset($rekap[$b])) continue;
    $rekap[$b]['total']++;
    $g = $d['grading_kelengkapan'] ?? '';
    if ($g === 'Selesai') {
        $rekap[$b]['selesai']++;
        $rekap[$b]['sum_skor'] += 100;
    } elseif ($g === 'Selesai Sebagian') {
        $rekap[$b]['sebagian']++;
        $rekap[$b]['sum_skor'] += 80;
    } elseif ($g === 'Belum Selesai') {
        $rekap[$b]['belum']++;
        $rekap[$b]['sum_skor'] += 0;
    } else {
        $rekap[$b]['na']++;
        // N/A dikecualikan dari rata-rata
    }
}

// Total keseluruhan
$rekapTotal = ['total'=>0,'selesai'=>0,'sebagian'=>0,'belum'=>0,'na'=>0,'sum_skor'=>0,'dinilai'=>0];
foreach ($rekap as $r) {
    $rekapTotal['total']    += $r['total'];
    $rekapTotal['selesai']  += $r['selesai'];
    $rekapTotal['sebagian'] += $r['sebagian'];
    $rekapTotal['belum']    += $r['belum'];
    $rekapTotal['na']       += $r['na'];
    $rekapTotal['sum_skor'] += $r['sum_skor'];
}
$rekapTotal['dinilai'] = $rekapTotal['total'] - $rekapTotal['na'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akreditasi & Mutu - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .table-wrap { overflow-x: auto; }
        .th { padding: 10px 14px; text-align:left; font-size:12px; font-weight:600; color:#374151; border-bottom:1px solid #e5e7eb; white-space:nowrap; background:#f9fafb; }
        .td { padding: 10px 14px; font-size:12px; color:#374151; vertical-align:top; }
        .td-wrap { max-width:180px; word-break:break-word; white-space:normal; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">
        <?php include 'includes/header.php'; ?>

        <div class="flex-1 p-6 overflow-y-auto">
            <div class="space-y-6">

                <!-- Page Header -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Akreditasi & Mutu</h1>
                        <p class="text-gray-500 text-sm mt-1">Dokumen Bukti EP & Capaian Standar Akreditasi STARKES</p>
                    </div>
                    <?php if (canUserEditOrDelete('akreditasi')): ?>
                        <button onclick="openModal()" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-emerald-700 transition-colors shadow-sm">
                            ➕ Tambah Dokumen
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($message): ?>
                    <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 text-sm">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl">📋</div>
                        <div>
                            <p class="text-xs text-gray-500">Total EP</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-2xl">✅</div>
                        <div>
                            <p class="text-xs text-gray-500">Selesai</p>
                            <p class="text-2xl font-bold text-emerald-600"><?php echo $stats['selesai']; ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
                        <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center text-2xl">⏳</div>
                        <div>
                            <p class="text-xs text-gray-500">Selesai Sebagian</p>
                            <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['sebagian']; ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
                        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center text-2xl">⚠️</div>
                        <div>
                            <p class="text-xs text-gray-500">Belum Selesai</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo $stats['belum']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filter -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <form method="GET" class="flex flex-wrap items-center gap-4">
                        <label class="text-sm font-semibold text-gray-700">Filter Bab STARKES:</label>
                        <select name="bab" onchange="this.form.submit()"
                            class="px-4 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                            <option value="">-- Semua Bab --</option>
                            <?php foreach ($STARKES_BAB as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filter_bab == $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($value); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filter_bab): ?>
                            <a href="akreditasi.php" class="text-sm text-emerald-700 hover:underline">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- ══ REKAP KELENGKAPAN BUKTI PER BAB ══════════════════════════════ -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-blue-900">
                        <h2 class="text-base font-bold text-white tracking-wide">REKAP KELENGKAPAN BUKTI PER BAB / ELEMEN AKREDITASI</h2>
                        <p class="text-xs text-blue-200 mt-0.5">
                            Grading: Selesai = 100% &nbsp;|&nbsp; Selesai Sebagian = 80% &nbsp;|&nbsp; Belum Selesai = 0% &nbsp;(N/A dan Belum Dinilai dikecualikan dari rata-rata)
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm" style="min-width:820px">
                            <thead>
                                <tr class="bg-blue-800 text-white">
                                    <th class="px-4 py-3 text-center font-semibold border border-blue-700 w-20">Bab</th>
                                    <th class="px-4 py-3 text-left   font-semibold border border-blue-700">Nama Bab</th>
                                    <th class="px-4 py-3 text-center font-semibold border border-blue-700 w-24">Jumlah EP</th>
                                    <th class="px-4 py-3 text-center font-semibold border border-blue-700 w-28">Selesai<br><span class="font-normal text-xs">(100%)</span></th>
                                    <th class="px-4 py-3 text-center font-semibold border border-blue-700 w-32">Selesai Sebagian<br><span class="font-normal text-xs">(80%)</span></th>
                                    <th class="px-4 py-3 text-center font-semibold border border-blue-700 w-28">Belum Selesai<br><span class="font-normal text-xs">(0%)</span></th>
                                    <th class="px-4 py-3 text-center font-semibold border border-blue-700 w-28">Belum Dinilai /<br>N/A</th>
                                    <th class="px-4 py-3 text-center font-semibold border border-blue-700 w-32">Rata-rata Capaian<br><span class="font-normal text-xs">(%)</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rekap as $key => $r):
                                    $dinilai  = $r['total'] - $r['na'];
                                    $rataRata = $dinilai > 0 ? round($r['sum_skor'] / $dinilai, 1) : 0;
                                    // warna rata-rata
                                    if ($rataRata >= 80)      $avgClass = 'text-emerald-700 font-bold';
                                    elseif ($rataRata >= 40)  $avgClass = 'text-yellow-700 font-bold';
                                    else                      $avgClass = 'text-red-600 font-bold';
                                    // baris zebra
                                    $rowBg = ($key % 2 === 0) ? 'bg-white' : 'bg-gray-50';
                                ?>
                                <tr class="<?php echo $rowBg; ?> hover:bg-blue-50 transition-colors">
                                    <td class="px-4 py-2.5 text-center font-semibold border border-gray-200 text-blue-900"><?php echo htmlspecialchars($r['kode']); ?></td>
                                    <td class="px-4 py-2.5 border border-gray-200 text-gray-800"><?php echo htmlspecialchars($r['nama']); ?></td>
                                    <td class="px-4 py-2.5 text-center border border-gray-200">
                                        <?php if ($r['total'] > 0): ?>
                                            <span class="font-semibold text-gray-800"><?php echo $r['total']; ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-300">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-center border border-gray-200">
                                        <?php if ($r['total'] > 0): ?>
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full <?php echo $r['selesai'] > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-400'; ?> text-xs font-bold"><?php echo $r['selesai']; ?></span>
                                        <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-center border border-gray-200">
                                        <?php if ($r['total'] > 0): ?>
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full <?php echo $r['sebagian'] > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-400'; ?> text-xs font-bold"><?php echo $r['sebagian']; ?></span>
                                        <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-center border border-gray-200">
                                        <?php if ($r['total'] > 0): ?>
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full <?php echo $r['belum'] > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400'; ?> text-xs font-bold"><?php echo $r['belum']; ?></span>
                                        <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-center border border-gray-200">
                                        <?php if ($r['total'] > 0): ?>
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full <?php echo $r['na'] > 0 ? 'bg-gray-200 text-gray-600' : 'bg-gray-100 text-gray-400'; ?> text-xs font-bold"><?php echo $r['na']; ?></span>
                                        <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-center border border-gray-200">
                                        <span class="<?php echo $r['total'] > 0 ? $avgClass : 'text-gray-300'; ?>">
                                            <?php echo $r['total'] > 0 ? number_format($rataRata, 1) . '%' : '—'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <!-- Baris TOTAL -->
                            <tfoot>
                                <?php
                                    $totalDinilai = $rekapTotal['dinilai'];
                                    $totalAvg     = $totalDinilai > 0 ? round($rekapTotal['sum_skor'] / $totalDinilai, 1) : 0;
                                    if ($totalAvg >= 80)     $tAvgClass = 'text-emerald-700';
                                    elseif ($totalAvg >= 40) $tAvgClass = 'text-yellow-700';
                                    else                     $tAvgClass = 'text-red-600';
                                ?>
                                <tr class="bg-blue-900 text-white">
                                    <td class="px-4 py-3 text-center font-bold border border-blue-700" colspan="2">TOTAL</td>
                                    <td class="px-4 py-3 text-center font-bold border border-blue-700"><?php echo $rekapTotal['total']; ?></td>
                                    <td class="px-4 py-3 text-center font-bold border border-blue-700"><?php echo $rekapTotal['selesai']; ?></td>
                                    <td class="px-4 py-3 text-center font-bold border border-blue-700"><?php echo $rekapTotal['sebagian']; ?></td>
                                    <td class="px-4 py-3 text-center font-bold border border-blue-700"><?php echo $rekapTotal['belum']; ?></td>
                                    <td class="px-4 py-3 text-center font-bold border border-blue-700"><?php echo $rekapTotal['na']; ?></td>
                                    <td class="px-4 py-3 text-center font-bold border border-blue-700">
                                        <span class="<?php echo $tAvgClass; ?>">
                                            <?php echo number_format($totalAvg, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Tabel -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm table-wrap">
                    <table class="w-full border-collapse" style="min-width:1200px">
                        <thead>
                            <tr>
                                <th class="th">No</th>
                                <th class="th">Kode Standar</th>
                                <th class="th">Uraian Standar</th>
                                <th class="th">No EP</th>
                                <th class="th">Elemen Penilaian</th>
                                <th class="th">Kelengkapan Bukti Diminta</th>
                                <th class="th">Fakta dan Analisis</th>
                                <th class="th">Rekomendasi</th>
                                <th class="th">Grading Kelengkapan Bukti</th>
                                <th class="th">Skor (%)</th>
                                <th class="th">Catatan</th>
                                <th class="th">Berkas</th>
                                <th class="th">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="13" class="td text-center py-12 text-gray-400">
                                        Belum ada dokumen yang ditambahkan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documents as $i => $doc):
                                    $grading = $doc['grading_kelengkapan'] ?? 'Belum Selesai';
                                    $skor    = hitungSkor($grading);
                                    // warna grading
                                    if ($grading === 'Selesai') {
                                        $gClass = 'bg-emerald-100 text-emerald-800';
                                        $sClass = 'bg-emerald-100 text-emerald-800';
                                    } elseif ($grading === 'Selesai Sebagian') {
                                        $gClass = 'bg-yellow-100 text-yellow-800';
                                        $sClass = 'bg-yellow-100 text-yellow-800';
                                    } else {
                                        $gClass = 'bg-red-100 text-red-800';
                                        $sClass = 'bg-red-100 text-red-800';
                                    }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="td"><?php echo $i + 1; ?></td>
                                    <td class="td">
                                        <span class="inline-block px-2 py-0.5 rounded bg-purple-100 text-purple-800 text-xs font-semibold">
                                            <?php echo htmlspecialchars($doc['kode_standar'] ?? '-'); ?>
                                        </span>
                                        <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($STARKES_BAB[$doc['bab']] ?? $doc['bab']); ?></div>
                                    </td>
                                    <td class="td td-wrap"><?php echo nl2br(htmlspecialchars($doc['uraian_standar'] ?? '-')); ?></td>
                                    <td class="td whitespace-nowrap"><?php echo htmlspecialchars($doc['no_ep'] ?? '-'); ?></td>
                                    <td class="td td-wrap"><?php echo nl2br(htmlspecialchars($doc['elemen_penilaian'] ?? '-')); ?></td>
                                    <td class="td td-wrap"><?php echo nl2br(htmlspecialchars($doc['kelengkapan_bukti'] ?? '-')); ?></td>
                                    <td class="td td-wrap"><?php echo nl2br(htmlspecialchars($doc['fakta_analisis'] ?? '-')); ?></td>
                                    <td class="td td-wrap"><?php echo nl2br(htmlspecialchars($doc['rekomendasi'] ?? '-')); ?></td>
                                    <td class="td">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $gClass; ?>">
                                            <?php echo htmlspecialchars($grading); ?>
                                        </span>
                                    </td>
                                    <td class="td">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold <?php echo $sClass; ?>">
                                            <?php echo $skor; ?>%
                                        </span>
                                    </td>
                                    <td class="td td-wrap"><?php echo nl2br(htmlspecialchars($doc['catatan'] ?? '-')); ?></td>
                                    <td class="td">
                                        <?php if ($doc['file_path']): ?>
                                            <a href="uploads/akreditasi/<?php echo $doc['file_path']; ?>" target="_blank"
                                               class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 hover:bg-green-200">
                                                Ada
                                            </a>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Tidak Ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="td">
                                        <div class="flex items-center gap-1">
                                            <?php if (canUserEditOrDelete('akreditasi')): ?>
                                                <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($doc), ENT_QUOTES); ?>)'
                                                    class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">Edit</button>
                                                <form method="POST" onsubmit="return confirm('Yakin hapus dokumen ini?')" class="inline">
                                                    <input type="hidden" name="delete_id" value="<?php echo $doc['id']; ?>">
                                                    <button type="submit" class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-lg hover:bg-red-200">Hapus</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </main>

    <!-- ══ MODAL TAMBAH / EDIT ══════════════════════════════════════════════ -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="px-7 py-5 border-b border-gray-100 flex justify-between items-center flex-shrink-0">
                <h2 id="modal-title" class="text-xl font-bold text-gray-900">Tambah Dokumen / Bukti EP</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <!-- Body scrollable -->
            <form method="POST" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <div class="px-7 py-5 space-y-4 overflow-y-auto flex-1">

                    <!-- Pilih Akreditasi -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Pilih Akreditasi</label>
                        <select name="bab" id="bab" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm text-gray-700">
                            <?php foreach ($STARKES_BAB as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Kode Standar -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Kode Standar</label>
                            <input type="text" name="kode_standar" id="kode_standar" placeholder="Contoh: TKRS 1"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none text-sm text-gray-700 placeholder-gray-400">
                        </div>

                    <!-- Uraian Standar -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Uraian Standar</label>
                        <textarea name="uraian_standar" id="uraian_standar" rows="2"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none text-sm text-gray-700 resize-none"></textarea>
                    </div>

                    <!-- No EP -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">No EP</label>
                        <input type="text" name="no_ep" id="no_ep" placeholder="Contoh: EP-101"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none text-sm text-gray-700 placeholder-gray-400">
                    </div>

                    <!-- Elemen Penilaian -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Elemen Penilaian</label>
                        <textarea name="elemen_penilaian" id="elemen_penilaian" rows="2"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none text-sm text-gray-700 resize-none"></textarea>
                    </div>

                    <!-- Kelengkapan Bukti Diminta -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Kelengkapan Bukti Diminta</label>
                        <textarea name="kelengkapan_bukti" id="kelengkapan_bukti" rows="2"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none text-sm text-gray-700 resize-none"></textarea>
                    </div>

                    <!-- Fakta dan Analisis -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Fakta dan Analisis</label>
                        <textarea name="fakta_analisis" id="fakta_analisis" rows="2"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none text-sm text-gray-700 resize-none"></textarea>
                    </div>

                    <!-- Rekomendasi -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Rekomendasi</label>
                        <textarea name="rekomendasi" id="rekomendasi" rows="2"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none text-sm text-gray-700 resize-none"></textarea>
                    </div>

                    <!-- Grading + Skor otomatis -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Grading Kelengkapan Bukti</label>
                            <select name="grading_kelengkapan" id="grading_kelengkapan" onchange="updateSkor(this.value)"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-emerald-500 outline-none text-sm text-gray-700">
                                <option value="Selesai">Selesai</option>
                                <option value="Selesai Sebagian">Selesai Sebagian</option>
                                <option value="Belum Selesai" selected>Belum Selesai</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Skor (%)</label>
                            <input type="text" id="skor_display" readonly value="0%"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm font-bold text-gray-700 cursor-not-allowed">
                        </div>
                    </div>

                    <!-- Catatan -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Catatan</label>
                        <textarea name="catatan" id="catatan" rows="2"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none text-sm text-gray-700 resize-none"></textarea>
                    </div>

                    <!-- Upload Berkas -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Upload Berkas (PDF/DOC/Image)</label>
                        <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                            <input type="file" name="berkas" id="berkas" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                class="text-sm text-gray-700 file:mr-2 file:py-1 file:px-2 file:rounded file:border file:border-gray-300 file:bg-white file:text-xs file:cursor-pointer w-full">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Biarkan kosong jika tidak ingin mengubah berkas</p>
                    </div>

                </div>
                </div>
                <!-- Footer -->
                <div class="px-7 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
                    <button type="button" onclick="closeModal()"
                        class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium text-sm">Batal</button>
                    <button type="submit" name="add_document" id="submitBtn"
                        class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium text-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ── Hitung skor otomatis ──────────────────────────────────────────────
        function updateSkor(grading) {
            const el = document.getElementById('skor_display');
            if (!el) return;
            if (grading === 'Selesai')               el.value = '100%';
            else if (grading === 'Selesai Sebagian') el.value = '80%';
            else                                     el.value = '0%';
        }

        // Inisialisasi skor saat halaman load
        updateSkor(document.getElementById('grading_kelengkapan').value);

        // ── Buka modal Tambah ─────────────────────────────────────────────────
        function openModal(id) {
            resetForm();
            document.getElementById('submitBtn').name        = 'add_document';
            document.getElementById('submitBtn').textContent = 'Simpan';
            document.getElementById('modal-title').textContent = 'Tambah Dokumen / Bukti EP';
            const modal = document.getElementById('modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function resetForm() {
            const ids = ['edit_id','kode_standar','uraian_standar',
                         'no_ep','elemen_penilaian','kelengkapan_bukti',
                         'fakta_analisis','rekomendasi','catatan'];
            ids.forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            const bab = document.getElementById('bab');
            if (bab) bab.value = '1';
            const grading = document.getElementById('grading_kelengkapan');
            if (grading) grading.value = 'Belum Selesai';
            const berkas = document.getElementById('berkas');
            if (berkas) berkas.value = '';
            updateSkor('Belum Selesai');
        }

        // ── Buka modal Edit ───────────────────────────────────────────────────
        function openEditModal(doc) {
            const setVal = function(id, val) {
                const el = document.getElementById(id);
                if (el) el.value = val || '';
            };
            setVal('edit_id',            doc.id);
            setVal('bab',                doc.bab || '1');
            setVal('kode_standar',       doc.kode_standar);
            setVal('uraian_standar',     doc.uraian_standar);
            setVal('no_ep',              doc.no_ep);
            setVal('elemen_penilaian',   doc.elemen_penilaian);
            setVal('kelengkapan_bukti',  doc.kelengkapan_bukti);
            setVal('fakta_analisis',     doc.fakta_analisis);
            setVal('rekomendasi',        doc.rekomendasi);
            setVal('catatan',            doc.catatan);
            setVal('berkas',             '');

            const grading = doc.grading_kelengkapan || 'Belum Selesai';
            setVal('grading_kelengkapan', grading);
            updateSkor(grading);

            document.getElementById('submitBtn').name        = 'edit_document';
            document.getElementById('submitBtn').textContent = 'Simpan Perubahan';
            document.getElementById('modal-title').textContent = 'Edit Dokumen / Bukti EP';

            const modal = document.getElementById('modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // ── Tutup modal ───────────────────────────────────────────────────────
        function closeModal(id) {
            const modalId = id || 'modal';
            const el = document.getElementById(modalId);
            if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
        }
    </script>
</body>
</html>