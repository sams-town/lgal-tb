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

// Initialize database check/creation just in case
if (isset($isLocal) && $isLocal) {
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
    } catch (PDOException $e) {
        // Fail silently
    }
}

// Handle form submission for adding new document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_surat'])) {
    $nomor_surat = $_POST['nomor_surat'] ?? '';
    $kategori = $_POST['kategori'] ?? 'Surat Masuk';
    $asal_pengirim = $_POST['asal_pengirim'] ?? '';
    $perihal = $_POST['perihal'] ?? '';
    $tanggal_surat = $_POST['tanggal_surat'] ?? '';
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

    try {
        $stmt = $pdo->prepare("
            INSERT INTO manajemen_surat (nomor_surat, kategori, asal_pengirim, perihal, tanggal_surat, tanggal_diterima, status_tindak_lanjut, file_path, kepada, cc)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nomor_surat, $kategori, $asal_pengirim, $perihal, $tanggal_surat, $tanggal_diterima, $status_tindak_lanjut, $file_path, $kepada, $cc]);
        
        // Send notifications
        $penerima = [];
        if (isset($_POST['kepada']) && is_array($_POST['kepada'])) {
            $penerima = array_merge($penerima, $_POST['kepada']);
        }
        if (isset($_POST['cc']) && is_array($_POST['cc'])) {
            $penerima = array_merge($penerima, $_POST['cc']);
        }
        
        $penerima = array_unique($penerima);
        foreach ($penerima as $role_or_user) {
            createNotification(
                "$kategori Baru",
                "Ada $kategori baru dari $asal_pengirim dengan perihal: $perihal.",
                $role_or_user
            );
        }

        $_SESSION['success_msg'] = "Dokumen $kategori berhasil ditambahkan!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal menyimpan data: " . $e->getMessage();
    }

    header("Location: surat-masuk.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    if (!canUserEditOrDelete('sekretariat')) {
        $_SESSION['error_msg'] = "Anda tidak memiliki akses untuk menghapus data ini!";
        header("Location: surat-masuk.php");
        exit;
    }
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT file_path, kategori FROM manajemen_surat WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if ($doc) {
            $stmt = $pdo->prepare("DELETE FROM manajemen_surat WHERE id = ?");
            $stmt->execute([$id]);

            if ($doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
            $_SESSION['success_msg'] = "Dokumen " . htmlspecialchars($doc['kategori']) . " berhasil dihapus!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: surat-masuk.php");
    exit;
}

// Filter and query setup
$selected_kategori = $_GET['kategori'] ?? 'Surat Masuk';
$allowed_kategori = ['Semua', 'Surat Masuk', 'Disposisi', 'Notulen', 'Memo'];
if (!in_array($selected_kategori, $allowed_kategori)) {
    $selected_kategori = 'Surat Masuk';
}

