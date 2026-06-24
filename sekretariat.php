<?php
/*
CREATE TABLE IF NOT EXISTS manajemen_surat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_surat VARCHAR(255) NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    asal_pengirim VARCHAR(255) NOT NULL,
    perihal TEXT NOT NULL,
    tanggal_surat DATE NOT NULL,
    tanggal_diterima DATE NULL,
    status_tindak_lanjut VARCHAR(50) NOT NULL DEFAULT 'Pending',
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kategori (kategori),
    INDEX idx_status_tindak_lanjut (status_tindak_lanjut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Initialize manajemen_surat table and insert sample data if empty
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS manajemen_surat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nomor_surat VARCHAR(255) NOT NULL,
            kategori VARCHAR(50) NOT NULL,
            asal_pengirim VARCHAR(255) NOT NULL,
            perihal TEXT NOT NULL,
            tanggal_surat DATE NOT NULL,
            tanggal_diterima DATE NULL,
            status_tindak_lanjut VARCHAR(50) NOT NULL DEFAULT 'Pending',
            file_path VARCHAR(255) NULL,
            kepada TEXT NULL,
            cc TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kategori (kategori),
            INDEX idx_status_tindak_lanjut (status_tindak_lanjut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Add columns if they don't exist (for existing tables)
    $columns = $pdo->query("SHOW COLUMNS FROM manajemen_surat LIKE 'kepada'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE manajemen_surat ADD COLUMN kepada TEXT NULL AFTER file_path");
    }
    $columns = $pdo->query("SHOW COLUMNS FROM manajemen_surat LIKE 'cc'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE manajemen_surat ADD COLUMN cc TEXT NULL AFTER kepada");
    }

    // Insert sample data if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM manajemen_surat");
    if (false && $stmt->fetchColumn() == 0) {
        $sampleData = [
            ['nomor_surat' => 'SM-001/2024', 'kategori' => 'Surat Masuk', 'asal_pengirim' => 'Dinas Kesehatan Propinsi', 'perihal' => 'Undangan Rapat Evaluasi Kinerja', 'tanggal_surat' => '2024-06-01', 'tanggal_diterima' => '2024-06-02', 'status_tindak_lanjut' => 'Pending', 'file_path' => 'uploads/sekretariat/surat-masuk-001.pdf'],
            ['nomor_surat' => 'SK-002/2024', 'kategori' => 'Surat Keluar', 'asal_pengirim' => 'RS Taman Harapan Baru', 'perihal' => 'Surat Balasan Permintaan Kerjasama', 'tanggal_surat' => '2024-06-05', 'tanggal_diterima' => null, 'status_tindak_lanjut' => 'Selesai', 'file_path' => 'uploads/sekretariat/surat-keluar-002.pdf'],
            ['nomor_surat' => 'NT-003/2024', 'kategori' => 'Notulen', 'asal_pengirim' => 'Sekretaris Direksi', 'perihal' => 'Notulen Rapat Direksi Bulanan', 'tanggal_surat' => '2024-06-10', 'tanggal_diterima' => null, 'status_tindak_lanjut' => 'Selesai', 'file_path' => 'uploads/sekretariat/notulen-003.pdf'],
            ['nomor_surat' => 'DS-004/2024', 'kategori' => 'Disposisi', 'asal_pengirim' => 'Direktur', 'perihal' => 'Disposisi Surat Permintaan Bantuan', 'tanggal_surat' => '2024-06-12', 'tanggal_diterima' => null, 'status_tindak_lanjut' => 'Dalam Proses', 'file_path' => null],
            ['nomor_surat' => 'MM-005/2024', 'kategori' => 'Memo', 'asal_pengirim' => 'Bagian Keuangan', 'perihal' => 'Memo Pengingat Pembayaran Tagihan', 'tanggal_surat' => '2024-06-15', 'tanggal_diterima' => null, 'status_tindak_lanjut' => 'Pending', 'file_path' => null]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO manajemen_surat (nomor_surat, kategori, asal_pengirim, perihal, tanggal_surat, tanggal_diterima, status_tindak_lanjut, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($sampleData as $data) {
            $stmt->execute([
                $data['nomor_surat'],
                $data['kategori'],
                $data['asal_pengirim'],
                $data['perihal'],
                $data['tanggal_surat'],
                $data['tanggal_diterima'],
                $data['status_tindak_lanjut'],
                $data['file_path']
            ]);
        }
    }
} catch (PDOException $e) {
    // Continue if sample data fails to insert
}

// Handle form submission for adding new document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_surat'])) {
    $nomor_surat = $_POST['nomor_surat'];
    $kategori = $_POST['kategori'];
    $asal_pengirim = $_POST['asal_pengirim'];
    $perihal = $_POST['perihal'];
    $tanggal_surat = $_POST['tanggal_surat'];
    $tanggal_diterima = !empty($_POST['tanggal_diterima']) ? $_POST['tanggal_diterima'] : null;
    $status_tindak_lanjut = 'Pending';
    $file_path = null;
    $kepada = isset($_POST['kepada']) ? json_encode($_POST['kepada']) : null;
    $cc = isset($_POST['cc']) ? json_encode($_POST['cc']) : null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/sekretariat/';
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
            INSERT INTO manajemen_surat (nomor_surat, kategori, asal_pengirim, perihal, tanggal_surat, tanggal_diterima, status_tindak_lanjut, file_path, kepada, cc)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nomor_surat, $kategori, $asal_pengirim, $perihal, $tanggal_surat, $tanggal_diterima, $status_tindak_lanjut, $file_path, $kepada, $cc]);
        
        // Send notifications to semua penerima di "kepada" and "cc"
        $penerima = [];
        if (isset($_POST['kepada']) && is_array($_POST['kepada'])) {
            $penerima = array_merge($penerima, $_POST['kepada']);
        }
        if (isset($_POST['cc']) && is_array($_POST['cc'])) {
            $penerima = array_merge($penerima, $_POST['cc']);
        }
        
        // Remove duplicates
        $penerima = array_unique($penerima);
        
        foreach ($penerima as $role_or_user) {
            // Jika ini adalah role (nama role), kirim ke role tersebut
            createNotification(
                "$kategori Baru",
                "Ada $kategori baru dari $asal_pengirim dengan perihal: $perihal.",
                $role_or_user
            );
        }
        
    } catch (PDOException $e) {
        $error = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $currentType = $_GET['type'] ?? 'semua';
    try {
        // Get file path before deleting
        $stmt = $pdo->prepare("SELECT file_path FROM manajemen_surat WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM manajemen_surat WHERE id = ?");
        $stmt->execute([$id]);

        // Delete file if exists
        if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
    } catch (PDOException $e) {
        $error = "Gagal menghapus data: " . $e->getMessage();
    }
}

// Get type parameter
$type = isset($_GET['type']) ? $_GET['type'] : 'semua';

// Get documents based on type
try {
    if ($type === 'semua') {
        $stmt = $pdo->query("SELECT * FROM manajemen_surat ORDER BY created_at DESC");
        $documents = $stmt->fetchAll();
    } else {
        $typeMap = [
            'surat-masuk' => 'Surat Masuk',
            'surat-keluar' => 'Surat Keluar',
            'disposisi' => 'Disposisi',
            'notulen' => 'Notulen',
            'memo' => 'Memo'
        ];
        $kategori = $typeMap[$type] ?? 'Surat Masuk';
        $stmt = $pdo->prepare("SELECT * FROM manajemen_surat WHERE kategori = ? ORDER BY created_at DESC");
        $stmt->execute([$kategori]);
        $documents = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $documents = [];
}

// Calculate stats
try {
    // Total surat
    $stmt = $pdo->query("SELECT COUNT(*) FROM manajemen_surat");
    $totalSurat = $stmt->fetchColumn();

    // Surat masuk
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = ?");
    $stmt->execute(['Surat Masuk']);
    $suratMasuk = $stmt->fetchColumn();

    // Surat keluar
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = ?");
    $stmt->execute(['Surat Keluar']);
    $suratKeluar = $stmt->fetchColumn();

    // Perlu tindak lanjut
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE status_tindak_lanjut IN ('Pending', 'Dalam Proses')");
    $stmt->execute();
    $perluTindakLanjut = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalSurat = $suratMasuk = $suratKeluar = $perluTindakLanjut = 0;
}

// Helper functions
function getKategoriBadgeClass($kategori) {
    switch ($kategori) {
        case 'Surat Masuk':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'Surat Keluar':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'Disposisi':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'Notulen':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'Memo':
            return 'bg-gray-100 text-gray-800 border-gray-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Selesai':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'Dalam Proses':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'Pending':
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
    <title>Sekretariat - RS Taman Harapan Baru</title>
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
                <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 bg-emerald-700">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">✉️</span>
                        <span>Sekretariat</span>
                    </div>
                    <span>▼</span>
                </button>
                <div class="ml-4 space-y-1">
                    <a href="sekretariat.php?type=surat-masuk" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $type === 'surat-masuk' ? 'bg-emerald-600' : ''; ?>">
                        Surat Masuk
                    </a>
                    <a href="sekretariat.php?type=surat-keluar" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $type === 'surat-keluar' ? 'bg-emerald-600' : ''; ?>">
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
            <a href="corsec.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">🏛️</span>
                <span>Corporate Secretary</span>
            </a>
            <a href="audit_trail.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">🔍</span>
                <span>Audit Trail</span>
            </a>
            <a href="setting.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
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
                        placeholder="Cari surat atau notulen..." 
                        class="w-full pl-10 pr-4 py-2 bg-gray-100 border-0 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
                    >
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <button onclick="openModal()" class="flex items-center gap-2 bg-emerald-600 text-white px-6 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                    <span>+</span>
                    <span>Tambah Surat/Notulen</span>
                </button>
                <button class="text-gray-500 hover:text-emerald-600 transition-colors text-xl">🔔</button>
                <div class="flex items-center gap-3 bg-emerald-50 px-4 py-2 rounded-xl">
                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-full flex items-center justify-center text-white font-bold text-lg">
                        <?php echo htmlspecialchars(substr($user['name'], 0, 1)); ?>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['role']); ?></p>
                    </div>
                    <a href="logout.php" class="text-sm text-red-600 hover:text-red-700 font-medium">Logout</a>
                </div>
            </div>
        </header>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Sekretariat</h1>
                    <p class="text-gray-600 mt-2">Manajemen surat masuk, surat keluar, notulen, dan memo rumah sakit</p>
                </div>

                <!-- Type Tabs -->
                <div class="flex flex-wrap gap-3">
                    <?php 
                    $types = [
                        'semua' => 'Semua',
                        'surat-masuk' => 'Surat Masuk',
                        'surat-keluar' => 'Surat Keluar',
                        'disposisi' => 'Disposisi',
                        'notulen' => 'Notulen',
                        'memo' => 'Memo'
                    ];
                    foreach ($types as $typeVal => $typeLabel): 
                    ?>
                        <a href="sekretariat.php?type=<?php echo $typeVal; ?>" class="px-5 py-2 rounded-xl font-medium transition-all <?php echo $type === $typeVal ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:border-emerald-300 hover:text-emerald-600'; ?>">
                            <?php echo $typeLabel; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Surat / Berkas</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalSurat; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Surat Masuk</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $suratMasuk; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">📥</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Surat Keluar</p>
                                <h3 class="text-3xl font-bold text-purple-600"><?php echo $suratKeluar; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">📤</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Perlu Tindak Lanjut</p>
                                <h3 class="text-3xl font-bold text-amber-600"><?php echo $perluTindakLanjut; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">⚠️</div>
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
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nomor & Tanggal Surat</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Kategori</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Asal/Tujuan</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Perihal</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Detail</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                                Belum ada surat atau notulen yang tersedia
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $index => $doc): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['nomor_surat']); ?></p>
                                                    <p class="text-sm text-gray-500"><?php echo formatDate($doc['tanggal_surat']); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo getKategoriBadgeClass($doc['kategori']); ?>">
                                                        <?php echo htmlspecialchars($doc['kategori']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo htmlspecialchars($doc['asal_pengirim']); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700 max-w-xs">
                                                    <p class="text-sm truncate"><?php echo htmlspecialchars($doc['perihal']); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <?php if ($doc['kategori'] === 'Memo'): ?>
                                                        <?php 
                                                        $kepada = !empty($doc['kepada']) ? json_decode($doc['kepada'], true) : [];
                                                        $cc = !empty($doc['cc']) ? json_decode($doc['cc'], true) : [];
                                                        ?>
                                                        <?php if (!empty($kepada)): ?>
                                                            <p class="text-xs text-gray-600"><strong>KEPADA:</strong> <?php echo htmlspecialchars(implode(', ', $kepada)); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($cc)): ?>
                                                            <p class="text-xs text-gray-600 mt-1"><strong>CC:</strong> <?php echo htmlspecialchars(implode(', ', $cc)); ?></p>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo getStatusBadgeClass($doc['status_tindak_lanjut']); ?>">
                                                        <?php echo htmlspecialchars($doc['status_tindak_lanjut']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php if ($doc['file_path']): ?>
                                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                            📥
                                                            <span>Download</span>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <?php if ($doc['file_path']): ?>
                                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                                Lihat Dokumen
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="sekretariat.php?delete=<?php echo $doc['id']; ?>&type=<?php echo $type; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus surat ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
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

    <!-- Modal -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Tambah Surat/Notulen</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Surat</label>
                    <input type="text" name="nomor_surat" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                    <select name="kategori" id="kategoriSelect" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="Surat Masuk">Surat Masuk</option>
                        <option value="Surat Keluar">Surat Keluar</option>
                        <option value="Disposisi">Disposisi</option>
                        <option value="Notulen">Notulen</option>
                        <option value="Memo">Memo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Asal / Pengirim</label>
                    <input type="text" name="asal_pengirim" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Perihal / Ringkasan</label>
                    <textarea name="perihal" required rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                </div>
                
                <!-- Memo-specific fields -->
                <div id="memoFields" class="hidden space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">KEPADA</label>
                        <div class="space-y-2 border border-gray-300 rounded-xl p-4 max-h-48 overflow-y-auto">
                            <?php
                            $recipients = [
                                'Direktur Utama', 'Direktur Keuangan', 'Direktur Umum & SDM',
                                'Kepala Komite Mutu', 'Kepala Bagian Medis', 'Kepala Bagian Keperawatan',
                                'Kepala Bagian Keuangan', 'Kepala Bagian Umum', 'Kepala Bagian SDM'
                            ];
                            foreach ($recipients as $recipient):
                            ?>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="kepada[]" value="<?php echo htmlspecialchars($recipient); ?>" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars($recipient); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CC (Tembusan)</label>
                        <div class="space-y-2 border border-gray-300 rounded-xl p-4 max-h-48 overflow-y-auto">
                            <?php foreach ($recipients as $recipient): ?>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="cc[]" value="<?php echo htmlspecialchars($recipient); ?>" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars($recipient); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Surat</label>
                        <input type="date" name="tanggal_surat" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Diterima</label>
                        <input type="date" name="tanggal_diterima" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload File Berkas (PDF)</label>
                    <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_surat" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Show/hide memo fields based on category
        document.addEventListener('DOMContentLoaded', function() {
            const kategoriSelect = document.getElementById('kategoriSelect');
            const memoFields = document.getElementById('memoFields');
            
            if (kategoriSelect && memoFields) {
                kategoriSelect.addEventListener('change', function() {
                    if (this.value === 'Memo') {
                        memoFields.classList.remove('hidden');
                    } else {
                        memoFields.classList.add('hidden');
                    }
                });
            }
        });
    </script>

    <script>
        function openModal() {
            document.getElementById('modal').classList.remove('hidden');
            document.getElementById('modal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
            document.getElementById('modal').classList.remove('flex');
        }
    </script>
</body>
</html>
