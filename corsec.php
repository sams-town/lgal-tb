<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!hasPermission('corsec_view')) {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION['user'];
$success = null;
$error = null;

// Initialize dokumen_corsec table and insert sample data if empty
if (isset($isLocal) && $isLocal) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS dokumen_corsec (
                id INT AUTO_INCREMENT PRIMARY KEY,
                judul VARCHAR(255) NOT NULL,
                nomor_dokumen VARCHAR(255) NULL,
                kategori VARCHAR(100) NOT NULL,
                tanggal_terbit DATE NOT NULL,
                file_path VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_kategori (kategori)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Insert sample data if table is empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_corsec");
        if (false && $stmt->fetchColumn() == 0) {
            $sampleData = [
                ['judul' => 'Rapat Direksi Q1 2024', 'nomor_dokumen' => 'CORSEC/001/2024', 'kategori' => 'Board Meeting', 'tanggal_terbit' => '2024-03-15', 'file_path' => 'uploads/corsec/rapat-q1-2024.pdf'],
                ['judul' => 'Pedoman GCG 2024', 'nomor_dokumen' => 'CORSEC/002/2024', 'kategori' => 'GCG', 'tanggal_terbit' => '2024-02-20', 'file_path' => 'uploads/corsec/pedoman-gcg-2024.pdf'],
                ['judul' => 'Laporan KPI Direksi Semester 1', 'nomor_dokumen' => 'CORSEC/003/2024', 'kategori' => 'KPI Direksi', 'tanggal_terbit' => '2024-06-30', 'file_path' => null],
                ['judul' => 'Daftar Risiko Operasional 2024', 'nomor_dokumen' => 'CORSEC/004/2024', 'kategori' => 'Risk Management', 'tanggal_terbit' => '2024-04-10', 'file_path' => null]
            ];

            $stmt = $pdo->prepare("
                INSERT INTO dokumen_corsec (judul, nomor_dokumen, kategori, tanggal_terbit, file_path)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($sampleData as $data) {
                $stmt->execute([
                    $data['judul'],
                    $data['nomor_dokumen'],
                    $data['kategori'],
                    $data['tanggal_terbit'],
                    $data['file_path']
                ]);
            }
        }
    } catch (PDOException $e) {
        // Continue if sample data fails to insert
    }
}

// Handle form submission for adding new document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_dokumen'])) {
    if (!canUserEditOrDelete('corsec')) {
        $error = "Anda tidak memiliki akses untuk menambah data!";
    } else {
        $judul = $_POST['judul'];
        $nomor_dokumen = $_POST['nomor_dokumen'];
        $kategori = $_POST['kategori'];
        $tanggal_terbit = $_POST['tanggal_terbit'];
        $file_path = null;

        // Handle file upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/corsec/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
            $targetFile = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
                $file_path = $targetFile;
            }
        }

        // Insert into database
        try {
            $stmt = $pdo->prepare("
                INSERT INTO dokumen_corsec (judul, nomor_dokumen, kategori, tanggal_terbit, file_path)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$judul, $nomor_dokumen, $kategori, $tanggal_terbit, $file_path]);
            
            // Send notification
            createNotification(
                "Dokumen eksekutif baru",
                "Dokumen eksekutif baru $nomor_dokumen telah berhasil ditambahkan oleh Corsec",
                null,
                $_SESSION['user']['id'] ?? null
            );
            
            $success = "Dokumen berhasil ditambahkan!";
        } catch (PDOException $e) {
            $error = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

// Handle form submission for editing document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_dokumen'])) {
    if (!canUserEditOrDelete('corsec')) {
        $error = "Anda tidak memiliki akses untuk mengedit data!";
    } else {
        $edit_id = (int)$_POST['edit_id'];
        $judul = $_POST['judul'];
        $nomor_dokumen = $_POST['nomor_dokumen'];
        $kategori = $_POST['kategori'];
        $tanggal_terbit = $_POST['tanggal_terbit'];

        // Get current file path
        $stmt = $pdo->prepare("SELECT file_path FROM dokumen_corsec WHERE id = ?");
        $stmt->execute([$edit_id]);
        $current_doc = $stmt->fetch();
        $file_path = $current_doc['file_path'] ?? null;

        // Handle file upload if new file is provided
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/corsec/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
            $targetFile = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
                // Delete old file if exists
                if ($file_path && file_exists($file_path)) {
                    unlink($file_path);
                }
                $file_path = $targetFile;
            }
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE dokumen_corsec 
                SET judul = ?, nomor_dokumen = ?, kategori = ?, tanggal_terbit = ?, file_path = ?
                WHERE id = ?
            ");
            $stmt->execute([$judul, $nomor_dokumen, $kategori, $tanggal_terbit, $file_path, $edit_id]);
            
            $success = "Dokumen berhasil diperbarui!";
        } catch (PDOException $e) {
            $error = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    if (!canUserEditOrDelete('corsec')) {
        $_SESSION['pks_error'] = "Anda tidak memiliki akses untuk menghapus data ini!";
        header("Location: corsec.php?category=" . urlencode($category ?? ''));
        exit;
    }
    $id = $_GET['delete'];
    try {
        // Get file path before deleting
        $stmt = $pdo->prepare("SELECT file_path FROM dokumen_corsec WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM dokumen_corsec WHERE id = ?");
        $stmt->execute([$id]);

        // Delete file if exists
        if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        $success = "Dokumen berhasil dihapus!";
    } catch (PDOException $e) {
        $error = "Gagal menghapus data: " . $e->getMessage();
    }
}

