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

// Handle form submission for adding new Perizinan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_perizinan'])) {
    $nama_izin = $_POST['nama_izin'] ?? null;
    $pemilik_izin = $_POST['pemilik_izin'] ?? null;
    $masa_berlaku_mulai = $_POST['masa_berlaku_mulai'] ?? null;
    $masa_berlaku_akhir = $_POST['masa_berlaku_akhir'] ?? null;
    $instansi_penerbit = $_POST['instansi_penerbit'] ?? null;
    $penanggung_jawab = $_POST['penanggung_jawab'] ?? null;

    $file_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/perizinan/';
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
            INSERT INTO dokumen_perizinan (nama_izin, pemilik_izin, masa_berlaku_mulai, masa_berlaku_akhir, instansi_penerbit, penanggung_jawab, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nama_izin, $pemilik_izin, $masa_berlaku_mulai, $masa_berlaku_akhir, $instansi_penerbit, $penanggung_jawab, $file_path]);

        $_SESSION['pks_success'] = "Dokumen Perizinan berhasil ditambahkan!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menyimpan data: " . $e->getMessage();
    }

    header("Location: perizinan.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    if (!isUserLegalOrAdmin()) {
        $_SESSION['pks_error'] = "Anda tidak memiliki akses untuk menghapus data ini!";
        header("Location: perizinan.php");
        exit;
    }
    $id = (int)$_GET['delete'];
    try {
        // Get file path before deleting
        $stmt = $pdo->prepare("SELECT file_path FROM dokumen_perizinan WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM dokumen_perizinan WHERE id = ?");
        $stmt->execute([$id]);

        // Delete file if exists
        if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        $_SESSION['pks_success'] = "Dokumen Perizinan berhasil dihapus!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: perizinan.php");
    exit;
}

// Capture filter query
$filter_pemilik = isset($_GET['pemilik_izin']) ? trim($_GET['pemilik_izin']) : '';

// Get Perizinan documents
try {
    if (!empty($filter_pemilik)) {
        $stmt = $pdo->prepare("
            SELECT * FROM dokumen_perizinan 
            WHERE pemilik_izin = :pemilik_izin
            ORDER BY created_at DESC
        ");
        $stmt->execute(['pemilik_izin' => $filter_pemilik]);
    } else {
        $stmt = $pdo->query("SELECT * FROM dokumen_perizinan ORDER BY created_at DESC");
    }
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Calculate stats for Perizinan
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_perizinan");
    $totalDocs = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_perizinan WHERE pemilik_izin = ?");
    $stmt->execute(['RS THB']);
    $rsthb = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_perizinan WHERE pemilik_izin = ?");
    $stmt->execute(['PT PBA']);
    $ptpba = $stmt->fetchColumn();

    $today = new DateTime();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_perizinan WHERE masa_berlaku_akhir > ?");
    $stmt->execute([$today->format('Y-m-d')]);
    $aktif = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalDocs = 0;
    $rsthb = 0;
    $ptpba = 0;
    $aktif = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perizinan - RS Taman Harapan Baru</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Perizinan</h1>
                    <p class="text-gray-600 mt-2">Pengelolaan seluruh izin operasional dan perizinan rumah sakit</p>
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
                    <a href="export_handler.php?action=export_data&module=perizinan" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        📊
                        <span>Export Excel</span>
                    </a>
                    <button onclick="openImportModal()" class="flex items-center gap-2 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        📤
                        <span>Import Excel</span>
                    </button>
                    <a href="export_handler.php?action=download_template&module=perizinan" class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                        📄
                        <span>Download Template</span>
                    </a>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Perizinan</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">RS THB</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $rsthb; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">🏢</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">PT PBA</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $ptpba; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">🏢</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Aktif</p>
                                <h3 class="text-3xl font-bold text-purple-600"><?php echo $aktif; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">✅</div>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <!-- Search Bar -->
                    <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
                        <div class="flex flex-col md:flex-row md:items-center gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Daftar Dokumen Perizinan</h2>
                                <p class="text-xs text-gray-500 mt-1">Total: <?php echo count($documents); ?> dokumen ditemukan</p>
                            </div>
                            <form method="GET" class="flex items-center gap-2">
                                <div class="relative w-64">
                                    <select 
                                        name="pemilik_izin" 
                                        onchange="this.form.submit()"
                                        class="w-full pl-10 pr-8 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:border-emerald-500 transition-all appearance-none cursor-pointer"
                                    >
                                        <option value="">-- Semua Pemilik Izin --</option>
                                        <option value="RS THB" <?php echo $filter_pemilik === 'RS THB' ? 'selected' : ''; ?>>RS THB</option>
                                        <option value="PT PBA" <?php echo $filter_pemilik === 'PT PBA' ? 'selected' : ''; ?>>PT PBA</option>
                                    </select>
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">📁</span>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">▼</span>
                                </div>
                                <?php if (!empty($filter_pemilik)): ?>
                                    <a href="perizinan.php" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl transition-colors">
                                        Reset
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nama Izin</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Pemilik Izin</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Masa Berlaku</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Instansi Penerbit</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Penanggung Jawab</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada dokumen perizinan yang tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $index => $doc): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['nama_izin'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php $pemilik_izin = $doc['pemilik_izin'] ?? '-'; ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $pemilik_izin === 'RS THB' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-blue-100 text-blue-800 border-blue-200'; ?>">
                                                    <?php echo htmlspecialchars($pemilik_izin); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo formatDate($doc['masa_berlaku_mulai'] ?? null); ?> - <?php echo formatDate($doc['masa_berlaku_akhir'] ?? null); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo htmlspecialchars($doc['instansi_penerbit'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo htmlspecialchars($doc['penanggung_jawab'] ?? '-'); ?></p>
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
                                                        <a href="perizinan.php?delete=<?php echo $doc['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
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

    <!-- Modal for Perizinan -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Tambah Dokumen Perizinan</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Izin</label>
                    <input type="text" name="nama_izin" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pemilik Izin</label>
                    <select name="pemilik_izin" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="RS THB">RS THB</option>
                        <option value="PT PBA">PT PBA</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku Mulai</label>
                        <input type="date" name="masa_berlaku_mulai" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku Akhir</label>
                        <input type="date" name="masa_berlaku_akhir" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Instansi Penerbit</label>
                    <input type="text" name="instansi_penerbit" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Penanggung Jawab</label>
                    <input type="text" name="penanggung_jawab" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas (PDF)</label>
                    <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_perizinan" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
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
            <form method="POST" action="export_handler.php?action=import_data&module=perizinan" enctype="multipart/form-data" class="p-6 space-y-4">
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
    </script>
</body>
</html>
