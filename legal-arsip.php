<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Check access permission for Legal
if (!isUserLegalOrAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

function getLegalArsipStatus($tanggalBerakhir) {
    if (!$tanggalBerakhir) {
        return ['text' => 'Tidak Ada Tanggal Berakhir', 'class' => 'bg-gray-100 text-gray-800 border-gray-200'];
    }
    $today = new DateTime();
    $berakhir = new DateTime($tanggalBerakhir);
    $diff = $today->diff($berakhir)->days;
    $invert = $today->diff($berakhir)->invert;

    if ($invert == 1) {
        return ['text' => 'Expired / Kadaluarsa', 'class' => 'bg-red-600 text-white border-red-700'];
    } elseif ($diff <= 60) {
        return ['text' => 'Mendekati Kadaluarsa', 'class' => 'bg-amber-100 text-amber-800 border-amber-200'];
    } else {
        return ['text' => 'Aktif', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200'];
    }
}

// Handle form submission for adding new Arsip Dokumen Legal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_arsip_legal'])) {
    $tipe_kontrak = $_POST['tipe_kontrak'] ?? null;
    $perusahaan = $_POST['perusahaan'] ?? null;
    $ruang_lingkup = $_POST['ruang_lingkup'] ?? null;
    $nilai_kontrak = !empty($_POST['nilai_kontrak']) ? (float)$_POST['nilai_kontrak'] : null;
    $potongan_harga = $_POST['potongan_harga'] ?? null;
    $cara_pembayaran = $_POST['cara_pembayaran'] ?? null;
    $tanggal_mulai = !empty($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : null;
    $tanggal_berakhir = !empty($_POST['tanggal_berakhir']) ? $_POST['tanggal_berakhir'] : null;
    $nama_pj = $_POST['nama_pj'] ?? null;
    $no_telp_pj = $_POST['no_telp_pj'] ?? null;

    $file_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/arsip_legal/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $file_path = $targetFile;
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO dokumen_arsip_legal (tipe_kontrak, perusahaan, ruang_lingkup, nilai_kontrak, potongan_harga, cara_pembayaran, tanggal_mulai, tanggal_berakhir, nama_pj, no_telp_pj, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tipe_kontrak, $perusahaan, $ruang_lingkup, $nilai_kontrak, $potongan_harga, $cara_pembayaran, $tanggal_mulai, $tanggal_berakhir, $nama_pj, $no_telp_pj, $file_path]);

        $_SESSION['pks_success'] = "Dokumen Arsip Legal berhasil ditambahkan!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menyimpan data: " . $e->getMessage();
    }

    header("Location: legal-arsip.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    if (!isUserLegalOrAdmin()) {
        $_SESSION['pks_error'] = "Anda tidak memiliki akses untuk menghapus data ini!";
        header("Location: legal-arsip.php");
        exit;
    }
    $id = (int)$_GET['delete'];
    try {
        // Get file path before deleting
        $stmt = $pdo->prepare("SELECT file_path FROM dokumen_arsip_legal WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM dokumen_arsip_legal WHERE id = ?");
        $stmt->execute([$id]);

        // Delete file if exists
        if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        $_SESSION['pks_success'] = "Dokumen Arsip Legal berhasil dihapus!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: legal-arsip.php");
    exit;
}

// Capture filter query
$filter_tipe = isset($_GET['tipe_kontrak']) ? trim($_GET['tipe_kontrak']) : '';

