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

// Handle form submission for adding new Regulasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_regulasi'])) {
    $judul_regulasi = $_POST['judul_regulasi'] ?? null;
    $nomor_regulasi = $_POST['nomor_regulasi'] ?? null;
    $kategori_regulasi = $_POST['kategori_regulasi'] ?? null;
    $tanggal_terbit = $_POST['tanggal_terbit'] ?? null;
    
    $file_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/regulasi/';
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
            INSERT INTO dokumen_regulasi (judul_regulasi, nomor_regulasi, kategori_regulasi, tanggal_terbit, file_path)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$judul_regulasi, $nomor_regulasi, $kategori_regulasi, $tanggal_terbit, $file_path]);
        
        $_SESSION['pks_success'] = "Dokumen Regulasi berhasil ditambahkan!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menyimpan data: " . $e->getMessage();
    }
    
    header("Location: regulasi.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Get file path before deleting
        $stmt = $pdo->prepare("SELECT file_path FROM dokumen_regulasi WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM dokumen_regulasi WHERE id = ?");
        $stmt->execute([$id]);

        // Delete file if exists
        if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        $_SESSION['pks_success'] = "Dokumen Regulasi berhasil dihapus!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: regulasi.php");
    exit;
}

// Get Regulasi documents
try {
    $stmt = $pdo->query("SELECT * FROM dokumen_regulasi ORDER BY created_at DESC");
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
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Regulasi</h1>
                    <p class="text-gray-600 mt-2">Arsip dan manajemen seluruh peraturan dan undang-undang terkait kesehatan</p>
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

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Regulasi</p>
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
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Judul Regulasi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nomor Regulasi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Kategori Regulasi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal Terbit</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
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
                                            <td class="px-6 py-4">
                                                <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                <?php if (!empty($file_path)): ?>
                                                    <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
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
                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                            Lihat
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="regulasi.php?delete=<?php echo $doc['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
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

    <!-- Modal for Regulasi -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Tambah Dokumen Regulasi</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Judul Regulasi</label>
                    <input type="text" name="judul_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Regulasi</label>
                    <input type="text" name="nomor_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Regulasi</label>
                    <select name="kategori_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="SPO">SPO</option>
                        <option value="Peraturan Direktur">Peraturan Direktur</option>
                        <option value="Keputusan Direktur">Keputusan Direktur</option>
                        <option value="Kebijakan Mutu">Kebijakan Mutu</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Terbit</label>
                    <input type="date" name="tanggal_terbit" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas (PDF)</label>
                    <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_regulasi" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
