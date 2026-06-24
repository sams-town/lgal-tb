<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Define STARKES Bab array
$STARKES_BAB = [
    '1' => '1. TKRS (Tata Kelola Rumah Sakit)',
    '2' => '2. PKPO (Pelayanan Kesehatan Pemberdayaan Orang)',
    '3' => '3. KKS (Ketersediaan Kesehatan Sarana)',
    '4' => '4. PPM (Pelayanan Profesional Medis)',
    '5' => '5. KPP (Kualitas Pelayanan Kesehatan)',
    '6' => '6. APK (Akses Pelayanan Kesehatan)',
    '7' => '7. HAM (Hak Azasi Manusia)',
    '8' => '8. KES (Keselamatan Pasien & Karyawan)',
    '9' => '9. MUT (Manajemen Mutu)',
    '10' => '10. INF (Infection Control & Pencegahan Penyakit)',
    '11' => '11. MDP (Manajemen Dokumen & Peraturan)',
    '12' => '12. PKM (Pengelolaan Keuangan dan Manajemen)',
    '13' => '13. SDI (Sumber Daya Manusia)',
    '14' => '14. KEL (Kesejahteraan Lingkungan)',
    '15' => '15. Prognas (Program Nasional)'
];

// Handle form submission for adding documents
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_document'])) {
    $bab = trim($_POST['bab']);
    $nama_dokumen = trim($_POST['nama_dokumen']);
    $kode_ep = trim($_POST['kode_ep']);
    $tanggal_review = date('Y-m-d'); // Set to today if not provided
    $target_capaian = (int)$_POST['target_capaian'];

    // Handle file upload
    $file_path = null;
    if (isset($_FILES['berkas']) && $_FILES['berkas']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/akreditasi/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['berkas']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (in_array($ext, $allowed_exts)) {
            $unique_name = uniqid('akreditasi_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['berkas']['tmp_name'], $upload_dir . $unique_name)) {
                $file_path = $unique_name;
            }
        }
    }

    try {
        // Create table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `dokumen_akreditasi` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `bab` varchar(2) NOT NULL,
                `nama_dokumen` varchar(255) NOT NULL,
                `kode_ep` varchar(50) NOT NULL,
                `tanggal_review` date NOT NULL,
                `target_capaian` int(3) NOT NULL,
                `status_pemenuhan` enum('Belum Lengkap','Dalam Review','Sudah Terpenuhi') NOT NULL DEFAULT 'Belum Lengkap',
                `file_path` varchar(255) DEFAULT NULL,
                `file_status` enum('Tidak Ada','Ada') NOT NULL DEFAULT 'Tidak Ada',
                `created_by` int(11) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Insert document
        $stmt = $pdo->prepare("
            INSERT INTO dokumen_akreditasi 
            (bab, nama_dokumen, kode_ep, tanggal_review, target_capaian, status_pemenuhan, file_path, file_status)
            VALUES (?, ?, ?, ?, ?, 'Belum Lengkap', ?, ?)
        ");
        $file_status = $file_path ? 'Ada' : 'Tidak Ada';
        $stmt->execute([$bab, $nama_dokumen, $kode_ep, $tanggal_review, $target_capaian, $file_path, $file_status]);
        $message = 'Dokumen berhasil ditambahkan!';
    } catch (PDOException $e) {
        $message = 'Gagal menambah dokumen: ' . $e->getMessage();
    }
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    try {
        // Get file path to delete if exists
        $stmt = $pdo->prepare("SELECT file_path FROM dokumen_akreditasi WHERE id = ?");
        $stmt->execute([$delete_id]);
        $doc = $stmt->fetch();
        if ($doc && $doc['file_path']) {
            $file_path = 'uploads/akreditasi/' . $doc['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // Delete from DB
        $stmt = $pdo->prepare("DELETE FROM dokumen_akreditasi WHERE id = ?");
        $stmt->execute([$delete_id]);
        $message = 'Dokumen berhasil dihapus!';
    } catch (PDOException $e) {
        $message = 'Gagal menghapus dokumen: ' . $e->getMessage();
    }
}

// Get filter and documents
$filter_bab = isset($_GET['bab']) ? $_GET['bab'] : '';
try {
    // Check if table exists, if not, create it
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `dokumen_akreditasi` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `bab` varchar(2) NOT NULL,
            `nama_dokumen` varchar(255) NOT NULL,
            `kode_ep` varchar(50) NOT NULL,
            `tanggal_review` date NOT NULL,
            `target_capaian` int(3) NOT NULL,
            `status_pemenuhan` enum('Belum Lengkap','Dalam Review','Sudah Terpenuhi') NOT NULL DEFAULT 'Belum Lengkap',
            `file_path` varchar(255) DEFAULT NULL,
            `file_status` enum('Tidak Ada','Ada') NOT NULL DEFAULT 'Tidak Ada',
            `created_by` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $sql = "SELECT * FROM dokumen_akreditasi";
    $params = [];
    if ($filter_bab) {
        $sql .= " WHERE bab = ?";
        $params[] = $filter_bab;
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documents = $stmt->fetchAll();

    // Get stats
    $stats = [
        'total' => 0,
        'terpenuhi' => 0,
        'review' => 0,
        'belum' => 0
    ];
    foreach ($documents as $doc) {
        $stats['total']++;
        if ($doc['status_pemenuhan'] === 'Sudah Terpenuhi') {
            $stats['terpenuhi']++;
        } elseif ($doc['status_pemenuhan'] === 'Dalam Review') {
            $stats['review']++;
        } else {
            $stats['belum']++;
        }
    }
} catch (PDOException $e) {
    $documents = [];
    $stats = ['total' => 0, 'terpenuhi' => 0, 'review' => 0, 'belum' => 0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akreditasi & Mutu - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal-overlay {
            background-color: rgba(0,0,0,0.5);
        }
    </style>
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
                    <a href="pks.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        Perjanjian Kerjasama (PKS)
                    </a>
                    <a href="regulasi.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        › Regulasi
                    </a>
                    <a href="perizinan.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
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
            <a href="akreditasi.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors bg-emerald-700">
                <span class="text-xl">🏅</span>
                <span>Akreditasi & Mutu</span>
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
                        placeholder="Cari semua modul..." 
                        class="w-full pl-10 pr-4 py-2 bg-gray-100 border-0 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
                    >
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
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
                <!-- Header and Action -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Akreditasi & Mutu</h1>
                        <p class="text-gray-600 mt-2">Dokumen Bukti EP & Capaian Standar Akreditasi STARKES</p>
                    </div>
                    <button 
                        id="openModalBtn"
                        class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-medium transition-colors shadow-sm hover:shadow-md"
                    >
                        <span class="text-xl">+</span>
                        Tambah Dokumen
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Elemen Penilaian</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $stats['total']; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">📋</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Sudah Terpenuhi</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $stats['terpenuhi']; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">✅</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Dalam Review</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $stats['review']; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center text-3xl">⏳</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Belum Lengkap</p>
                                <h3 class="text-3xl font-bold text-amber-600"><?php echo $stats['belum']; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">⚠️</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Dropdown -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <form method="GET" class="flex items-center gap-4">
                        <label class="font-semibold text-gray-700">Filter Bab STARKES:</label>
                        <select name="bab" class="px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all" onchange="this.form.submit()">
                            <option value="">-- Semua Bab --</option>
                            <?php foreach ($STARKES_BAB as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filter_bab == $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($value); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filter_bab): ?>
                            <a href="akreditasi.php" class="text-sm text-emerald-700 hover:text-emerald-800 font-medium">Reset Filter</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nama Dokumen / Bukti EP</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Kode EP</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal Review</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Target Capaian</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status Pemenuhan</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                        Belum ada dokumen yang ditambahkan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documents as $index => $doc): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-gray-600"><?php echo $index + 1; ?></td>
                                        <td class="px-6 py-4">
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['nama_dokumen']); ?></p>
                                            <p class="text-sm text-gray-500">Bab: <?php echo htmlspecialchars($STARKES_BAB[$doc['bab']] ?? $doc['bab']); ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                                <?php echo htmlspecialchars($doc['kode_ep']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-700"><?php echo date('d/m/Y', strtotime($doc['tanggal_review'])); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="font-semibold text-emerald-700"><?php echo $doc['target_capaian']; ?>%</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                                $statusClass = '';
                                                if ($doc['status_pemenuhan'] === 'Sudah Terpenuhi') {
                                                    $statusClass = 'bg-emerald-100 text-emerald-800';
                                                } elseif ($doc['status_pemenuhan'] === 'Dalam Review') {
                                                    $statusClass = 'bg-blue-100 text-blue-800';
                                                } else {
                                                    $statusClass = 'bg-amber-100 text-amber-800';
                                                }
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($doc['status_pemenuhan']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($doc['file_path']): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                    Ada
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                                    Tidak Ada
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <?php if ($doc['file_path']): ?>
                                                    <a 
                                                        href="uploads/akreditasi/<?php echo $doc['file_path']; ?>" 
                                                        target="_blank"
                                                        class="text-blue-700 hover:text-blue-800 text-sm font-medium"
                                                    >
                                                        Lihat
                                                    </a>
                                                <?php endif; ?>
                                                <form method="POST" onsubmit="return confirm('Yakin ingin menghapus dokumen ini?')">
                                                    <input type="hidden" name="delete_id" value="<?php echo $doc['id']; ?>">
                                                    <button 
                                                        type="submit"
                                                        class="text-red-700 hover:text-red-800 text-sm font-medium"
                                                    >
                                                        Hapus
                                                    </button>
                                                </form>
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

    <!-- Add Document Modal -->
    <div id="addModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0" onclick="closeModal()"></div>
        <div class="relative z-10 flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden">
                <div class="p-8 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="text-2xl font-bold text-gray-900">Tambah Dokumen / Bukti EP</h2>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">×</button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="p-8 space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Pilih Akreditasi</label>
                        <select name="bab" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                            <?php foreach ($STARKES_BAB as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Nama Dokumen</label>
                        <input type="text" name="nama_dokumen" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Nomor Elemen Penilaian</label>
                        <input type="text" name="kode_ep" required placeholder="Contoh: EP-101" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Target Capaian (%)</label>
                        <input type="number" name="target_capaian" min="0" max="100" required value="100" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Upload Berkas (PDF/DOC/Image)</label>
                        <input type="file" name="berkas" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-100">
                        <button type="button" onclick="closeModal()" class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-medium transition-colors">
                            Batal
                        </button>
                        <button type="submit" name="add_document" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-medium transition-colors">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }
        function closeModal() {
            document.getElementById('addModal').classList.add('hidden');
        }
        document.getElementById('openModalBtn').addEventListener('click', openModal);
    </script>
</body>
</html>
