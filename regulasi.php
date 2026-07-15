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

// Helper: upload multiple files, return JSON-encoded array of paths (or null)
function uploadRegulasiFiles(array $filesInput, string $uploadDir = 'uploads/regulasi/'): ?string {
    if (!isset($filesInput['name']) || !is_array($filesInput['name'])) {
        return null;
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $paths = [];
    $count = count($filesInput['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($filesInput['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        $originalName = basename($filesInput['name'][$i]);
        $fileName = uniqid() . '_' . $originalName;
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($filesInput['tmp_name'][$i], $targetFile)) {
            $paths[] = $targetFile;
        }
    }
    return count($paths) > 0 ? json_encode($paths) : null;
}

// Helper: decode file_path (supports legacy single path string and new JSON array)
function decodeFilePaths(?string $raw): array {
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return $decoded;
    // Legacy: single path string
    return [$raw];
}

// Helper: delete files from disk given encoded file_path value
function deleteRegulasiFiles(?string $raw): void {
    foreach (decodeFilePaths($raw) as $path) {
        if (!empty($path) && file_exists($path)) {
            unlink($path);
        }
    }
}

// Handle form submission for adding new Regulasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_regulasi'])) {
    if (!canUserEditOrDelete('legal')) {
        $_SESSION['pks_error'] = "Anda tidak memiliki akses untuk menambah data!";
        header("Location: regulasi.php");
        exit;
    }
    $judul_regulasi = $_POST['judul_regulasi'] ?? null;
    $nomor_regulasi = $_POST['nomor_regulasi'] ?? null;
    $kategori_regulasi = $_POST['kategori_regulasi'] ?? null;
    $tanggal_terbit = $_POST['tanggal_terbit'] ?? null;
    $penanggung_jawab = $_POST['penanggung_jawab'] ?? null;

    // Handle multiple file uploads
    $file_path = null;
    if (!empty($_FILES['files']['name'][0])) {
        $file_path = uploadRegulasiFiles($_FILES['files']);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO dokumen_regulasi (judul_regulasi, nomor_regulasi, kategori_regulasi, tanggal_terbit, penanggung_jawab, file_path)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$judul_regulasi, $nomor_regulasi, $kategori_regulasi, $tanggal_terbit, $penanggung_jawab, $file_path]);
        
        $_SESSION['pks_success'] = "Dokumen Regulasi berhasil ditambahkan!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menyimpan data: " . $e->getMessage();
    }
    
    header("Location: regulasi.php");
    exit;
}

// Handle form submission for editing Regulasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_regulasi'])) {
    if (!canUserEditOrDelete('legal')) {
        $_SESSION['pks_error'] = "Anda tidak memiliki akses untuk mengedit data!";
        header("Location: regulasi.php");
        exit;
    }
    
    $edit_id = (int)$_POST['edit_id'];
    $judul_regulasi = $_POST['judul_regulasi'] ?? null;
    $nomor_regulasi = $_POST['nomor_regulasi'] ?? null;
    $kategori_regulasi = $_POST['kategori_regulasi'] ?? null;
    $tanggal_terbit = $_POST['tanggal_terbit'] ?? null;
    $penanggung_jawab = $_POST['penanggung_jawab'] ?? null;
    // Files to remove (indexes sent from JS as JSON array of paths)
    $remove_files = json_decode($_POST['remove_files'] ?? '[]', true);
    if (!is_array($remove_files)) $remove_files = [];

    // Get current file paths
    $stmt = $pdo->prepare("SELECT file_path FROM dokumen_regulasi WHERE id = ?");
    $stmt->execute([$edit_id]);
    $current_doc = $stmt->fetch();
    $existing_paths = decodeFilePaths($current_doc['file_path'] ?? null);

    // Remove files the user explicitly deleted
    foreach ($remove_files as $rp) {
        if (!empty($rp) && file_exists($rp)) {
            unlink($rp);
        }
        $existing_paths = array_values(array_filter($existing_paths, fn($p) => $p !== $rp));
    }

    // Append newly uploaded files
    if (!empty($_FILES['files']['name'][0])) {
        $new_encoded = uploadRegulasiFiles($_FILES['files']);
        if ($new_encoded) {
            $new_paths = json_decode($new_encoded, true);
            $existing_paths = array_merge($existing_paths, $new_paths);
        }
    }

    $file_path = count($existing_paths) > 0 ? json_encode(array_values($existing_paths)) : null;

    try {
        $stmt = $pdo->prepare("
            UPDATE dokumen_regulasi 
            SET judul_regulasi = ?, nomor_regulasi = ?, kategori_regulasi = ?, tanggal_terbit = ?, penanggung_jawab = ?, file_path = ?
            WHERE id = ?
        ");
        $stmt->execute([$judul_regulasi, $nomor_regulasi, $kategori_regulasi, $tanggal_terbit, $penanggung_jawab, $file_path, $edit_id]);
        
        $_SESSION['pks_success'] = "Dokumen Regulasi berhasil diperbarui!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal memperbarui data: " . $e->getMessage();
    }
    
    header("Location: regulasi.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    if (!canUserEditOrDelete('legal')) {
        $_SESSION['pks_error'] = "Anda tidak memiliki akses untuk menghapus data ini!";
        header("Location: regulasi.php");
        exit;
    }
    $id = (int)$_GET['delete'];
    try {
        // Get file path(s) before deleting
        $stmt = $pdo->prepare("SELECT file_path FROM dokumen_regulasi WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM dokumen_regulasi WHERE id = ?");
        $stmt->execute([$id]);

        // Delete all associated files from disk
        if ($doc) {
            deleteRegulasiFiles($doc['file_path'] ?? null);
        }
        $_SESSION['pks_success'] = "Dokumen Regulasi berhasil dihapus!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: regulasi.php");
    exit;
}

