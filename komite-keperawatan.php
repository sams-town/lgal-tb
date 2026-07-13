<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!hasPermission('komite_view')) {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION['user'];
$page = 'komite-keperawatan';

$jabatanKeperawatanOptions = [
    'Supervisor', 'Bidan', 'Perawat'
];

// Handle file upload helper
function handleFileUpload($fileInputName, $uploadDir) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = uniqid() . '_' . basename($_FILES[$fileInputName]['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetFile)) {
            return $targetFile;
        }
    }
    return null;
}

// Handle form submission for Tambah Tenaga Medis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_tenaga_medis'])) {
    $uploadDir = 'uploads/medis/';
    $fileStr = handleFileUpload('file_str', $uploadDir);
    $fileSip = handleFileUpload('file_sip', $uploadDir);
    $filePks = handleFileUpload('file_pks', $uploadDir);
    $fileSk = handleFileUpload('file_sk', $uploadDir);

    $sertifikasi = isset($_POST['sertifikasi']) ? json_encode($_POST['sertifikasi']) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenaga_medis (
                nama_lengkap, unit_ruangan, status_kepegawaian, tipe_form,
                no_str, masa_berlaku_str_mulai, masa_berlaku_str_akhir, file_str,
                no_sip, masa_berlaku_sip_mulai, masa_berlaku_sip_akhir, file_sip,
                no_pks, masa_berlaku_pks_mulai, masa_berlaku_pks_akhir, file_pks,
                no_sk, masa_berlaku_sk_mulai, masa_berlaku_sk_akhir, file_sk,
                kompetensi_klinis, sertifikasi_kompetensi, jabatan_keperawatan,
                spesialis, nomor_pkwt, rincian_kewenangan_klinis,
                lantai, nomor_keputusan_direktur
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['nama_lengkap'],
            $_POST['unit_ruangan'] ?? null,
            $_POST['status_kepegawaian'],
            $page,
            $_POST['no_str'] ?? null,
            $_POST['masa_berlaku_str_mulai'] ?? null,
            $_POST['masa_berlaku_str_akhir'] ?? null,
            $fileStr,
            $_POST['no_sip'] ?? null,
            $_POST['masa_berlaku_sip_mulai'] ?? null,
            $_POST['masa_berlaku_sip_akhir'] ?? null,
            $fileSip,
            $_POST['no_pks'] ?? null,
            $_POST['masa_berlaku_pks_mulai'] ?? null,
            $_POST['masa_berlaku_pks_akhir'] ?? null,
            $filePks,
            $_POST['no_sk'] ?? null,
            $_POST['masa_berlaku_sk_mulai'] ?? null,
            $_POST['masa_berlaku_sk_akhir'] ?? null,
            $fileSk,
            $_POST['kompetensi_klinis'] ?? null,
            $sertifikasi,
            $_POST['jabatan_keperawatan'] ?? null,
            $_POST['spesialis'] ?? null,
            $_POST['nomor_pkwt'] ?? null,
            $_POST['rincian_kewenangan_klinis'] ?? null,
            $_POST['lantai'] ?? null,
            $_POST['nomor_keputusan_direktur'] ?? null
        ]);
        $_SESSION['success_msg'] = "Data Komite Keperawatan berhasil ditambahkan";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal menambahkan data: " . $e->getMessage();
    }
    header("Location: komite-keperawatan.php");
    exit;
}