try {
    if ($selected_kategori === 'Semua') {
        $stmt = $pdo->query("SELECT * FROM manajemen_surat WHERE kategori IN ('Surat Masuk', 'Disposisi', 'Notulen', 'Memo') ORDER BY created_at DESC");
        $documents = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM manajemen_surat WHERE kategori = ? ORDER BY created_at DESC");
        $stmt->execute([$selected_kategori]);
        $documents = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $documents = [];
}

// Calculate Stats for Surat Masuk & Internal
try {
    // Total Surat Masuk & Internal
    $stmt = $pdo->query("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ('Surat Masuk', 'Disposisi', 'Notulen', 'Memo')");
    $totalSuratMasukInternal = $stmt->fetchColumn();

    // Surat Masuk
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = ?");
    $stmt->execute(['Surat Masuk']);
    $countSuratMasuk = $stmt->fetchColumn();

    // Disposisi
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = ?");
    $stmt->execute(['Disposisi']);
    $countDisposisi = $stmt->fetchColumn();

    // Perlu Tindak Lanjut (Pending / Dalam Proses)
    $stmt = $pdo->query("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ('Surat Masuk', 'Disposisi', 'Notulen', 'Memo') AND status_tindak_lanjut IN ('Pending', 'Dalam Proses')");
    $perluTindakLanjut = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalSuratMasukInternal = $countSuratMasuk = $countDisposisi = $perluTindakLanjut = 0;
}

// Helper badge functions
if (!function_exists('getKategoriBadgeClass')) {
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
    <title>Surat Masuk & Internal - RS Taman Harapan Baru</title>
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
                <!-- Title & Filter bar -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Surat Masuk & Dokumen Internal</h1>
                        <p class="text-gray-600 mt-2">Manajemen surat masuk, disposisi, notulen, dan memo internal rumah sakit</p>
                    </div>
                    
                    <!-- Category Filter Dropdown -->
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-gray-700">Kategori:</span>
                        <select onchange="location = this.value;" class="bg-white border border-gray-200 rounded-xl px-4 py-2 text-sm font-medium text-gray-700 shadow-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="surat-masuk.php?kategori=Semua" <?php echo $selected_kategori === 'Semua' ? 'selected' : ''; ?>>Semua Dokumen</option>
                            <option value="surat-masuk.php?kategori=Surat Masuk" <?php echo $selected_kategori === 'Surat Masuk' ? 'selected' : ''; ?>>Surat Masuk</option>
                            <option value="surat-masuk.php?kategori=Disposisi" <?php echo $selected_kategori === 'Disposisi' ? 'selected' : ''; ?>>Disposisi</option>
                            <option value="surat-masuk.php?kategori=Notulen" <?php echo $selected_kategori === 'Notulen' ? 'selected' : ''; ?>>Notulen</option>
                            <option value="surat-masuk.php?kategori=Memo" <?php echo $selected_kategori === 'Memo' ? 'selected' : ''; ?>>Memo</option>
                        </select>
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
                                <p class="text-sm text-gray-500 mb-1">Total Berkas Masuk/Internal</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalSuratMasukInternal; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Surat Masuk</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $countSuratMasuk; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">📥</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Disposisi</p>
                                <h3 class="text-3xl font-bold text-amber-600"><?php echo $countDisposisi; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl flex items-center justify-center text-3xl">📝</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Perlu Tindak Lanjut</p>
                                <h3 class="text-3xl font-bold text-red-600"><?php echo $perluTindakLanjut; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">⚠️</div>
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
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Asal Pengirim</th>
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
                                            Belum ada dokumen yang tersedia untuk kategori ini
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
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo getKategoriBadgeClass($doc['kategori']); ?>">
                                                    <?php echo htmlspecialchars($doc['kategori']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-sm">
                                                <?php echo htmlspecialchars($doc['asal_pengirim']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 max-w-xs text-sm truncate" title="<?php echo htmlspecialchars($doc['perihal']); ?>">
                                                <?php echo htmlspecialchars($doc['perihal']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 text-xs">
                                                <?php if ($doc['kategori'] === 'Memo'): ?>
                                                    <?php 
                                                    $kepada = !empty($doc['kepada']) ? json_decode($doc['kepada'], true) : [];
                                                    $cc = !empty($doc['cc']) ? json_decode($doc['cc'], true) : [];
                                                    ?>
                                                    <?php if (!empty($kepada)): ?>
                                                        <p><strong>KEPADA:</strong> <?php echo htmlspecialchars(implode(', ', $kepada)); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($cc)): ?>
                                                        <p class="mt-1"><strong>CC:</strong> <?php echo htmlspecialchars(implode(', ', $cc)); ?></p>
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
                                                            Lihat
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (canUserEditOrDelete('sekretariat')): ?>
                                                        <a href="surat-masuk.php?delete=<?php echo $doc['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
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
                <h2 class="text-xl font-bold text-gray-900">Tambah Surat / Berkas</h2>
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
                        <option value="Surat Masuk" selected>Surat Masuk</option>
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