// Capture category filter query
$kategori_filter = isset($_GET['kategori_filter']) ? trim($_GET['kategori_filter']) : '';

// Get Regulasi documents
try {
    $sql = "SELECT * FROM dokumen_regulasi WHERE 1=1";
    $params = [];
    
    if (!empty($kategori_filter)) {
        $sql .= " AND kategori_regulasi = :kategori_filter";
        $params['kategori_filter'] = $kategori_filter;
    }
    
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Calculate stats for Regulasi
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_regulasi");
    $totalDocs = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_regulasi WHERE kategori_regulasi = ?");
    $stmt->execute(['SPO']);
    $spo = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_regulasi WHERE kategori_regulasi = ?");
    $stmt->execute(['Peraturan Direktur']);
    $perdir = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_regulasi WHERE kategori_regulasi = ?");
    $stmt->execute(['Kebijakan Mutu']);
    $kebijakan = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalDocs = 0;
    $spo = 0;
    $perdir = 0;
    $kebijakan = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regulasi - RS Taman Harapan Baru</title>
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
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Regulasi</h1>
                        <p class="text-gray-600 mt-2">Arsip dan manajemen seluruh peraturan dan undang-undang terkait kesehatan</p>
                    </div>
                    <a href="pengajuan.php" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors shadow-sm">
                        📝
                        <span>Pengajuan Dokumen</span>
                    </a>
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
                    <a href="export_handler.php?action=export_data&module=regulasi" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        📊
                        <span>Export Excel</span>
                    </a>
                    <button onclick="openImportModal()" class="flex items-center gap-2 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        📤
                        <span>Import Excel</span>
                    </button>
                    <a href="export_handler.php?action=download_template&module=regulasi" class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                        📄
                        <span>Download Template</span>
                    </a>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">
                                    Total Regulasi
                                </p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">SPO</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $spo; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">📝</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Peraturan Direktur</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $perdir; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">🏢</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Kebijakan Mutu</p>
                                <h3 class="text-3xl font-bold text-purple-600"><?php echo $kebijakan; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">🏅</div>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <!-- Search Bar -->
                    <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
                        <div class="flex flex-col md:flex-row md:items-center gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Daftar Dokumen Regulasi</h2>
                                <p class="text-xs text-gray-500 mt-1">Total: <?php echo count($documents); ?> dokumen ditemukan</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- Input Pencarian -->
                                <div class="relative w-64">
                                    <input 
                                        type="text" 
                                        id="search-input" 
                                        placeholder="Cari regulasi..." 
                                        onkeyup="filterTable()"
                                        class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:border-emerald-500 transition-all"
                                    >
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">🔍</span>
                                </div>
                                <form method="GET" class="flex items-center gap-2">
                                    <div class="relative w-64">
                                        <select 
                                            name="kategori_filter" 
                                            onchange="this.form.submit()"
                                            class="w-full pl-10 pr-8 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:border-emerald-500 transition-all appearance-none cursor-pointer"
                                        >
                                            <option value="">-- Semua Regulasi --</option>
                                            <option value="SPO" <?php echo $kategori_filter === 'SPO' ? 'selected' : ''; ?>>SPO</option>
                                            <option value="Peraturan Direktur" <?php echo $kategori_filter === 'Peraturan Direktur' ? 'selected' : ''; ?>>Peraturan Direktur</option>
                                            <option value="Keputusan Direktur" <?php echo $kategori_filter === 'Keputusan Direktur' ? 'selected' : ''; ?>>Keputusan Direktur</option>
                                            <option value="Kebijakan Mutu" <?php echo $kategori_filter === 'Kebijakan Mutu' ? 'selected' : ''; ?>>Kebijakan Mutu</option>
                                        </select>
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">📁</span>
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">▼</span>
                                    </div>
                                    <?php if (!empty($kategori_filter)): ?>
                                        <a href="regulasi.php" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl transition-colors">
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
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Judul Regulasi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nomor Regulasi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Kategori Regulasi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal Terbit</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Penanggung Jawab</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada dokumen regulasi yang tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $index => $doc): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['judul_regulasi'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo htmlspecialchars($doc['nomor_regulasi'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border bg-blue-100 text-blue-800 border-blue-200">
                                                    <?php echo htmlspecialchars($doc['kategori_regulasi'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo formatDate($doc['tanggal_terbit'] ?? null); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo htmlspecialchars($doc['penanggung_jawab'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php
                                                    $file_paths = decodeFilePaths($doc['file_path'] ?? null);
                                                ?>
                                                <?php if (!empty($file_paths)): ?>
                                                    <div class="flex flex-col gap-1">
                                                    <?php foreach ($file_paths as $idx => $fp): ?>
                                                        <a href="download_pdf.php?file=<?php echo urlencode($fp); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                            📥 <span>Berkas <?php echo $idx + 1; ?></span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-sm">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                <?php
                                                    $file_paths_action = decodeFilePaths($doc['file_path'] ?? null);
                                                ?>
                                                <?php if (!empty($file_paths_action)): ?>
                                                    <?php foreach ($file_paths_action as $idx => $fp): ?>
                                                        <a href="view_pdf.php?file=<?php echo urlencode($fp); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                            Lihat <?php echo count($file_paths_action) > 1 ? ($idx + 1) : ''; ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                 <?php if (canUserEditOrDelete('legal')): ?>
                                                     <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($doc), ENT_QUOTES); ?>)" class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                                                         Edit
                                                     </button>
                                                     <a href="regulasi.php?delete=<?php echo $doc['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
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

    <!-- Modal for Regulasi -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Tambah Dokumen Regulasi</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Judul Regulasi</label>
                    <input type="text" name="judul_regulasi" id="judul_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Regulasi</label>
                    <input type="text" name="nomor_regulasi" id="nomor_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Regulasi</label>
                    <select name="kategori_regulasi" id="kategori_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="SPO">SPO</option>
                        <option value="Peraturan Direktur">Peraturan Direktur</option>
                        <option value="Keputusan Direktur">Keputusan Direktur</option>
                        <option value="Kebijakan Mutu">Kebijakan Mutu</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Terbit</label>
                    <input type="date" name="tanggal_terbit" id="tanggal_terbit" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Penanggung Jawab</label>
                    <input type="text" name="penanggung_jawab" id="penanggung_jawab" placeholder="Masukkan nama penanggung jawab" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas (PDF)</label>
                    <!-- Existing files list (shown in edit mode) -->
                    <div id="existing-files-container" class="hidden mb-3 space-y-2">
                        <p class="text-xs font-medium text-gray-600">Berkas yang sudah ada:</p>
                        <ul id="existing-files-list" class="space-y-1"></ul>
                    </div>
                    <!-- Hidden input to track removed files -->
                    <input type="hidden" name="remove_files" id="remove_files" value="[]">
                    <!-- Dynamic file input rows -->
                    <div id="file-inputs-container" class="space-y-2">
                        <div class="flex items-center gap-2 file-input-row">
                            <input type="file" name="files[]" accept=".pdf"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-xl cursor-pointer text-sm">
                            <button type="button" onclick="appendFileRow()"
                                class="flex-shrink-0 w-9 h-9 flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-lg font-bold transition-colors"
                                title="Tambah berkas">+</button>
                            <button type="button" onclick="this.closest('.file-input-row').remove()"
                                class="flex-shrink-0 w-9 h-9 flex items-center justify-center bg-red-100 hover:bg-red-200 text-red-600 rounded-xl text-lg font-bold transition-colors"
                                title="Hapus baris ini">×</button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Klik <strong>+</strong> untuk menambah berkas. Biarkan kosong jika tidak ingin mengubah berkas.</p>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_regulasi" id="submitBtn" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
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
            <form method="POST" action="export_handler.php?action=import_data&module=regulasi" enctype="multipart/form-data" class="p-6 space-y-4">
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
        // Override window.openModal to reset form when adding new
        const originalOpenModal = window.openModal;
        window.openModal = function(modalId) {
            if (modalId === 'modal' || !modalId) {
                resetForm();
                document.getElementById('submitBtn').name = 'tambah_regulasi';
                document.getElementById('submitBtn').textContent = 'Simpan';
                document.querySelector('#modal h2').textContent = 'Tambah Dokumen Regulasi';
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
            document.getElementById('judul_regulasi').value = '';
            document.getElementById('nomor_regulasi').value = '';
            document.getElementById('kategori_regulasi').value = 'SPO';
            document.getElementById('tanggal_terbit').value = '';
            document.getElementById('penanggung_jawab').value = '';
            document.getElementById('remove_files').value = '[]';
            // Hide existing files section
            document.getElementById('existing-files-container').classList.add('hidden');
            document.getElementById('existing-files-list').innerHTML = '';
            // Reset file inputs to a single empty row
            resetFileInputs();
        }

        // Reset file inputs container to one empty row
        function resetFileInputs() {
            const container = document.getElementById('file-inputs-container');
            container.innerHTML = '';
            appendFileRow(); // add first row
        }

        // Append one file input row. Every row has:
        //   - file input
        //   - green "+" button (adds another row)
        //   - red "×" button (removes this row) — hidden on the very first row
        function appendFileRow() {
            const container = document.getElementById('file-inputs-container');

            const div = document.createElement('div');
            div.className = 'flex items-center gap-2 file-input-row';

            const input = document.createElement('input');
            input.type = 'file';
            input.name = 'files[]';
            input.accept = '.pdf';
            input.className = 'flex-1 px-3 py-2 border border-gray-300 rounded-xl cursor-pointer text-sm';

            // "+" button — always adds a new row
            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.title = 'Tambah berkas';
            addBtn.className = 'flex-shrink-0 w-9 h-9 flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-lg font-bold transition-colors';
            addBtn.textContent = '+';
            addBtn.onclick = function() { appendFileRow(); };

            // "×" button — removes this row
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.title = 'Hapus baris ini';
            removeBtn.className = 'flex-shrink-0 w-9 h-9 flex items-center justify-center bg-red-100 hover:bg-red-200 text-red-600 rounded-xl text-lg font-bold transition-colors';
            removeBtn.textContent = '×';
            removeBtn.onclick = function() { div.remove(); };

            div.appendChild(input);
            div.appendChild(addBtn);
            div.appendChild(removeBtn);
            container.appendChild(div);
        }

        // Called from inline HTML onclick — kept for backward compat with static first row
        function addFileInput() { appendFileRow(); }
            const container = document.getElementById('existing-files-container');
            const list = document.getElementById('existing-files-list');
            list.innerHTML = '';
            if (!paths || paths.length === 0) {
                container.classList.add('hidden');
                return;
            }
            container.classList.remove('hidden');
            paths.forEach(function(p, i) {
                const parts = p.split('/');
                const rawName = parts[parts.length - 1];
                // Strip leading uniqid prefix (e.g. "67a1b2c3d_filename.pdf")
                const displayName = rawName.replace(/^[a-f0-9]+_/, '') || rawName;
                const li = document.createElement('li');
                li.id = 'existing-file-' + i;
                li.className = 'flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm';
                li.innerHTML = '<span class="text-gray-700 truncate max-w-xs">📄 ' + displayName + '</span>'
                    + '<button type="button" onclick="removeExistingFile(\'' + p.replace(/'/g, "\\'") + '\', ' + i + ')" '
                    + 'class="ml-2 text-red-500 hover:text-red-700 font-medium flex-shrink-0">✕ Hapus</button>';
                list.appendChild(li);
            });
        }

        // Mark an existing file for removal
        function removeExistingFile(path, index) {
            const input = document.getElementById('remove_files');
            let toRemove = JSON.parse(input.value || '[]');
            if (!toRemove.includes(path)) toRemove.push(path);
            input.value = JSON.stringify(toRemove);
            // Grey out the item visually
            const li = document.getElementById('existing-file-' + index);
            if (li) {
                li.classList.add('opacity-40', 'line-through');
                li.querySelector('button').disabled = true;
            }
        }

        function openEditModal(doc) {
            resetForm();
            document.getElementById('edit_id').value = doc.id;
            document.getElementById('judul_regulasi').value = doc.judul_regulasi || '';
            document.getElementById('nomor_regulasi').value = doc.nomor_regulasi || '';
            document.getElementById('kategori_regulasi').value = doc.kategori_regulasi || 'SPO';
            document.getElementById('tanggal_terbit').value = doc.tanggal_terbit || '';
            document.getElementById('penanggung_jawab').value = doc.penanggung_jawab || '';

            // Decode existing file paths (JSON array or legacy single string)
            let existingPaths = [];
            if (doc.file_path) {
                try {
                    const parsed = JSON.parse(doc.file_path);
                    existingPaths = Array.isArray(parsed) ? parsed : [doc.file_path];
                } catch(e) {
                    existingPaths = [doc.file_path];
                }
            }
            renderExistingFiles(existingPaths);
            // Start with one empty file input row for adding new files
            resetFileInputs();

            document.getElementById('submitBtn').name = 'edit_regulasi';
            document.getElementById('submitBtn').textContent = 'Simpan Perubahan';
            document.querySelector('#modal h2').textContent = 'Edit Dokumen Regulasi';

            const modal = document.getElementById('modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

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
