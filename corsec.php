<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$success = null;
$error = null;

// Initialize dokumen_corsec table and insert sample data if empty
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

// Handle form submission for adding new document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_dokumen'])) {
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

// Handle delete
if (isset($_GET['delete'])) {
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

function formatDate($date) {
    if (!$date) return '-';
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $months[$d->format('n')] . ' ' . $d->format('Y');
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
    <aside class="w-64 bg-gradient-to-b from-emerald-800 to-emerald-900 text-white shadow-xl">
        <div class="p-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center text-emerald-800 font-bold text-xl">
                    🏥
                </div>
                <div>
                    <h1 class="text-lg font-bold">RS. Taman Harapan Baru</h1>
                    <p class="text-xs text-emerald-200">Legal & Corporate Secretary</p>
                </div>
            </div>
        </div>
        
        <nav class="p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">📊</span>
                <span>Dashboard</span>
            </a>
            <div class="space-y-1">
                <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📑</span>
                        <span>Legal</span>
                    </div>
                    <span>▼</span>
                </button>
                <div class="ml-4 space-y-1">
                    <a href="legal.php?page=pks" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        Perjanjian Kerjasama (PKS)
                    </a>
                    <a href="legal.php?page=regulasi" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        › Regulasi
                    </a>
                    <a href="legal.php?page=perizinan" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        › Perizinan
                    </a>
                </div>
            </div>
            <div class="space-y-1">
                <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">✉️</span>
                        <span>Sekretariat</span>
                    </div>
                    <span>▼</span>
                </button>
                <div class="ml-4 space-y-1">
                    <a href="surat-masuk.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        Surat Masuk
                    </a>
                    <a href="surat-keluar.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        Surat Keluar
                    </a>
                </div>
            </div>
            <a href="akreditasi.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">🏅</span>
                <span>Akreditasi & Mutu</span>
            </a>
            <a href="approval.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">✍️</span>
                <span>Persetujuan & E-Sign</span>
            </a>
            <a href="sop.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">📚</span>
                <span>SOP & SDM</span>
            </a>
            <a href="tenaga_medis.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">👨‍⚕️</span>
                <span>Komite</span>
            </a>
            <a href="corsec.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors bg-emerald-700">
                <span class="text-xl">🏛️</span>
                <span>Corporate Secretary</span>
            </a>
            <a href="audit_trail.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">🔍</span>
                <span>Audit Trail</span>
            </a>
            <a href="pengaturan.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">⚙️</span>
                <span>Pengaturan</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow-sm px-8 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4 flex-1 max-w-md">
                <div class="relative flex-1">
                    <input 
                        type="text" 
                        placeholder="Cari dokumen eksekutif..." 
                        class="w-full pl-10 pr-4 py-2 bg-gray-100 border-0 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
                    >
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <button onclick="openModal()" class="flex items-center gap-2 bg-emerald-600 text-white px-6 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                    <span>+</span>
                    <span>Tambah Dokumen Eksekutif</span>
                </button>
                <button class="text-gray-500 hover:text-emerald-600 transition-colors text-xl">🔔</button>
                <div class="flex items-center gap-3 bg-emerald-50 px-4 py-2 rounded-xl">
                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-full flex items-center justify-center text-white font-bold text-lg">
                        <?php 
                        $userName = $user['name'] ?? $user['nama'] ?? 'Guest';
                        echo htmlspecialchars(substr($userName, 0, 1)); 
                        ?>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-semibold text-gray-800">
                            <?php 
                            $userName = $user['name'] ?? $user['nama'] ?? 'Guest';
                            echo htmlspecialchars($userName); 
                            ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?php 
                            $userRole = $user['role'] ?? $user['nama_role'] ?? 'Guest';
                            echo htmlspecialchars($userRole); 
                            ?>
                        </p>
                    </div>
                    <a href="logout.php" class="text-sm text-red-600 hover:text-red-700 font-medium">Logout</a>
                </div>
            </div>
        </header>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Corporate Secretary</h1>
                        <p class="text-gray-600 mt-2">Manajemen Dokumen & Agenda Eksekutif Direksi</p>
                    </div>
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
                                                    <button class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                                        Detail
                                                    </button>
                                                    <a href="corsec.php?delete=<?php echo $doc['id']; ?>&category=<?php echo $category; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                                        Hapus
                                                    </a>
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

    <!-- Modal Tambah Dokumen -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Tambah Dokumen Eksekutif</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Judul Dokumen / Rapat</label>
                    <input type="text" name="judul" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Dokumen</label>
                    <input type="text" name="nomor_dokumen" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Corsec</label>
                    <select name="kategori" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="GCG">GCG</option>
                        <option value="Board Meeting">Board Meeting</option>
                        <option value="KPI Direksi">KPI Direksi</option>
                        <option value="Risk Management">Risk Management</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Terbit / Pelaksanaan</label>
                    <input type="date" name="tanggal_terbit" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas Softcopy (PDF)</label>
                    <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_dokumen" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
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
        function openImportModal() {
            openModal('importModal');
        }

        function closeImportModal() {
            closeModal('importModal');
        }
    </script>
</body>
</html>
