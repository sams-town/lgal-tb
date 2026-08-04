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

// Handle form submission for adding new Surat Keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_surat'])) {
    if (!canUserEditOrDelete('sekretariat')) {
        $_SESSION['error_msg'] = "Anda tidak memiliki akses untuk menambah data!";
    } else {
        $nomor_surat          = $_POST['nomor_surat'] ?? '';
        $kategori             = $_POST['kategori'] ?? 'Surat Keluar';
        $asal_pengirim        = $_POST['asal_pengirim'] ?? '';
        $perihal              = $_POST['perihal'] ?? '';
        $tanggal_surat        = $_POST['tanggal_surat'] ?? '';
        $tanggal_diterima     = null;
        $status_tindak_lanjut = $_POST['status_tindak_lanjut'] ?? 'Pending';
        $lampiran             = $_POST['lampiran'] ?? null;
        $tujuan_alamat        = $_POST['tujuan_alamat'] ?? null;
        $up_nama              = $_POST['up_nama'] ?? null;
        $ucapan_mitra         = $_POST['ucapan_mitra'] ?? null;
        $isi_surat            = $_POST['isi_surat'] ?? null;
        $penanda_tangan       = $_POST['penanda_tangan'] ?? null;
        $jabatan_ttd          = $_POST['jabatan_ttd'] ?? null;
        $file_path            = null;

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
                INSERT INTO manajemen_surat (nomor_surat, kategori, asal_pengirim, perihal, tanggal_surat, tanggal_diterima, status_tindak_lanjut, file_path, lampiran, tujuan_alamat, up_nama, ucapan_mitra, isi_surat, penanda_tangan, jabatan_ttd)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nomor_surat, $kategori, $asal_pengirim, $perihal, $tanggal_surat, $tanggal_diterima, $status_tindak_lanjut, $file_path, $lampiran, $tujuan_alamat, $up_nama, $ucapan_mitra, $isi_surat, $penanda_tangan, $jabatan_ttd]);

            $_SESSION['success_msg'] = "Surat Keluar berhasil ditambahkan!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Gagal menyimpan data: " . $e->getMessage();
        }
    }

    header("Location: surat-keluar.php");
    exit;
}

// Handle form submission for editing Surat Keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_surat'])) {
    if (!canUserEditOrDelete('sekretariat')) {
        $_SESSION['error_msg'] = "Anda tidak memiliki akses untuk mengedit data!";
    } else {
        $edit_id              = (int)$_POST['edit_id'];
        $nomor_surat          = $_POST['nomor_surat'] ?? '';
        $kategori             = $_POST['kategori'] ?? 'Surat Keluar';
        $asal_pengirim        = $_POST['asal_pengirim'] ?? '';
        $perihal              = $_POST['perihal'] ?? '';
        $tanggal_surat        = $_POST['tanggal_surat'] ?? '';
        $status_tindak_lanjut = $_POST['status_tindak_lanjut'] ?? 'Pending';
        $lampiran             = $_POST['lampiran'] ?? null;
        $tujuan_alamat        = $_POST['tujuan_alamat'] ?? null;
        $up_nama              = $_POST['up_nama'] ?? null;
        $ucapan_mitra         = $_POST['ucapan_mitra'] ?? null;
        $isi_surat            = $_POST['isi_surat'] ?? null;
        $penanda_tangan       = $_POST['penanda_tangan'] ?? null;
        $jabatan_ttd          = $_POST['jabatan_ttd'] ?? null;

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
                if ($file_path && file_exists($file_path)) unlink($file_path);
                $file_path = $targetFile;
            }
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE manajemen_surat
                SET nomor_surat=?, kategori=?, asal_pengirim=?, perihal=?, tanggal_surat=?,
                    status_tindak_lanjut=?, file_path=?,
                    lampiran=?, tujuan_alamat=?, up_nama=?, ucapan_mitra=?,
                    isi_surat=?, penanda_tangan=?, jabatan_ttd=?
                WHERE id=?
            ");
            $stmt->execute([
                $nomor_surat, $kategori, $asal_pengirim, $perihal, $tanggal_surat,
                $status_tindak_lanjut, $file_path,
                $lampiran, $tujuan_alamat, $up_nama, $ucapan_mitra,
                $isi_surat, $penanda_tangan, $jabatan_ttd,
                $edit_id
            ]);
            $_SESSION['success_msg'] = "Surat Keluar berhasil diperbarui!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Gagal memperbarui data: " . $e->getMessage();
        }
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
        $stmt = $pdo->prepare("SELECT file_path FROM manajemen_surat WHERE id = ?");
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

// Kategori surat keluar
$kategoriKeluarAll = [
    'Internal Memo','Disposisi','Surat Keluar','Surat Tugas','Surat Keterangan',
    'Surat Pernyataan','Surat Undangan','Surat Edaran','Surat Kuasa',
    'Sertifikat','Surat Perintah Kerja (SPK)','Berita Acara',
    'Perjanjian Kerjasama (PKS)','SPO','Peraturan Direktur (Perdir)'
];
$selected_kategori_keluar = $_GET['kategori'] ?? 'Semua';
if (!in_array($selected_kategori_keluar, array_merge(['Semua'], $kategoriKeluarAll))) {
    $selected_kategori_keluar = 'Semua';
}