// Handle form submission for editing Komite Keperawatan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tenaga_medis'])) {
    if (!canUserEditOrDelete('komite')) {
        $_SESSION['error_msg'] = "Anda tidak memiliki akses untuk mengedit data ini!";
    } else {
        $editId = (int)$_POST['edit_id'];
        $uploadDir = 'uploads/medis/';

        // Get current file paths
        $stmt = $pdo->prepare("SELECT file_str, file_sip, file_pks, file_sk FROM tenaga_medis WHERE id = ? AND tipe_form = 'komite-keperawatan'");
        $stmt->execute([$editId]);
        $currentData = $stmt->fetch();

        // Handle file uploads
        $fileStr = handleFileUpload('file_str', $uploadDir);
        $fileSip = handleFileUpload('file_sip', $uploadDir);
        $filePks = handleFileUpload('file_pks', $uploadDir);
        $fileSk = handleFileUpload('file_sk', $uploadDir);

        // Keep old files if no new ones are uploaded
        if (!$fileStr) $fileStr = $currentData['file_str'] ?? null;
        if (!$fileSip) $fileSip = $currentData['file_sip'] ?? null;
        if (!$filePks) $filePks = $currentData['file_pks'] ?? null;
        if (!$fileSk) $fileSk = $currentData['file_sk'] ?? null;

        // Delete old files if new ones are provided
        if (isset($_FILES['file_str']) && $_FILES['file_str']['error'] === UPLOAD_ERR_OK && $currentData['file_str'] && file_exists($currentData['file_str'])) {
            unlink($currentData['file_str']);
        }
        if (isset($_FILES['file_sip']) && $_FILES['file_sip']['error'] === UPLOAD_ERR_OK && $currentData['file_sip'] && file_exists($currentData['file_sip'])) {
            unlink($currentData['file_sip']);
        }
        if (isset($_FILES['file_pks']) && $_FILES['file_pks']['error'] === UPLOAD_ERR_OK && $currentData['file_pks'] && file_exists($currentData['file_pks'])) {
            unlink($currentData['file_pks']);
        }
        if (isset($_FILES['file_sk']) && $_FILES['file_sk']['error'] === UPLOAD_ERR_OK && $currentData['file_sk'] && file_exists($currentData['file_sk'])) {
            unlink($currentData['file_sk']);
        }

        $sertifikasi = isset($_POST['sertifikasi']) ? json_encode($_POST['sertifikasi']) : null;

        try {
            $stmt = $pdo->prepare("
                UPDATE tenaga_medis 
                SET nama_lengkap = ?, unit_ruangan = ?, status_kepegawaian = ?,
                    no_str = ?, masa_berlaku_str_mulai = ?, masa_berlaku_str_akhir = ?, file_str = ?,
                    no_sip = ?, masa_berlaku_sip_mulai = ?, masa_berlaku_sip_akhir = ?, file_sip = ?,
                    no_pks = ?, masa_berlaku_pks_mulai = ?, masa_berlaku_pks_akhir = ?, file_pks = ?,
                    no_sk = ?, masa_berlaku_sk_mulai = ?, masa_berlaku_sk_akhir = ?, file_sk = ?,
                    kompetensi_klinis = ?, sertifikasi_kompetensi = ?, jabatan_keperawatan = ?,
                    spesialis = ?, nomor_pkwt = ?, rincian_kewenangan_klinis = ?,
                    lantai = ?, nomor_keputusan_direktur = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['nama_lengkap'],
                $_POST['unit_ruangan'] ?? null,
                $_POST['status_kepegawaian'],
                $_POST['no_str'] ?? null,
                $_POST['masa_berlaku_str_mulai'] ?? null,
                $_POST['masa_berlaku_str_akhir'] ?? null,
                $fileStr,
                $_POST['no_sip'] ?? null,
                $_POST['masa_berlaku_sip_mulai'] ?? null,
                $_POST['masa_berlaku_sip_akhir'] ?? null,
                $fileSip,
                $_POST['no_pks'] ?? null,
                $_POST['masa_berlaku_pks_mulai'] ?? null,
                $_POST['masa_berlaku_pks_akhir'] ?? null,
                $filePks,
                $_POST['no_sk'] ?? null,
                $_POST['masa_berlaku_sk_mulai'] ?? null,
                $_POST['masa_berlaku_sk_akhir'] ?? null,
                $fileSk,
                $_POST['kompetensi_klinis'] ?? null,
                $sertifikasi,
                $_POST['jabatan_keperawatan'] ?? null,
                $_POST['spesialis'] ?? null,
                $_POST['nomor_pkwt'] ?? null,
                $_POST['rincian_kewenangan_klinis'] ?? null,
                $_POST['lantai'] ?? null,
                $_POST['nomor_keputusan_direktur'] ?? null,
                $editId
            ]);
            $_SESSION['success_msg'] = "Data Komite Keperawatan berhasil diperbarui";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
    header("Location: komite-keperawatan.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    if (!canUserEditOrDelete('komite')) {
        $_SESSION['error_msg'] = "Anda tidak memiliki akses untuk menghapus data ini!";
        header("Location: komite-keperawatan.php");
        exit;
    }
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT file_str, file_sip, file_pks, file_sk FROM tenaga_medis WHERE id = ? AND tipe_form = 'komite-keperawatan'");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if ($data) {
            $stmt = $pdo->prepare("DELETE FROM tenaga_medis WHERE id = ?");
            $stmt->execute([$id]);

            $files = [$data['file_str'], $data['file_sip'], $data['file_pks'], $data['file_sk']];
            foreach ($files as $file) {
                if ($file && file_exists($file)) {
                    unlink($file);
                }
            }
            $_SESSION['success_msg'] = "Data berhasil dihapus";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: komite-keperawatan.php");
    exit;
}

