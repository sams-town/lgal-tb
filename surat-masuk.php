<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!hasPermission('sekretariat_view')) {
    header("Location: dashboard.php");
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
    if (!canUserEditOrDelete('sekretariat')) {
        $_SESSION['error_msg'] = "Anda tidak memiliki akses untuk menambah data!";
    } else {
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

        // Field khusus Internal Memo
        $dari           = $_POST['dari'] ?? null;
        $isi_surat      = $_POST['isi_surat'] ?? null;
        $penanda_tangan = $_POST['penanda_tangan'] ?? null;
        $jabatan_ttd    = $_POST['jabatan_ttd'] ?? null;
        $tembusan       = !empty($_POST['tembusan_raw'])
                          ? json_encode(array_values(array_filter(array_map('trim', explode("\n", $_POST['tembusan_raw'])))))
                          : null;
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
                INSERT INTO manajemen_surat (nomor_surat, kategori, asal_pengirim, perihal, tanggal_surat, tanggal_diterima, status_tindak_lanjut, file_path, kepada, cc, dari, isi_surat, penanda_tangan, jabatan_ttd, tembusan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nomor_surat, $kategori, $asal_pengirim, $perihal, $tanggal_surat, $tanggal_diterima, $status_tindak_lanjut, $file_path, $kepada, $cc, $dari, $isi_surat, $penanda_tangan, $jabatan_ttd, $tembusan]);
            
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
    }

    header("Location: surat-masuk.php");
    exit;
}

// Handle form submission for editing document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_surat'])) {
    if (!canUserEditOrDelete('sekretariat')) {
        $_SESSION['error_msg'] = "Anda tidak memiliki akses untuk mengedit data!";
    } else {
        $edit_id = (int)$_POST['edit_id'];
        $nomor_surat = $_POST['nomor_surat'] ?? '';
        $kategori = $_POST['kategori'] ?? 'Surat Masuk';
        $asal_pengirim = $_POST['asal_pengirim'] ?? '';
        $perihal = $_POST['perihal'] ?? '';
        $tanggal_surat = $_POST['tanggal_surat'] ?? '';
        $tanggal_diterima = !empty($_POST['tanggal_diterima']) ? $_POST['tanggal_diterima'] : null;
        $kepada = isset($_POST['kepada']) ? json_encode($_POST['kepada']) : null;
        $cc = isset($_POST['cc']) ? json_encode($_POST['cc']) : null;

        // Get current file path
        $stmt = $pdo->prepare("SELECT file_path FROM manajemen_surat WHERE id = ?");
        $stmt->execute([$edit_id]);
        $current_doc = $stmt->fetch();
        $file_path = $current_doc['file_path'] ?? null;

        // Handle file upload if new file is provided
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/sekretariat/';
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
                UPDATE manajemen_surat 
                SET nomor_surat = ?, kategori = ?, asal_pengirim = ?, perihal = ?, tanggal_surat = ?, tanggal_diterima = ?, file_path = ?, kepada = ?, cc = ?
                WHERE id = ?
            ");
            $stmt->execute([$nomor_surat, $kategori, $asal_pengirim, $perihal, $tanggal_surat, $tanggal_diterima, $file_path, $kepada, $cc, $edit_id]);
            
            $_SESSION['success_msg'] = "Dokumen berhasil diperbarui!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Gagal memperbarui data: " . $e->getMessage();
        }
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
$kategoriMasukAll = [
    'Surat Masuk','Internal Memo','Surat Tugas','Surat Keterangan',
    'Surat Pernyataan','Surat Undangan','Surat Edaran','Surat Kuasa',
    'Sertifikat','Surat Perintah Kerja (SPK)','Berita Acara',
    'Perjanjian Kerjasama (PKS)','SPO','Peraturan Direktur (Perdir)'
];
$selected_kategori = $_GET['kategori'] ?? 'Semua';
$allowed_kategori  = array_merge(['Semua'], $kategoriMasukAll);
if (!in_array($selected_kategori, $allowed_kategori)) {
    $selected_kategori = 'Semua';
}

