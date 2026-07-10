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

// Handle form submission for adding new Surat Keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_surat'])) {
    $nomor_surat = $_POST['nomor_surat'] ?? '';
    $kategori = 'Surat Keluar';
    $asal_pengirim = $_POST['asal_pengirim'] ?? ''; // or destination in case of Surat Keluar
    $perihal = $_POST['perihal'] ?? '';
    $tanggal_surat = $_POST['tanggal_surat'] ?? '';
    $tanggal_diterima = null; // Surat Keluar doesn't have tanggal_diterima
    $status_tindak_lanjut = $_POST['status_tindak_lanjut'] ?? 'Pending';
    $file_path = null;

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

    try {
        $stmt = $pdo->prepare("
            INSERT INTO manajemen_surat (nomor_surat, kategori, asal_pengirim, perihal, tanggal_surat, tanggal_diterima, status_tindak_lanjut, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nomor_surat, $kategori, $asal_pengirim, $perihal, $tanggal_surat, $tanggal_diterima, $status_tindak_lanjut, $file_path]);

        $_SESSION['success_msg'] = "Surat Keluar berhasil ditambahkan!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal menyimpan data: " . $e->getMessage();
    }

    header("Location: surat-keluar.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    if (!canUserEditOrDelete('sekretariat')) {
        $_SESSION['error_msg'] = "Anda tidak memiliki akses untuk menghapus data ini!";
        header("Location: surat-keluar.php");
        exit;
    }
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT file_path FROM manajemen_surat WHERE id = ? AND kategori = 'Surat Keluar'");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if ($doc) {
            $stmt = $pdo->prepare("DELETE FROM manajemen_surat WHERE id = ?");
            $stmt->execute([$id]);

            if ($doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
            $_SESSION['success_msg'] = "Surat Keluar berhasil dihapus!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: surat-keluar.php");
    exit;
}

// Query all Surat Keluar
try {
    $stmt = $pdo->prepare("SELECT * FROM manajemen_surat WHERE kategori = 'Surat Keluar' ORDER BY created_at DESC");
    $stmt->execute();
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Calculate Stats for Surat Keluar
try {
    // Total Surat Keluar
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = 'Surat Keluar'");
    $stmt->execute();
    $totalSuratKeluar = $stmt->fetchColumn();

    // Selesai
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = 'Surat Keluar' AND status_tindak_lanjut = 'Selesai'");
    $stmt->execute();
    $countSelesai = $stmt->fetchColumn();

    // Dalam Proses
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = 'Surat Keluar' AND status_tindak_lanjut = 'Dalam Proses'");
    $stmt->execute();
    $countProses = $stmt->fetchColumn();

    // Pending
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = 'Surat Keluar' AND status_tindak_lanjut = 'Pending'");
    $stmt->execute();
    $countPending = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalSuratKeluar = $countSelesai = $countProses = $countPending = 0;
}

// Helper badge functions
if (!function_exists('getKategoriBadgeClass')) {
    function getKategoriBadgeClass($kategori) {
        return 'bg-purple-100 text-purple-800 border-purple-200';
    }
}

if (!function_exists('getStatusBadgeClass')) {
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
    <title>Surat Keluar - RS Taman Harapan Baru</title>
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
                <!-- Title & Add Button -->
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Surat Keluar</h1>
                        <p class="text-gray-600 mt-2">Manajemen surat keluar resmi rumah sakit</p>
                    </div>
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
                                <p class="text-sm text-gray-500 mb-1">Total Surat Keluar</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalSuratKeluar; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">📤</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Selesai</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $countSelesai; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">✓</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Dalam Proses</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $countProses; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">⚙️</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Pending</p>
                                <h3 class="text-3xl font-bold text-amber-600"><?php echo $countPending; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-500 rounded-2xl flex items-center justify-center text-3xl">⏳</div>
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
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tujuan Surat</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Perihal</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada dokumen Surat Keluar yang tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $index => $doc): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-gray-700 text-sm font-medium">
                                                <?php echo $index + 1; ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['nomor_surat']); ?></p>
                                                <p class="text-sm text-gray-500"><?php echo formatDate($doc['tanggal_surat']); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm">
                                                <?php echo htmlspecialchars($doc['asal_pengirim']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 max-w-xs text-sm truncate" title="<?php echo htmlspecialchars($doc['perihal']); ?>">
                                                <?php echo htmlspecialchars($doc['perihal']); ?>
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
                                                            Lihat
                                                        </a>
                                                    <?php endif; ?>
                                                     <?php if (canUserEditOrDelete('sekretariat')): ?>
                                                         <a href="surat-keluar.php?delete=<?php echo $doc['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus surat keluar ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
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

    <!-- Modal Form (Id modal) -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Tambah Surat Keluar</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Surat</label>
                    <input type="text" name="nomor_surat" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                    <input type="text" value="Surat Keluar" readonly class="w-full px-4 py-2 border border-gray-200 bg-gray-50 rounded-xl text-gray-500 font-medium">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tujuan / Penerima</label>
                    <input type="text" name="asal_pengirim" required placeholder="Contoh: Dinas Kesehatan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Perihal</label>
                    <textarea name="perihal" required rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Tindak Lanjut</label>
                    <select name="status_tindak_lanjut" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="Pending" selected>Pending</option>
                        <option value="Dalam Proses">Dalam Proses</option>
                        <option value="Selesai">Selesai</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Surat</label>
                    <input type="date" name="tanggal_surat" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
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