// Get data
try {
    $stmt = $pdo->prepare("SELECT * FROM tenaga_medis WHERE tipe_form = 'komite-keperawatan' ORDER BY created_at DESC");
    $stmt->execute();
    $dataMedis = $stmt->fetchAll();
} catch (PDOException $e) {
    $dataMedis = [];
}

// Calculate stats
$totalData = count($dataMedis);
$today = new DateTime();
$aktifCount = 0;
$h30Count = 0;
$expiredCount = 0;

foreach ($dataMedis as $data) {
    $checkDate = $data['masa_berlaku_sip_akhir'] ?? $data['masa_berlaku_sk_akhir'] ?? null;
    if ($checkDate) {
        $tanggal = new DateTime($checkDate);
        $selisih = $today->diff($tanggal)->days;
        
        if ($tanggal < $today) {
            $expiredCount++;
        } elseif ($selisih <= 30) {
            $h30Count++;
        } else {
            $aktifCount++;
        }
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (!$date) return '-';
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $d = new DateTime($date);
        return $d->format('d') . ' ' . $months[$d->format('n')] . ' ' . $d->format('Y');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Komite Keperawatan - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <?php include 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Komite Keperawatan</h1>
                        <p class="text-gray-600 mt-2">Manajemen komite keperawatan (untuk perawat)</p>
                    </div>
                    <button onclick="openModal('modal')" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        <span>+</span>
                        <span>Tambah Dokumen</span>
                    </button>
                </div>

                <!-- Session Flash Messages -->
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl font-medium shadow-sm transition-all">
                        <?php echo htmlspecialchars($_SESSION['success_msg']); ?>
                    </div>
                    <?php unset($_SESSION['success_msg']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="p-4 bg-red-100 text-red-800 rounded-xl font-medium shadow-sm transition-all">
                        <?php echo htmlspecialchars($_SESSION['error_msg']); ?>
                    </div>
                    <?php unset($_SESSION['error_msg']); ?>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Data</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalData; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Aktif</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $aktifCount; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">✅</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Expired H-30</p>
                                <h3 class="text-3xl font-bold text-amber-600"><?php echo $h30Count; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">⚠️</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Expired</p>
                                <h3 class="text-3xl font-bold text-red-600"><?php echo $expiredCount; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-3xl">🚨</div>
                        </div>
                    </div>
                </div>

                <!-- Export/Import Buttons -->
                <div class="flex items-center gap-3 mb-4">
                    <a href="export_handler.php?action=export_data&module=tenaga_medis&subpage=komite-keperawatan" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        📊
                        <span>Export Excel</span>
                    </a>
                    <button onclick="openModal('importModal')" class="flex items-center gap-2 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        📤
                        <span>Import Excel</span>
                    </button>
                    <a href="export_handler.php?action=download_template&module=tenaga_medis" class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                        📄
                        <span>Download Template</span>
                    </a>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nama Lengkap</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Unit/Ruangan</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Lantai</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Jabatan</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Rincian Kewenangan Klinis</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nomor Keputusan Direktur</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status Kepegawaian</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nomor PKWT</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No. SIP</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No. STR</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($dataMedis)): ?>
                                    <tr>
                                        <td colspan="13" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada data yang tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dataMedis as $index => $data): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-gray-700 text-sm font-medium">
                                                <?php echo $index + 1; ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 font-medium text-gray-900">
                                                <?php echo htmlspecialchars($data['nama_lengkap']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm">
                                                <?php echo htmlspecialchars($data['unit_ruangan'] ?? '-'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm">
                                                <?php echo htmlspecialchars($data['lantai'] ?? '-'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm">
                                                <?php echo htmlspecialchars($data['jabatan_keperawatan'] ?? '-'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm max-w-xs truncate" title="<?php echo htmlspecialchars($data['rincian_kewenangan_klinis'] ?? '-'); ?>">
                                                <?php echo htmlspecialchars($data['rincian_kewenangan_klinis'] ?? '-'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm">
                                                <?php echo htmlspecialchars($data['nomor_keputusan_direktur'] ?? '-'); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $data['status_kepegawaian'] === 'Tetap' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-blue-100 text-blue-800 border-blue-200'; ?>">
                                                    <?php echo htmlspecialchars($data['status_kepegawaian']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm">
                                                <div class="font-medium font-mono"><?php echo htmlspecialchars($data['nomor_pkwt'] ?? $data['no_pks'] ?? '-'); ?></div>
                                                <?php 
                                                $pksMulai = $data['masa_berlaku_pks_mulai'] ?? null;
                                                $pksAkhir = $data['masa_berlaku_pks_akhir'] ?? null;
                                                if ($pksMulai && $pksAkhir): ?>
                                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        <?php echo formatDate($pksMulai) . ' - ' . formatDate($pksAkhir); ?>
                                                    </div>
                                                <?php elseif ($pksAkhir): ?>
                                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        s/d <?php echo formatDate($pksAkhir); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm">
                                                <div class="font-medium font-mono"><?php echo htmlspecialchars($data['no_sip'] ?? '-'); ?></div>
                                                <?php 
                                                $sipMulai = $data['masa_berlaku_sip_mulai'] ?? null;
                                                $sipAkhir = $data['masa_berlaku_sip_akhir'] ?? null;
                                                if ($sipMulai && $sipAkhir): ?>
                                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                        <?php echo formatDate($sipMulai) . ' - ' . formatDate($sipAkhir); ?>
                                                    </div>
                                                <?php elseif ($sipAkhir): ?>
                                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                        s/d <?php echo formatDate($sipAkhir); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm">
                                                <div class="font-medium font-mono"><?php echo htmlspecialchars($data['no_str'] ?? '-'); ?></div>
                                                <?php 
                                                $strMulai = $data['masa_berlaku_str_mulai'] ?? null;
                                                $strAkhir = $data['masa_berlaku_str_akhir'] ?? null;
                                                if ($strMulai && $strAkhir): ?>
                                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                                        <?php echo formatDate($strMulai) . ' - ' . formatDate($strAkhir); ?>
                                                    </div>
                                                <?php elseif ($strAkhir): ?>
                                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                                        s/d <?php echo formatDate($strAkhir); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex gap-2">
                                                    <?php if ($data['file_str']): ?>
                                                        <a href="<?php echo htmlspecialchars($data['file_str']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">STR</a>
                                                    <?php endif; ?>
                                                    <?php if ($data['file_sip']): ?>
                                                        <a href="<?php echo htmlspecialchars($data['file_sip']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">SIP</a>
                                                    <?php endif; ?>
                                                    <?php if ($data['file_pks']): ?>
                                                        <a href="<?php echo htmlspecialchars($data['file_pks']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">PKS</a>
                                                    <?php endif; ?>
                                                    <?php if ($data['file_sk']): ?>
                                                        <a href="<?php echo htmlspecialchars($data['file_sk']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">SK</a>
                                                    <?php endif; ?>
                                                    <?php if (!$data['file_str'] && !$data['file_sip'] && !$data['file_pks'] && !$data['file_sk']): ?>
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <?php if (canUserEditOrDelete('komite')): ?>
                                                        <button onclick='openEditModal(<?php echo json_encode($data); ?>)' class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">Edit</button>
                                                        <a href="komite-keperawatan.php?delete=<?php echo $data['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">Hapus</a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-sm">-</span>
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
        </div>
    </main>

    <!-- Modal Tambah Tenaga Medis -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 id="modal_title" class="text-xl font-bold text-gray-900">Form Tambah Tenaga Medis - Komite Keperawatan</h2>
                <button onclick="closeModal('modal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <!-- Informasi Dasar -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Informasi Dasar</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_lengkap" id="nama_lengkap" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nama lengkap">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit/Ruangan</label>
                            <input type="text" name="unit_ruangan" id="unit_ruangan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan unit/ruangan">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lantai</label>
                            <select name="lantai" id="lantai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="1">Lantai 1</option>
                                <option value="2">Lantai 2</option>
                                <option value="3">Lantai 3</option>
                                <option value="5">Lantai 5</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
                            <select name="jabatan_keperawatan" id="jabatan_keperawatan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <?php foreach ($jabatanKeperawatanOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Karyawan <span class="text-red-500">*</span></label>
                            <select name="status_kepegawaian" id="status_karyawan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="Tetap" selected>Tetap</option>
                                <option value="Tidak Tetap">Tidak Tetap</option>
                            </select>
                        </div>
                        <div id="container_sk" class="mt-0">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor SK Direktur</label>
                            <input type="text" name="nomor_keputusan_direktur" id="nomor_keputusan_direktur" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SK Direktur">
                        </div>
                        <div id="container_pkwt" class="mt-0 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor PKWT</label>
                            <input type="text" name="nomor_pkwt" id="nomor_pkwt" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor PKWT">
                        </div>
                    </div>
                    <div class="space-y-2 pt-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rincian Kewenangan Klinis</label>
                        <textarea name="rincian_kewenangan_klinis" id="rincian_kewenangan_klinis" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan rincian kewenangan klinis"></textarea>
                    </div>
                </div>

                <!-- Dokumen Legalitas -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Dokumen Legalitas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- STR -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">STR (Surat Tanda Registrasi)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. STR</label>
                                    <input type="text" name="no_str" id="no_str" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor STR">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_str_mulai" id="masa_berlaku_str_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_str_akhir" id="masa_berlaku_str_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">STR (PDF)</label>
                                    <input type="file" name="file_str" id="file_str" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                                    <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin mengubah berkas</p>
                                </div>
                            </div>
                        </div>

                        <!-- SIP -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">SIP (Surat Izin Praktik)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. SIP</label>
                                    <input type="text" name="no_sip" id="no_sip" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SIP">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_sip_mulai" id="masa_berlaku_sip_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_sip_akhir" id="masa_berlaku_sip_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SIP (PDF)</label>
                                    <input type="file" name="file_sip" id="file_sip" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                                    <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin mengubah berkas</p>
                                </div>
                            </div>
                        </div>

                        <!-- PKS -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">PKS (Perjanjian Kerja Sama)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. PKS</label>
                                    <input type="text" name="no_pks" id="no_pks" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor PKS">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_pks_mulai" id="masa_berlaku_pks_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_pks_akhir" id="masa_berlaku_pks_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">PKS (PDF)</label>
                                    <input type="file" name="file_pks" id="file_pks" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                                    <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin mengubah berkas</p>
                                </div>
                            </div>
                        </div>

                        <!-- SK -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">SK (Surat Keputusan)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. SK</label>
                                    <input type="text" name="no_sk" id="no_sk" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SK">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_sk_mulai" id="masa_berlaku_sk_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_sk_akhir" id="masa_berlaku_sk_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SK (PDF)</label>
                                    <input type="file" name="file_sk" id="file_sk" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                                    <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin mengubah berkas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kompetensi -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Kompetensi</h3>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Sertifikasi Kompetensi</label>
                            <button type="button" onclick="addSertifikasi()" class="text-emerald-600 hover:text-emerald-700 text-sm font-medium flex items-center gap-1">
                                <span>+</span> Tambah Sertifikasi
                            </button>
                        </div>
                        <div id="sertifikasi-container" class="space-y-2">
                            <div class="flex gap-2">
                                <input type="text" name="sertifikasi[]" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan sertifikasi">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kompetensi Klinis</label>
                        <textarea name="kompetensi_klinis" id="kompetensi_klinis" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Tulis daftar tindakan medis yang diizinkan"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('modal')" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-300 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_tenaga_medis" id="submit-btn" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Import Excel</h2>
                <button onclick="closeModal('importModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" action="export_handler.php?action=import_data&module=tenaga_medis&subpage=komite-keperawatan" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih File CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('importModal')" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Import
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Override openModal
        const originalOpenModal = window.openModal;
        window.openModal = function(modalId) {
            if (modalId === 'modal' || !modalId) {
                resetForm();
                document.getElementById('submit-btn').name = 'tambah_tenaga_medis';
                document.getElementById('submit-btn').textContent = 'Simpan Data';
                document.getElementById('modal_title').textContent = 'Form Tambah Tenaga Medis - Komite Keperawatan';
            }
            if (originalOpenModal) {
                originalOpenModal(modalId);
            } else {
                const element = document.getElementById(modalId || 'modal');
                if (element) {
                    element.classList.remove('hidden');
                    element.classList.add('flex');
                }
            }
        };

        function resetForm() {
            document.getElementById('edit_id').value = '';
            document.getElementById('nama_lengkap').value = '';
            document.getElementById('unit_ruangan').value = '';
            document.getElementById('lantai').value = '1';
            document.getElementById('jabatan_keperawatan').value = '<?php echo $jabatanKeperawatanOptions[0]; ?>';
            document.getElementById('status_karyawan').value = 'Tetap';
            document.getElementById('container_sk').classList.remove('hidden');
            document.getElementById('container_pkwt').classList.add('hidden');
            document.getElementById('nomor_keputusan_direktur').value = '';
            document.getElementById('nomor_pkwt').value = '';
            document.getElementById('rincian_kewenangan_klinis').value = '';
            document.getElementById('no_str').value = '';
            document.getElementById('masa_berlaku_str_mulai').value = '';
            document.getElementById('masa_berlaku_str_akhir').value = '';
            document.getElementById('file_str').value = '';
            document.getElementById('no_sip').value = '';
            document.getElementById('masa_berlaku_sip_mulai').value = '';
            document.getElementById('masa_berlaku_sip_akhir').value = '';
            document.getElementById('file_sip').value = '';
            document.getElementById('no_pks').value = '';
            document.getElementById('masa_berlaku_pks_mulai').value = '';
            document.getElementById('masa_berlaku_pks_akhir').value = '';
            document.getElementById('file_pks').value = '';
            document.getElementById('no_sk').value = '';
            document.getElementById('masa_berlaku_sk_mulai').value = '';
            document.getElementById('masa_berlaku_sk_akhir').value = '';
            document.getElementById('file_sk').value = '';
            document.getElementById('kompetensi_klinis').value = '';
            document.getElementById('sertifikasi-container').innerHTML = `
                <div class="flex gap-2">
                    <input type="text" name="sertifikasi[]" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan sertifikasi">
                </div>
            `;
        }

        function openEditModal(doc) {
            document.getElementById('edit_id').value = doc.id;
            document.getElementById('nama_lengkap').value = doc.nama_lengkap || '';
            document.getElementById('unit_ruangan').value = doc.unit_ruangan || '';
            document.getElementById('lantai').value = doc.lantai || '1';
            document.getElementById('jabatan_keperawatan').value = doc.jabatan_keperawatan || '<?php echo $jabatanKeperawatanOptions[0]; ?>';
            document.getElementById('status_karyawan').value = doc.status_kepegawaian || 'Tetap';
            if (doc.status_kepegawaian === 'Tetap') {
                document.getElementById('container_sk').classList.remove('hidden');
                document.getElementById('container_pkwt').classList.add('hidden');
            } else {
                document.getElementById('container_sk').classList.add('hidden');
                document.getElementById('container_pkwt').classList.remove('hidden');
            }
            document.getElementById('nomor_keputusan_direktur').value = doc.nomor_keputusan_direktur || '';
            document.getElementById('nomor_pkwt').value = doc.nomor_pkwt || '';
            document.getElementById('rincian_kewenangan_klinis').value = doc.rincian_kewenangan_klinis || '';
            document.getElementById('no_str').value = doc.no_str || '';
            document.getElementById('masa_berlaku_str_mulai').value = doc.masa_berlaku_str_mulai || '';
            document.getElementById('masa_berlaku_str_akhir').value = doc.masa_berlaku_str_akhir || '';
            document.getElementById('no_sip').value = doc.no_sip || '';
            document.getElementById('masa_berlaku_sip_mulai').value = doc.masa_berlaku_sip_mulai || '';
            document.getElementById('masa_berlaku_sip_akhir').value = doc.masa_berlaku_sip_akhir || '';
            document.getElementById('no_pks').value = doc.no_pks || '';
            document.getElementById('masa_berlaku_pks_mulai').value = doc.masa_berlaku_pks_mulai || '';
            document.getElementById('masa_berlaku_pks_akhir').value = doc.masa_berlaku_pks_akhir || '';
            document.getElementById('no_sk').value = doc.no_sk || '';
            document.getElementById('masa_berlaku_sk_mulai').value = doc.masa_berlaku_sk_mulai || '';
            document.getElementById('masa_berlaku_sk_akhir').value = doc.masa_berlaku_sk_akhir || '';
            document.getElementById('kompetensi_klinis').value = doc.kompetensi_klinis || '';

            // Handle sertifikasi
            const sertifikasi = doc.sertifikasi_kompetensi ? JSON.parse(doc.sertifikasi_kompetensi) : [];
            const container = document.getElementById('sertifikasi-container');
            container.innerHTML = '';
            sertifikasi.forEach(sert => {
                container.insertAdjacentHTML('beforeend', `
                    <div class="flex gap-2">
                        <input type="text" name="sertifikasi[]" value="${sert}" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan sertifikasi">
                    </div>
                `);
            });
            if (sertifikasi.length === 0) {
                container.innerHTML = `
                    <div class="flex gap-2">
                        <input type="text" name="sertifikasi[]" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan sertifikasi">
                    </div>
                `;
            }

            document.getElementById('file_str').value = '';
            document.getElementById('file_sip').value = '';
            document.getElementById('file_pks').value = '';
            document.getElementById('file_sk').value = '';

            document.getElementById('submit-btn').name = 'edit_tenaga_medis';
            document.getElementById('submit-btn').textContent = 'Simpan Perubahan';
            document.getElementById('modal_title').textContent = 'Edit Komite Keperawatan';

            const modal = document.getElementById('modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal(modalId) {
            const element = document.getElementById(modalId);
            if (element) {
                element.classList.add('hidden');
                element.classList.remove('flex');
            }
        }

        function addSertifikasi() {
            const container = document.getElementById('sertifikasi-container');
            const template = `
                <div class="flex gap-2">
                    <input type="text" name="sertifikasi[]" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan sertifikasi">
                </div>
            `;
            container.insertAdjacentHTML('beforeend', template);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const statusKaryawanDropdown = document.getElementById('status_karyawan');
            const containerSK = document.getElementById('container_sk');
            const containerPKWT = document.getElementById('container_pkwt');
            
            if (statusKaryawanDropdown && containerSK && containerPKWT) {
                statusKaryawanDropdown.addEventListener('change', function() {
                    const status = this.value;
                    if (status === 'Tetap') {
                        containerSK.classList.remove('hidden');
                        containerPKWT.classList.add('hidden');
                    } else if (status === 'Tidak Tetap') {
                        containerSK.classList.add('hidden');
                        containerPKWT.classList.remove('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html>