// Get category from query parameter
$category = isset($_GET['category']) ? $_GET['category'] : 'semua';

// Get documents based on category
try {
    if ($category === 'semua') {
        $stmt = $pdo->query("SELECT * FROM dokumen_corsec ORDER BY created_at DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM dokumen_corsec WHERE kategori = ? ORDER BY created_at DESC");
        $stmt->execute([$category]);
    }
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Calculate stats
try {
    $whereClause = $category === 'semua' ? '' : 'WHERE kategori = ?';
    $params = $category === 'semua' ? [] : [$category];

    // Total documents
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_corsec $whereClause");
    $stmt->execute($params);
    $totalDocs = $stmt->fetchColumn();

    // GCG
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_corsec $whereClause " . ($whereClause ? 'AND' : 'WHERE') . " kategori = 'GCG'");
    $stmt->execute($params);
    $gcg = $stmt->fetchColumn();

    // Board Meeting
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_corsec $whereClause " . ($whereClause ? 'AND' : 'WHERE') . " kategori = 'Board Meeting'");
    $stmt->execute($params);
    $boardMeeting = $stmt->fetchColumn();

    // KPI Direksi
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_corsec $whereClause " . ($whereClause ? 'AND' : 'WHERE') . " kategori = 'KPI Direksi'");
    $stmt->execute($params);
    $kpiDireksi = $stmt->fetchColumn();

    // Risk Management
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_corsec $whereClause " . ($whereClause ? 'AND' : 'WHERE') . " kategori = 'Risk Management'");
    $stmt->execute($params);
    $riskManagement = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalDocs = $gcg = $boardMeeting = $kpiDireksi = $riskManagement = 0;
}

// Helper functions
function getCategoryBadgeClass($category) {
    switch ($category) {
        case 'GCG':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'Board Meeting':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'KPI Direksi':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'Risk Management':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
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
    <title>Corporate Secretary - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <?php include 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Corporate Secretary</h1>
                        <p class="text-gray-600 mt-2">Manajemen Dokumen & Agenda Eksekutif Direksi</p>
                    </div>
                    <?php if (canUserEditOrDelete('corsec')): ?>
                        <button onclick="openModal()" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors shadow-sm">
                            ➕
                            <span>Tambah Dokumen</span>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($success)): ?>
                    <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="p-4 bg-red-100 text-red-800 rounded-xl">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
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

                <!-- Category Filters -->
                <div class="flex flex-wrap gap-3">
                    <a href="corsec.php?category=semua" class="px-5 py-2 rounded-xl font-medium transition-all <?php echo $category === 'semua' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:border-emerald-300 hover:text-emerald-600'; ?>">
                        Semua Dokumen
                    </a>
                    <a href="corsec.php?category=GCG" class="px-5 py-2 rounded-xl font-medium transition-all <?php echo $category === 'GCG' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:border-emerald-300 hover:text-emerald-600'; ?>">
                        GCG
                    </a>
                    <a href="corsec.php?category=Board Meeting" class="px-5 py-2 rounded-xl font-medium transition-all <?php echo $category === 'Board Meeting' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:border-emerald-300 hover:text-emerald-600'; ?>">
                        Board Meeting
                    </a>
                    <a href="corsec.php?category=KPI Direksi" class="px-5 py-2 rounded-xl font-medium transition-all <?php echo $category === 'KPI Direksi' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:border-emerald-300 hover:text-emerald-600'; ?>">
                        KPI Direksi
                    </a>
                    <a href="corsec.php?category=Risk Management" class="px-5 py-2 rounded-xl font-medium transition-all <?php echo $category === 'Risk Management' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:border-emerald-300 hover:text-emerald-600'; ?>">
                        Risk Management
                    </a>
                </div>

                <!-- Export/Import Buttons -->
                <div class="flex items-center gap-3">
                    <a href="export_handler.php?action=export_data&module=corsec" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        📊
                        <span>Export Excel</span>
                    </a>
                    <button onclick="openImportModal()" class="flex items-center gap-2 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        📤
                        <span>Import Excel</span>
                    </button>
                    <a href="export_handler.php?action=download_template&module=corsec" class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                        📄
                        <span>Download Template</span>
                    </a>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Dokumen Eksekutif</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $totalDocs; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">GCG</p>
                                <h3 class="text-3xl font-bold text-purple-600"><?php echo $gcg; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">📚</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Board Meeting</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $boardMeeting; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">📋</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">KPI & Risk</p>
                                <h3 class="text-3xl font-bold text-amber-600"><?php echo $kpiDireksi + $riskManagement; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl flex items-center justify-center text-3xl">📊</div>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Judul Dokumen / Rapat</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nomor Dokumen</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Kategori</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal Terbit</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada dokumen yang tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $index => $doc): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['judul']); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo htmlspecialchars($doc['nomor_dokumen'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo getCategoryBadgeClass($doc['kategori']); ?>">
                                                    <?php echo htmlspecialchars($doc['kategori']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo formatDate($doc['tanggal_terbit']); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($doc['file_path']): ?>
                                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                        📥
                                                        <span>Download PDF</span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-sm">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <?php if ($doc['file_path']): ?>
                                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                            Lihat
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (canUserEditOrDelete('corsec')): ?>
                                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($doc), ENT_QUOTES); ?>)" class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                                                            Edit
                                                        </button>
                                                        <a href="corsec.php?delete=<?php echo $doc['id']; ?>&category=<?php echo $category; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
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

    <!-- Modal Tambah/Edit Dokumen -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 id="modal-title" class="text-xl font-bold text-gray-900">Tambah Dokumen Eksekutif</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Judul Dokumen / Rapat</label>
                    <input type="text" name="judul" id="judul" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Dokumen</label>
                    <input type="text" name="nomor_dokumen" id="nomor_dokumen" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Corsec</label>
                    <select name="kategori" id="kategori" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="GCG">GCG</option>
                        <option value="Board Meeting">Board Meeting</option>
                        <option value="KPI Direksi">KPI Direksi</option>
                        <option value="Risk Management">Risk Management</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Terbit / Pelaksanaan</label>
                    <input type="date" name="tanggal_terbit" id="tanggal_terbit" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas Softcopy (PDF)</label>
                    <input type="file" name="file" id="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                    <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin mengubah berkas</p>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_dokumen" id="submitBtn" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
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
                <h2 class="text-xl font-bold text-gray-900">Import Excel</h2>
                <button onclick="closeImportModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" action="export_handler.php?action=import_data&module=corsec" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih File CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full px-4 py-2 border border-gray-300 rounded-xl">
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
                document.getElementById('submitBtn').name = 'tambah_dokumen';
                document.getElementById('submitBtn').textContent = 'Simpan';
                document.getElementById('modal-title').textContent = 'Tambah Dokumen Eksekutif';
            }
            if (originalOpenModal) {
                originalOpenModal(modalId);
            } else {
                // Fallback to our own implementation if original doesn't exist
                const element = document.getElementById(modalId || 'modal');
                if (element) {
                    element.classList.remove('hidden');
                    element.classList.add('flex');
                }
            }
        };

        function resetForm() {
            document.getElementById('edit_id').value = '';
            document.getElementById('judul').value = '';
            document.getElementById('nomor_dokumen').value = '';
            document.getElementById('kategori').value = 'GCG';
            document.getElementById('tanggal_terbit').value = '';
            document.getElementById('file').value = '';
        }

        function openEditModal(doc) {
            document.getElementById('edit_id').value = doc.id;
            document.getElementById('judul').value = doc.judul || '';
            document.getElementById('nomor_dokumen').value = doc.nomor_dokumen || '';
            document.getElementById('kategori').value = doc.kategori || 'GCG';
            document.getElementById('tanggal_terbit').value = doc.tanggal_terbit || '';
            document.getElementById('file').value = '';
            
            document.getElementById('submitBtn').name = 'edit_dokumen';
            document.getElementById('submitBtn').textContent = 'Simpan Perubahan';
            document.getElementById('modal-title').textContent = 'Edit Dokumen Eksekutif';
            
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
    </script>
</body>
</html>