// Get Arsip documents
try {
    if (!empty($filter_tipe)) {
        $stmt = $pdo->prepare("
            SELECT * FROM dokumen_arsip_legal 
            WHERE tipe_kontrak = :tipe_kontrak
            ORDER BY created_at DESC
        ");
        $stmt->execute(['tipe_kontrak' => $filter_tipe]);
    } else {
        $stmt = $pdo->query("SELECT * FROM dokumen_arsip_legal ORDER BY created_at DESC");
    }
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Calculate stats for Arsip
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_arsip_legal");
    $totalDocs = $stmt->fetchColumn();

    $today = new DateTime();
    $sixtyDaysFromNow = (clone $today)->add(new DateInterval('P60D'));

    // Aktif
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_arsip_legal WHERE tanggal_berakhir > ? OR tanggal_berakhir IS NULL");
    $stmt->execute([$sixtyDaysFromNow->format('Y-m-d')]);
    $aktif = $stmt->fetchColumn();

    // Mendekati kadaluarsa
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_arsip_legal WHERE tanggal_berakhir BETWEEN ? AND ?");
    $stmt->execute([$today->format('Y-m-d'), $sixtyDaysFromNow->format('Y-m-d')]);
    $mendekati = $stmt->fetchColumn();

    // Kadaluarsa
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_arsip_legal WHERE tanggal_berakhir < ?");
    $stmt->execute([$today->format('Y-m-d')]);
    $kadaluarsa = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalDocs = 0;
    $aktif = 0;
    $mendekati = 0;
    $kadaluarsa = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arsip Dokumen - RS Taman Harapan Baru</title>
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
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Arsip Dokumen Legal</h1>
                    <p class="text-gray-600 mt-2">Tempat menyimpan dokumen umum legal dan pengingat otomatis kadaluarsa</p>
                </div>

                <?php if (isset($_SESSION['pks_success'])): ?>
                    <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl">
                        <?php echo htmlspecialchars($_SESSION['pks_success']); ?>
                    </div>
                    <?php unset($_SESSION['pks_success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['pks_error'])): ?>
                    <div class="p-4 bg-red-100 text-red-800 rounded-xl">
                        <?php echo htmlspecialchars($_SESSION['pks_error']); ?>
                    </div>
                    <?php unset($_SESSION['pks_error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['import_success'])): ?>
                    <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl">
                        <?php echo htmlspecialchars($_SESSION['import_success']); ?>
                    </div>
                    <?php unset($_SESSION['import_success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['import_error'])): ?>
                    <div class="p-4 bg-red-100 text-red-800 rounded-xl">
                        <?php echo htmlspecialchars($_SESSION['import_error']); ?>
                    </div>
                    <?php unset($_SESSION['import_error']); ?>
                <?php endif; ?>

                <!-- Export/Import Buttons -->
                <div class="flex items-center gap-3">
                    <a href="export_handler.php?action=export_data&module=legal-arsip" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        📊
                        <span>Export Excel</span>
                    </a>
                    <button onclick="openImportModal()" class="flex items-center gap-2 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        📤
                        <span>Import Excel</span>
                    </button>
                    <a href="export_handler.php?action=download_template&module=legal-arsip" class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                        📄
                        <span>Download Template</span>
                    </a>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Dokumen</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Aktif</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $aktif; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">✅</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Mendekati Kadaluarsa</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $mendekati; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">⚠️</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Kadaluarsa</p>
                                <h3 class="text-3xl font-bold text-red-600"><?php echo $kadaluarsa; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-red-550 to-red-600 rounded-2xl flex items-center justify-center text-3xl">❌</div>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <!-- Search Bar -->
                    <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
                        <div class="flex flex-col md:flex-row md:items-center gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Daftar Arsip Dokumen Legal</h2>
                                <p class="text-xs text-gray-500 mt-1">Total: <?php echo count($documents); ?> dokumen ditemukan</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- Input Pencarian -->
                                <div class="relative w-64">
                                    <input 
                                        type="text" 
                                        id="search-input" 
                                        placeholder="Cari arsip..." 
                                        onkeyup="filterTable()"
                                        class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:border-emerald-500 transition-all"
                                    >
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">🔍</span>
                                </div>
                                <form method="GET" class="flex items-center gap-2">
                                    <div class="relative w-64">
                                        <select 
                                            name="tipe_kontrak" 
                                            onchange="this.form.submit()"
                                            class="w-full pl-10 pr-8 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:border-emerald-500 transition-all appearance-none cursor-pointer"
                                        >
                                            <option value="">-- Semua Tipe Kontrak --</option>
                                            <option value="Asuransi" <?php echo $filter_tipe === 'Asuransi' ? 'selected' : ''; ?>>Asuransi</option>
                                            <option value="Perusahaan" <?php echo $filter_tipe === 'Perusahaan' ? 'selected' : ''; ?>>Perusahaan</option>
                                            <option value="Umum" <?php echo $filter_tipe === 'Umum' ? 'selected' : ''; ?>>Umum</option>
                                            <option value="Operasional" <?php echo $filter_tipe === 'Operasional' ? 'selected' : ''; ?>>Operasional</option>
                                            <option value="Farmasi" <?php echo $filter_tipe === 'Farmasi' ? 'selected' : ''; ?>>Farmasi</option>
                                            <option value="Alat Kesehatan" <?php echo $filter_tipe === 'Alat Kesehatan' ? 'selected' : ''; ?>>Alat Kesehatan</option>
                                            <option value="Penelitian/Pendidikan" <?php echo $filter_tipe === 'Penelitian/Pendidikan' ? 'selected' : ''; ?>>Penelitian/Pendidikan</option>
                                        </select>
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">📁</span>
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">▼</span>
                                    </div>
                                    <?php if (!empty($filter_tipe)): ?>
                                        <a href="legal-arsip.php" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl transition-colors">
                                            Reset
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tipe Kontrak</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Perusahaan/Instansi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Ruang Lingkup Kerjasama</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nilai Kontrak</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Potongan Harga</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Cara Pembayaran</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Jangka Waktu</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Penanggung Jawab Mitra</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="12" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada dokumen arsip yang tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $index => $doc): 
                                        $status = getLegalArsipStatus($doc['tanggal_berakhir'] ?? null);
                                    ?>
                                        <tr class="hover:bg-gray-50 <?php echo $status['text'] === 'Expired / Kadaluarsa' ? 'bg-red-50' : ''; ?>">
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border bg-blue-100 text-blue-800 border-blue-200">
                                                    <?php echo htmlspecialchars($doc['tipe_kontrak'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['perusahaan'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo htmlspecialchars($doc['ruang_lingkup'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm font-semibold">
                                                    <?php echo $doc['nilai_kontrak'] ? 'Rp ' . number_format($doc['nilai_kontrak'], 2, ',', '.') : '-'; ?>
                                                </p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo !empty($doc['potongan_harga']) ? htmlspecialchars($doc['potongan_harga']) : '-'; ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo !empty($doc['cara_pembayaran']) ? htmlspecialchars($doc['cara_pembayaran']) : '-'; ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm">
                                                    <?php echo formatDate($doc['tanggal_mulai'] ?? null); ?> - <?php echo formatDate($doc['tanggal_berakhir'] ?? null); ?>
                                                </p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm">
                                                    <strong><?php echo htmlspecialchars($doc['nama_pj'] ?? '-'); ?></strong>
                                                    <?php $no_telp_pj = $doc['no_telp_pj'] ?? ''; ?>
                                                    <?php if (!empty($no_telp_pj)): ?>
                                                        <br><small class="text-gray-500"><?php echo htmlspecialchars($no_telp_pj); ?></small>
                                                    <?php endif; ?>
                                                </p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $status['class']; ?>">
                                                    <?php echo $status['text']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                <?php if (!empty($file_path)): ?>
                                                    <a href="download_pdf.php?file=<?php echo urlencode($file_path); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                        📥
                                                        <span>Download</span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-sm">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                    <?php if (!empty($file_path)): ?>
                                                        <a href="view_pdf.php?file=<?php echo urlencode($file_path); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                            Lihat
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (isUserLegalOrAdmin()): ?>
                                                        <a href="legal-arsip.php?delete=<?php echo $doc['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                                            Hapus
                                                        </a>
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

    <!-- Modal for Legal Arsip -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Tambah Dokumen Arsip Legal</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Kontrak</label>
                    <select name="tipe_kontrak" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="Asuransi">Asuransi</option>
                        <option value="Perusahaan">Perusahaan</option>
                        <option value="Alat Kesehatan">Alat Kesehatan</option>
                        <option value="Farmasi">Farmasi</option>
                        <option value="Penelitian/Pendidikan">Penelitian/Pendidikan</option>
                        <option value="Operasional">Operasional</option>
                        <option value="Umum">Umum</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Perusahaan/Instansi</label>
                    <input type="text" name="perusahaan" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ruang Lingkup Kerjasama</label>
                    <textarea name="ruang_lingkup" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nilai Kontrak</label>
                    <input type="number" name="nilai_kontrak" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Potongan Harga</label>
                        <input type="text" name="potongan_harga" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Misal: 10%, Rp 50.000, dll.">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cara Pembayaran</label>
                        <input type="text" name="cara_pembayaran" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Misal: Transfer, Tahunan, dll.">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Berakhir</label>
                        <input type="date" name="tanggal_berakhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Penanggung Jawab Mitra</label>
                        <input type="text" name="nama_pj" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon Penanggung Jawab</label>
                        <input type="text" name="no_telp_pj" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas (PDF)</label>
                    <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_arsip_legal" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Import Excel (CSV)</h2>
                <button onclick="closeImportModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" action="export_handler.php?action=import_data&module=legal-arsip" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih File CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                    <p class="text-xs text-gray-500 mt-2">Pastikan format file sesuai dengan template. Tanggal dapat menggunakan format DD/MM/YYYY atau YYYY-MM-DD.</p>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeImportModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
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
        function openImportModal() {
            openModal('importModal');
        }

        function closeImportModal() {
            closeModal('importModal');
        }
        
        function closeModal(modalId = 'modal') {
            const element = document.getElementById(modalId);
            if (element) {
                element.classList.add('hidden');
                element.classList.remove('flex');
            }
        }

        function filterTable() {
            const input = document.getElementById('search-input');
            const filter = input.value.toLowerCase();
            const tbody = document.querySelector('table tbody');
            const rows = tbody.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                if (rows[i].cells.length === 1 && rows[i].cells[0].colSpan > 1) {
                    continue;
                }
                let match = false;
                const cells = rows[i].getElementsByTagName('td');
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent || cells[j].innerText;
                    if (cellText.toLowerCase().indexOf(filter) > -1) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }
    </script>
</body>
</html>