// Query all Surat Keluar
try {
    if ($selected_kategori_keluar === 'Semua') {
        $inList = implode(',', array_fill(0, count($kategoriKeluarAll), '?'));
        $stmt = $pdo->prepare("SELECT * FROM manajemen_surat WHERE kategori IN ($inList) ORDER BY created_at DESC");
        $stmt->execute($kategoriKeluarAll);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM manajemen_surat WHERE kategori = ? ORDER BY created_at DESC");
        $stmt->execute([$selected_kategori_keluar]);
    }
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Calculate Stats for Surat Keluar
try {
    $inList = implode(',', array_fill(0, count($kategoriKeluarAll), '?'));

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inList)");
    $stmt->execute($kategoriKeluarAll);
    $totalSuratKeluar = $stmt->fetchColumn();

    $params = array_merge($kategoriKeluarAll, ['Selesai']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inList) AND status_tindak_lanjut = ?");
    $stmt->execute($params);
    $countSelesai = $stmt->fetchColumn();

    $params = array_merge($kategoriKeluarAll, ['Dalam Proses']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inList) AND status_tindak_lanjut = ?");
    $stmt->execute($params);
    $countProses = $stmt->fetchColumn();

    $params = array_merge($kategoriKeluarAll, ['Pending']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($inList) AND status_tindak_lanjut = ?");
    $stmt->execute($params);
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
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Surat Keluar</h1>
                        <p class="text-gray-600 mt-2">Manajemen surat keluar resmi rumah sakit</p>
                    </div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <?php if (canUserEditOrDelete('sekretariat')): ?>
                            <button onclick="openModal()" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors shadow-sm">
                                ➕ <span>Tambah Surat</span>
                            </button>
                        <?php endif; ?>
                        <span class="text-sm font-medium text-gray-700">Kategori:</span>
                        <select onchange="location = this.value;" class="bg-white border border-gray-200 rounded-xl px-4 py-2 text-sm font-medium text-gray-700 shadow-sm focus:ring-2 focus:ring-emerald-500">
                            <option value="surat-keluar.php?kategori=Semua" <?= $selected_kategori_keluar==='Semua'?'selected':'' ?>>Semua</option>
                            <?php foreach ($kategoriKeluarAll as $kat): ?>
                            <option value="surat-keluar.php?kategori=<?= urlencode($kat) ?>" <?= $selected_kategori_keluar===$kat?'selected':'' ?>><?= htmlspecialchars($kat) ?></option>
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
                                                    <a href="cetak_surat_keluar.php?id=<?= $doc['id'] ?>" target="_blank"
                                                       class="px-3 py-1 text-sm bg-teal-100 text-teal-700 rounded-lg hover:bg-teal-200 transition-colors">
                                                        🖨 Cetak
                                                    </a>
                                                     <?php if (canUserEditOrDelete('sekretariat')): ?>
                                                         <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($doc), ENT_QUOTES); ?>)" class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                                                             Edit
                                                         </button>
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
                <h2 id="modal-title" class="text-xl font-bold text-gray-900">Tambah Surat Keluar</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="edit_id" id="edit_id" value="">

                <!-- Baris 1: Nomor, Kategori, Tanggal -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Surat <span class="text-red-500">*</span></label>
                        <input type="text" name="nomor_surat" id="nomor_surat" required placeholder="xx/DIR-EXT/RS.THB/BLN/THN" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori <span class="text-red-500">*</span></label>
                        <select name="kategori" id="kategoriSelectKeluar" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            <?php foreach ($kategoriKeluarAll as $kat): ?>
                            <option value="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Surat <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal_surat" id="tanggal_surat" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                </div>

                <!-- Perihal & Lampiran -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Perihal <span class="text-red-500">*</span></label>
                        <input type="text" name="perihal" id="perihal" required placeholder="Perihal surat" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lampiran</label>
                        <input type="text" name="lampiran" id="lampiran" placeholder="Contoh: 1 berkas" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                </div>

                <!-- Kepada Yth -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tujuan / Nama Penerima <span class="text-red-500">*</span></label>
                        <input type="text" name="asal_pengirim" id="asal_pengirim" required placeholder="Nama Perusahaan / Bapak / Ibu" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Alamat Tujuan</label>
                        <input type="text" name="tujuan_alamat" id="tujuan_alamat" placeholder="Alamat penerima" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                </div>

                <!-- Up & Ucapan mitra -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Up. (Nama & Jabatan) <span class="text-xs text-gray-400">opsional</span></label>
                        <input type="text" name="up_nama" id="up_nama" placeholder="Contoh: Bpk. Ahmad – Manager HRD" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ucapan Terima Kasih Mitra <span class="text-xs text-gray-400">opsional</span></label>
                        <input type="text" name="ucapan_mitra" id="ucapan_mitra" placeholder="kerjasama yang terjalin" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                </div>

                <!-- Isi surat -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Isi Surat <span class="text-red-500">*</span></label>
                    <textarea name="isi_surat" id="isi_surat" rows="5" required placeholder="Tulis isi / badan surat di sini..." class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"></textarea>
                </div>

                <!-- Penanda tangan -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Penanda Tangan</label>
                        <input type="text" name="penanda_tangan" id="penanda_tangan" placeholder="Nama lengkap penanda tangan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan Penanda Tangan</label>
                        <input type="text" name="jabatan_ttd" id="jabatan_ttd" placeholder="Jabatan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                </div>

                <!-- Status & Upload -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Tindak Lanjut</label>
                        <select name="status_tindak_lanjut" id="status_tindak_lanjut" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            <option value="Pending" selected>Pending</option>
                            <option value="Dalam Proses">Dalam Proses</option>
                            <option value="Selesai">Selesai</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload File Berkas</label>
                        <input type="file" name="file" id="file" accept=".pdf,.doc,.docx" class="w-full px-4 py-2 border border-gray-300 rounded-xl text-sm">
                        <p class="text-xs text-gray-400 mt-1">Biarkan kosong jika tidak ingin mengubah berkas</p>
                    </div>
                </div>

                <!-- Tombol -->
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors text-sm">
                        Batal
                    </button>
                    <button type="button" id="previewBtn" onclick="previewSurat()"
                            class="px-4 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-xl font-medium hover:bg-blue-100 transition-colors text-sm flex items-center gap-1.5">
                        👁 Preview Surat
                    </button>
                    <button type="submit" name="tambah_surat" id="submitBtn" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors text-sm">
                        Simpan
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
                document.getElementById('submitBtn').name = 'tambah_surat';
                document.getElementById('submitBtn').textContent = 'Simpan';
                document.getElementById('modal-title').textContent = 'Tambah Surat Keluar';
            }
            const modal = document.getElementById(modalId || 'modal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        };

        function resetForm() {
            document.getElementById('edit_id').value = '';
            document.getElementById('nomor_surat').value = '';
            document.getElementById('asal_pengirim').value = '';
            document.getElementById('perihal').value = '';
            document.getElementById('lampiran').value = '';
            document.getElementById('tujuan_alamat').value = '';
            document.getElementById('up_nama').value = '';
            document.getElementById('ucapan_mitra').value = '';
            document.getElementById('isi_surat').value = '';
            document.getElementById('penanda_tangan').value = '';
            document.getElementById('jabatan_ttd').value = '';
            document.getElementById('status_tindak_lanjut').value = 'Pending';
            document.getElementById('tanggal_surat').value = '';
            document.getElementById('file').value = '';
        }

        function openEditModal(doc) {
            document.getElementById('edit_id').value = doc.id;
            document.getElementById('nomor_surat').value = doc.nomor_surat || '';
            document.getElementById('kategoriSelectKeluar').value = doc.kategori || 'Surat Keluar';
            document.getElementById('asal_pengirim').value = doc.asal_pengirim || '';
            document.getElementById('perihal').value = doc.perihal || '';
            document.getElementById('lampiran').value = doc.lampiran || '';
            document.getElementById('tujuan_alamat').value = doc.tujuan_alamat || '';
            document.getElementById('up_nama').value = doc.up_nama || '';
            document.getElementById('ucapan_mitra').value = doc.ucapan_mitra || '';
            document.getElementById('isi_surat').value = doc.isi_surat || '';
            document.getElementById('penanda_tangan').value = doc.penanda_tangan || '';
            document.getElementById('jabatan_ttd').value = doc.jabatan_ttd || '';
            document.getElementById('status_tindak_lanjut').value = doc.status_tindak_lanjut || 'Pending';
            document.getElementById('tanggal_surat').value = doc.tanggal_surat || '';
            document.getElementById('file').value = '';
            
            document.getElementById('submitBtn').name = 'edit_surat';
            document.getElementById('submitBtn').textContent = 'Simpan Perubahan';
            document.getElementById('modal-title').textContent = 'Edit Surat Keluar';
            
            const modal = document.getElementById('modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal(modalId = 'modal') {
            const element = document.getElementById(modalId);
            if (element) {
                element.classList.add('hidden');
                element.classList.remove('flex');
            }
        }
        function previewSurat() {
            const params = new URLSearchParams({
                tanggal_surat:  document.getElementById('tanggal_surat').value,
                nomor_surat:    document.getElementById('nomor_surat').value,
                perihal:        document.getElementById('perihal').value,
                lampiran:       document.getElementById('lampiran').value,
                tujuan_nama:    document.getElementById('asal_pengirim').value,
                tujuan_alamat:  document.getElementById('tujuan_alamat').value,
                up_nama:        document.getElementById('up_nama').value,
                ucapan_mitra:   document.getElementById('ucapan_mitra').value,
                isi_surat:      document.getElementById('isi_surat').value,
                penanda_tangan: document.getElementById('penanda_tangan').value,
                jabatan_ttd:    document.getElementById('jabatan_ttd').value,
            });
            window.open('cetak_surat_keluar.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>