try {
    if ($selected_kategori === 'Semua') {
        $inList = implode(',', array_fill(0, count($kategoriMasukAll), '?'));
        $stmt = $pdo->prepare("SELECT * FROM manajemen_surat WHERE kategori IN ($inList) ORDER BY created_at DESC");
        $stmt->execute($kategoriMasukAll);
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
    $inList = implode(',', array_fill(0, count($kategoriMasukAll), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inList)");
    $stmt->execute($kategoriMasukAll);
    $totalSuratMasukInternal = $stmt->fetchColumn();

    // Surat Masuk
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = ?");
    $stmt->execute(['Surat Masuk']);
    $countSuratMasuk = $stmt->fetchColumn();

    // Disposisi / Internal Memo
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori = ?");
    $stmt->execute(['Internal Memo']);
    $countDisposisi = $stmt->fetchColumn();

    // Perlu Tindak Lanjut (Pending / Dalam Proses)
    $inList2 = implode(',', array_fill(0, count($kategoriMasukAll), '?'));
    $params  = array_merge($kategoriMasukAll, ['Pending','Dalam Proses']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inList2) AND status_tindak_lanjut IN (?,?)");
    $stmt->execute($params);
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
                    
                    <div class="flex items-center gap-3">
                        <?php if (canUserEditOrDelete('sekretariat')): ?>
                            <button onclick="openModal()" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors shadow-sm">
                                ➕
                                <span>Tambah Surat</span>
                            </button>
                        <?php endif; ?>
                        
                        <!-- Category Filter Dropdown -->
                        <span class="text-sm font-medium text-gray-700">Kategori:</span>
                        <select onchange="location = this.value;" class="bg-white border border-gray-200 rounded-xl px-4 py-2 text-sm font-medium text-gray-700 shadow-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="surat-masuk.php?kategori=Semua" <?php echo $selected_kategori === 'Semua' ? 'selected' : ''; ?>>Semua Dokumen</option>
                            <?php foreach ($kategoriMasukAll as $kat): ?>
                            <option value="surat-masuk.php?kategori=<?= urlencode($kat) ?>" <?php echo $selected_kategori === $kat ? 'selected' : ''; ?>><?= htmlspecialchars($kat) ?></option>
                            <?php endforeach; ?>
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
                                                    <?php if ($doc['kategori'] === 'Internal Memo'): ?>
                                                        <a href="cetak_internal_memo.php?id=<?= $doc['id'] ?>" target="_blank" class="px-3 py-1 text-sm bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition-colors">
                                                            🖨 Memo
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (canUserEditOrDelete('sekretariat')): ?>
                                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($doc), ENT_QUOTES); ?>)" class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                                                            Edit
                                                        </button>
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

    <!-- Modal Form -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 id="modal-title" class="text-xl font-bold text-gray-900">Tambah Surat / Berkas</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="edit_id" id="edit_id" value="">

                <!-- Nomor, Kategori, Tanggal -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Surat</label>
                        <input type="text" name="nomor_surat" id="nomor_surat" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                        <select name="kategori" id="kategoriSelect" required onchange="toggleMemoFieldsMasuk(this.value)"
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            <?php foreach ($kategoriMasukAll as $kat): ?>
                            <option value="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Surat</label>
                        <input type="date" name="tanggal_surat" id="tanggal_surat" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                </div>

                <!-- ═══ FORM INTERNAL MEMO ═══ -->
                <div id="fieldsMemoMasuk" class="hidden space-y-4">
                    <div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-2 text-xs text-indigo-700 font-medium">
                        ✉ Form Internal Memo
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kepada Yth <span class="text-red-500">*</span></label>
                            <input type="text" name="asal_pengirim" id="asal_pengirim_memo_masuk" placeholder="Nama penerima / unit" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dari <span class="text-red-500">*</span></label>
                            <input type="text" name="dari" id="dari_masuk" placeholder="Nama pengirim / unit" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Perihal <span class="text-red-500">*</span></label>
                        <input type="text" name="perihal" id="perihal_memo_masuk" placeholder="Perihal memo" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Isi Memo</label>
                        <textarea name="isi_surat" id="isi_memo_masuk" rows="4" placeholder="Tulis isi internal memo..."
                                  class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Penanda Tangan</label>
                            <input type="text" name="penanda_tangan" id="penanda_memo_masuk" placeholder="Nama lengkap" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
                            <input type="text" name="jabatan_ttd" id="jabatan_memo_masuk" placeholder="Jabatan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tembusan <span class="text-xs text-gray-400">opsional — satu per baris</span></label>
                        <textarea name="tembusan_raw" id="tembusan_masuk" rows="2"
                                  placeholder="Direktur Utama&#10;Kepala Unit Terkait"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm"></textarea>
                    </div>
                </div>

                <!-- ═══ FORM SURAT BIASA ═══ -->
                <div id="fieldsSuratMasuk" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Asal / Pengirim</label>
                        <input type="text" name="asal_pengirim" id="asal_pengirim" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Perihal / Ringkasan</label>
                        <textarea name="perihal" id="perihal" required rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Diterima</label>
                            <input type="date" name="tanggal_diterima" id="tanggal_diterima" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Upload File Berkas (PDF)</label>
                            <input type="file" name="file" id="file" accept=".pdf,.doc,.docx" class="w-full px-4 py-2 border border-gray-300 rounded-xl text-sm">
                            <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin mengubah berkas</p>
                        </div>
                    </div>
                </div>

                <!-- Upload juga untuk memo -->
                <div id="fileUploadMemo" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload File Berkas</label>
                    <input type="file" name="file" id="file_memo" accept=".pdf,.doc,.docx" class="w-full px-4 py-2 border border-gray-300 rounded-xl text-sm">
                </div>

                <!-- Tombol -->
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors text-sm">
                        Batal
                    </button>
                    <button type="button" id="previewMemoBtn" onclick="previewMemoMasuk()"
                            class="hidden px-4 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-xl font-medium hover:bg-indigo-100 transition-colors text-sm">
                        👁 Preview Memo
                    </button>
                    <button type="submit" name="tambah_surat" id="submitBtn" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors text-sm">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    <script>
        // ── Toggle form memo / surat biasa ──────────────────────
        function toggleMemoFieldsMasuk(kat) {
            const isMemo = (kat === 'Internal Memo');
            document.getElementById('fieldsMemoMasuk').classList.toggle('hidden', !isMemo);
            document.getElementById('fieldsSuratMasuk').classList.toggle('hidden',  isMemo);
            document.getElementById('fileUploadMemo').classList.toggle('hidden',   !isMemo);
            const prevBtn = document.getElementById('previewMemoBtn');
            if (prevBtn) prevBtn.classList.toggle('hidden', !isMemo);
        }

        // ── Override openModal ──────────────────────────────────
        window.openModal = function(modalId) {
            if (modalId === 'modal' || !modalId) {
                resetFormMasuk();
                document.getElementById('submitBtn').name = 'tambah_surat';
                document.getElementById('submitBtn').textContent = 'Simpan';
                document.getElementById('modal-title').textContent = 'Tambah Surat / Berkas';
                toggleMemoFieldsMasuk('Surat Masuk');
            }
            const modal = document.getElementById(modalId || 'modal');
            if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
        };

        // ── Reset ───────────────────────────────────────────────
        function resetFormMasuk() {
            document.getElementById('edit_id').value              = '';
            document.getElementById('nomor_surat').value          = '';
            document.getElementById('tanggal_surat').value        = '';
            document.getElementById('kategoriSelect').value       = 'Surat Masuk';
            // surat biasa
            document.getElementById('asal_pengirim').value        = '';
            document.getElementById('perihal').value              = '';
            document.getElementById('tanggal_diterima').value     = '';
            document.getElementById('file').value                 = '';
            // memo
            document.getElementById('asal_pengirim_memo_masuk').value = '';
            document.getElementById('dari_masuk').value               = '';
            document.getElementById('perihal_memo_masuk').value       = '';
            document.getElementById('isi_memo_masuk').value           = '';
            document.getElementById('penanda_memo_masuk').value       = '';
            document.getElementById('jabatan_memo_masuk').value       = '';
            document.getElementById('tembusan_masuk').value           = '';
        }

        // ── Edit Modal ──────────────────────────────────────────
        function openEditModal(doc) {
            resetFormMasuk();
            const kat = doc.kategori || 'Surat Masuk';
            document.getElementById('edit_id').value        = doc.id;
            document.getElementById('nomor_surat').value    = doc.nomor_surat || '';
            document.getElementById('tanggal_surat').value  = doc.tanggal_surat || '';
            document.getElementById('kategoriSelect').value = kat;
            toggleMemoFieldsMasuk(kat);

            if (kat === 'Internal Memo') {
                document.getElementById('asal_pengirim_memo_masuk').value = doc.asal_pengirim  || '';
                document.getElementById('dari_masuk').value               = doc.dari           || '';
                document.getElementById('perihal_memo_masuk').value       = doc.perihal        || '';
                document.getElementById('isi_memo_masuk').value           = doc.isi_surat      || '';
                document.getElementById('penanda_memo_masuk').value       = doc.penanda_tangan || '';
                document.getElementById('jabatan_memo_masuk').value       = doc.jabatan_ttd    || '';
                let tArr = [];
                try { tArr = doc.tembusan ? JSON.parse(doc.tembusan) : []; } catch(e){}
                document.getElementById('tembusan_masuk').value = tArr.join('\n');
            } else {
                document.getElementById('asal_pengirim').value    = doc.asal_pengirim    || '';
                document.getElementById('perihal').value          = doc.perihal          || '';
                document.getElementById('tanggal_diterima').value = doc.tanggal_diterima || '';
            }

            document.getElementById('submitBtn').name = 'edit_surat';
            document.getElementById('submitBtn').textContent = 'Simpan Perubahan';
            document.getElementById('modal-title').textContent = 'Edit ' + kat;
            const modal = document.getElementById('modal');
            modal.classList.remove('hidden'); modal.classList.add('flex');
        }

        // ── Close ───────────────────────────────────────────────
        function closeModal(modalId = 'modal') {
            const el = document.getElementById(modalId);
            if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
        }

        // ── Preview Internal Memo ───────────────────────────────
        function previewMemoMasuk() {
            const p = new URLSearchParams({
                no_memo:     document.getElementById('nomor_surat').value,
                kepada:      document.getElementById('asal_pengirim_memo_masuk').value,
                dari:        document.getElementById('dari_masuk').value,
                perihal:     document.getElementById('perihal_memo_masuk').value,
                tanggal:     document.getElementById('tanggal_surat').value,
                isi:         document.getElementById('isi_memo_masuk').value,
                nama_ttd:    document.getElementById('penanda_memo_masuk').value,
                jabatan_ttd: document.getElementById('jabatan_memo_masuk').value,
                tembusan:    document.getElementById('tembusan_masuk').value,
            });
            window.open('cetak_internal_memo.php?' + p.toString(), '_blank');
        }
    </script>
</body>
</html>
