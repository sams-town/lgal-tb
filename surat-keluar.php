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

        // Untuk Internal Memo: pakai field khusus memo
        if ($kategori === 'Internal Memo') {
            $asal_pengirim = $_POST['asal_pengirim_memo'] ?? $asal_pengirim;
            $perihal       = !empty($_POST['perihal_memo']) ? $_POST['perihal_memo'] : $perihal;
            $isi_surat     = !empty($_POST['isi_surat_memo']) ? $_POST['isi_surat_memo'] : $isi_surat;
            $penanda_tangan= !empty($_POST['penanda_tangan_memo']) ? $_POST['penanda_tangan_memo'] : $penanda_tangan;
            $jabatan_ttd   = !empty($_POST['jabatan_ttd_memo']) ? $_POST['jabatan_ttd_memo'] : $jabatan_ttd;
        }
        $dari      = $_POST['dari'] ?? null;
        $tembusan  = !empty($_POST['tembusan_raw'])
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
                INSERT INTO manajemen_surat (nomor_surat, kategori, asal_pengirim, perihal, tanggal_surat, tanggal_diterima, status_tindak_lanjut, file_path, lampiran, tujuan_alamat, up_nama, ucapan_mitra, isi_surat, penanda_tangan, jabatan_ttd, dari, tembusan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nomor_surat, $kategori, $asal_pengirim, $perihal, $tanggal_surat, $tanggal_diterima, $status_tindak_lanjut, $file_path, $lampiran, $tujuan_alamat, $up_nama, $ucapan_mitra, $isi_surat, $penanda_tangan, $jabatan_ttd, $dari, $tembusan]);

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

        // Untuk Internal Memo: pakai field khusus memo
        if ($kategori === 'Internal Memo') {
            $asal_pengirim = $_POST['asal_pengirim_memo'] ?? $asal_pengirim;
            $perihal       = !empty($_POST['perihal_memo']) ? $_POST['perihal_memo'] : $perihal;
            $isi_surat     = !empty($_POST['isi_surat_memo']) ? $_POST['isi_surat_memo'] : $isi_surat;
            $penanda_tangan= !empty($_POST['penanda_tangan_memo']) ? $_POST['penanda_tangan_memo'] : $penanda_tangan;
            $jabatan_ttd   = !empty($_POST['jabatan_ttd_memo']) ? $_POST['jabatan_ttd_memo'] : $jabatan_ttd;
        }
        $dari     = $_POST['dari'] ?? null;
        $tembusan = !empty($_POST['tembusan_raw'])
                    ? json_encode(array_values(array_filter(array_map('trim', explode("\n", $_POST['tembusan_raw'])))))
                    : null;

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
                    isi_surat=?, penanda_tangan=?, jabatan_ttd=?, dari=?, tembusan=?
                WHERE id=?
            ");
            $stmt->execute([
                $nomor_surat, $kategori, $asal_pengirim, $perihal, $tanggal_surat,
                $status_tindak_lanjut, $file_path,
                $lampiran, $tujuan_alamat, $up_nama, $ucapan_mitra,
                $isi_surat, $penanda_tangan, $jabatan_ttd, $dari, $tembusan,
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
                                                    <?php if ($doc['kategori'] === 'Internal Memo'): ?>
                                                    <a href="cetak_internal_memo.php?id=<?= $doc['id'] ?>" target="_blank"
                                                       class="px-3 py-1 text-sm bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition-colors">
                                                        🖨 Memo
                                                    </a>
                                                    <?php elseif ($doc['kategori'] === 'Surat Edaran'): ?>
                                                    <a href="cetak_surat_edaran.php?id=<?= $doc['id'] ?>" target="_blank"
                                                       class="px-3 py-1 text-sm bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition-colors">
                                                        🖨 Edaran
                                                    </a>
                                                    <?php elseif ($doc['kategori'] === 'Surat Pernyataan'): ?>
                                                    <a href="cetak_surat_pernyataan.php?id=<?= $doc['id'] ?>" target="_blank"
                                                       class="px-3 py-1 text-sm bg-rose-100 text-rose-700 rounded-lg hover:bg-rose-200 transition-colors">
                                                        🖨 Pernyataan
                                                    </a>
                                                    <?php elseif ($doc['kategori'] === 'Surat Tugas'): ?>
                                                    <a href="cetak_surat_tugas.php?id=<?= $doc['id'] ?>" target="_blank"
                                                       class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors">
                                                        🖨 Tugas
                                                    </a>
                                                    <?php elseif ($doc['kategori'] === 'Surat Undangan'): ?>
                                                    <a href="cetak_surat_undangan.php?id=<?= $doc['id'] ?>" target="_blank"
                                                       class="px-3 py-1 text-sm bg-sky-100 text-sky-700 rounded-lg hover:bg-sky-200 transition-colors">
                                                        🖨 Undangan
                                                    </a>
                                                    <?php else: ?>
                                                    <a href="cetak_surat_keluar.php?id=<?= $doc['id'] ?>" target="_blank"
                                                       class="px-3 py-1 text-sm bg-teal-100 text-teal-700 rounded-lg hover:bg-teal-200 transition-colors">
                                                        🖨 Cetak
                                                    </a>
                                                    <?php endif; ?>
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
                        <select name="kategori" id="kategoriSelectKeluar" required onchange="toggleMemoFields(this.value)" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
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

                <!-- === FORM INTERNAL MEMO === -->
                <div id="fieldsMemo" class="hidden space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kepada Yth <span class="text-red-500">*</span></label>
                            <input type="text" name="asal_pengirim" id="asal_pengirim_memo" placeholder="Nama penerima / unit" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dari <span class="text-red-500">*</span></label>
                            <input type="text" name="dari" id="dari" placeholder="Nama pengirim / unit" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Perihal <span class="text-red-500">*</span></label>
                        <input type="text" name="perihal" id="perihal_memo" placeholder="Perihal memo" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Isi Memo <span class="text-red-500">*</span></label>
                        <textarea name="isi_surat" id="isi_surat_memo" rows="4" placeholder="Tulis isi internal memo..." class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Penanda Tangan</label>
                            <input type="text" name="penanda_tangan" id="penanda_tangan_memo" placeholder="Nama lengkap" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
                            <input type="text" name="jabatan_ttd" id="jabatan_ttd_memo" placeholder="Jabatan penanda tangan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tembusan <span class="text-xs text-gray-400">opsional — satu per baris</span></label>
                        <textarea name="tembusan_raw" id="tembusan_raw" rows="3" placeholder="Contoh:&#10;Direktur Utama&#10;Kepala Unit Terkait" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm"></textarea>
                    </div>
                </div>

                <!-- === FORM SURAT KELUAR BIASA === -->
                <div id="fieldsSurat" class="space-y-4">
                    <!-- Perihal & Lampiran -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Perihal <span class="text-red-500">*</span></label>
                            <input type="text" name="perihal" id="perihal" placeholder="Perihal surat" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lampiran</label>
                            <input type="text" name="lampiran" id="lampiran" placeholder="Contoh: 1 berkas" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tujuan / Nama Penerima <span class="text-red-500">*</span></label>
                            <input type="text" name="asal_pengirim" id="asal_pengirim" placeholder="Nama Perusahaan / Bapak / Ibu" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Alamat Tujuan</label>
                            <input type="text" name="tujuan_alamat" id="tujuan_alamat" placeholder="Alamat penerima" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Up. (Nama & Jabatan) <span class="text-xs text-gray-400">opsional</span></label>
                            <input type="text" name="up_nama" id="up_nama" placeholder="Contoh: Bpk. Ahmad – Manager HRD" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Ucapan Terima Kasih Mitra <span class="text-xs text-gray-400">opsional</span></label>
                            <input type="text" name="ucapan_mitra" id="ucapan_mitra" placeholder="kerjasama yang terjalin" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Isi Surat <span class="text-red-500">*</span></label>
                        <textarea name="isi_surat" id="isi_surat" rows="5" placeholder="Tulis isi / badan surat di sini..." class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Penanda Tangan</label>
                            <input type="text" name="penanda_tangan" id="penanda_tangan" placeholder="Nama lengkap penanda tangan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan Penanda Tangan</label>
                            <input type="text" name="jabatan_ttd" id="jabatan_ttd" placeholder="Jabatan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                </div>

                <!-- ═══ FORM SURAT EDARAN ═══ -->
                <div id="fieldsEdaran" class="hidden space-y-4">
                    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-2 text-xs text-amber-700 font-medium">
                        📢 Form Surat Edaran
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tentang / Perihal <span class="text-red-500">*</span></label>
                        <input type="text" name="perihal" id="perihal_edaran" placeholder="Judul / subjek surat edaran" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Isi Surat Edaran <span class="text-red-500">*</span></label>
                        <textarea name="isi_surat" id="isi_surat_edaran" rows="5" placeholder="Tulis isi surat edaran..." class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Penanda Tangan</label>
                            <input type="text" name="penanda_tangan" id="penanda_edaran" placeholder="Nama lengkap" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
                            <input type="text" name="jabatan_ttd" id="jabatan_edaran" placeholder="Jabatan penanda tangan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                </div>

                <!-- ═══ FORM SURAT PERNYATAAN ═══ -->
                <div id="fieldsPernyataan" class="hidden space-y-4">
                    <div class="bg-rose-50 border border-rose-200 rounded-xl px-4 py-2 text-xs text-rose-700 font-medium">
                        📝 Form Surat Pernyataan
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Isi Pernyataan <span class="text-red-500">*</span></label>
                        <textarea name="isi_surat" id="isi_surat_pernyataan" rows="5"
                                  placeholder="Tulis isi pernyataan setelah 'Dengan ini menyatakan bahwa...'"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm"></textarea>
                        <p class="text-xs text-gray-400 mt-1">Teks dilanjutkan setelah "Dengan ini menyatakan bahwa..."</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Penanda Tangan</label>
                            <input type="text" name="penanda_tangan" id="penanda_pernyataan"
                                   value="dr. Andara Dwike, MARS, M.H., FISQua"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
                            <input type="text" name="jabatan_ttd" id="jabatan_pernyataan"
                                   value="Direktur Utama Rumah Sakit Taman Harapan Baru"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                </div>

                <!-- ═══ FORM SURAT TUGAS ═══ -->
                <div id="fieldsTugas" class="hidden space-y-4">
                    <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-2 text-xs text-green-700 font-medium">
                        📋 Form Surat Tugas
                    </div>
                    <div class="border border-gray-200 rounded-xl p-4 space-y-3">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Pemberi Tugas</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                                <input type="text" name="penanda_tangan" id="pemberi_tugas_nama"
                                       value="dr. Andara Dwike, MARS, M.H., FISQua"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
                                <input type="text" name="jabatan_ttd" id="pemberi_tugas_jabatan"
                                       value="Direktur Utama Rumah Sakit Taman Harapan Baru"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="border border-gray-200 rounded-xl p-4 space-y-3">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Penerima Tugas</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama <span class="text-red-500">*</span></label>
                                <input type="text" name="penerima_nama" id="penerima_tugas_nama" placeholder="Nama lengkap" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">NIK</label>
                                <input type="text" name="penerima_ktp" id="penerima_tugas_nik" placeholder="NIK / ID Karyawan" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
                                <input type="text" name="jabatan_kiri" id="penerima_tugas_jabatan" placeholder="Jabatan" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="border border-gray-200 rounded-xl p-4 space-y-3">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Detail Kegiatan</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Undangan Dari <span class="text-red-500">*</span></label>
                            <input type="text" name="untuk_kuasa" id="undangan_dari" placeholder="Nama instansi penyelenggara" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kegiatan / Perihal <span class="text-red-500">*</span></label>
                            <input type="text" name="perihal" id="nama_kegiatan" placeholder="Nama kegiatan" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hari, Tanggal</label>
                                <input type="text" name="hari_tanggal" id="hari_tanggal" placeholder="Senin, 1 Januari 2025" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Waktu</label>
                                <input type="text" name="waktu_acara" id="waktu_acara" placeholder="09.00 – 12.00 WIB" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tempat</label>
                                <input type="text" name="tujuan_alamat" id="tempat_tugas" placeholder="Nama gedung / ruangan" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ FORM SURAT UNDANGAN ═══ -->
                <div id="fieldsUndangan" class="hidden space-y-4">
                    <div class="bg-sky-50 border border-sky-200 rounded-xl px-4 py-2 text-xs text-sky-700 font-medium">
                        📨 Form Surat Undangan
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Perihal <span class="text-red-500">*</span></label>
                            <input type="text" name="perihal" id="perihal_undangan" value="UNDANGAN" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lampiran</label>
                            <input type="text" name="lampiran" id="lampiran_undangan" placeholder="Contoh: 1 berkas" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div class="border border-gray-200 rounded-xl p-4 space-y-3">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Kepada Yth.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama / Perusahaan <span class="text-red-500">*</span></label>
                                <input type="text" name="asal_pengirim" id="tujuan_nama_undangan" placeholder="Nama Bapak/Ibu/Instansi" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                                <input type="text" name="tujuan_alamat" id="tujuan_alamat_undangan" placeholder="Alamat penerima" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Up. (Nama & Jabatan) <span class="text-xs text-gray-400">opsional</span></label>
                            <input type="text" name="up_nama" id="up_undangan" placeholder="Contoh: Bpk. Ahmad – Manager HRD" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Yang Diundang <span class="text-red-500">*</span></label>
                            <input type="text" name="untuk_kuasa" id="diundang_keluar" placeholder="Nama/jabatan yang diundang" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Acara / Agenda <span class="text-red-500">*</span></label>
                            <input type="text" name="isi_surat" id="agenda_undangan" placeholder="Nama kegiatan / rapat" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div class="border border-gray-200 rounded-xl p-4 space-y-3">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Detail Acara</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hari, Tanggal</label>
                                <input type="text" name="hari_tanggal" id="hari_tanggal_undangan" placeholder="Senin, 1 Januari 2025" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tempat</label>
                                <input type="text" name="tujuan_alamat" id="tempat_undangan" placeholder="Ruang rapat / lokasi" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pukul Mulai</label>
                                <input type="text" name="waktu_acara" id="waktu_mulai_undangan" placeholder="09.00 WIB" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pukul Selesai</label>
                                <input type="text" name="waktu_selesai" id="waktu_selesai_undangan" placeholder="12.00 WIB" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Penanda Tangan</label>
                            <input type="text" name="penanda_tangan" id="penanda_undangan" placeholder="Nama lengkap" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
                            <input type="text" name="jabatan_ttd" id="jabatan_undangan" placeholder="Jabatan" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
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
        // ── Toggle form berdasarkan kategori ──────────────────
        function toggleMemoFields(kategori) {
            const isMemo       = (kategori === 'Internal Memo');
            const isEdaran     = (kategori === 'Surat Edaran');
            const isPernyataan = (kategori === 'Surat Pernyataan');
            const isTugas      = (kategori === 'Surat Tugas');
            const isBiasa      = !isMemo && !isEdaran && !isPernyataan && !isTugas;

            document.getElementById('fieldsMemo').classList.toggle('hidden',       !isMemo);
            document.getElementById('fieldsSurat').classList.toggle('hidden',      !isBiasa);
            document.getElementById('fieldsEdaran').classList.toggle('hidden',     !isEdaran);
            document.getElementById('fieldsPernyataan').classList.toggle('hidden', !isPernyataan);
            document.getElementById('fieldsTugas').classList.toggle('hidden',      !isTugas);

            const pb = document.getElementById('previewBtn');
            if (pb) {
                if (isMemo)           pb.textContent = '👁 Preview Memo';
                else if (isEdaran)    pb.textContent = '👁 Preview Edaran';
                else if (isPernyataan)pb.textContent = '👁 Preview Pernyataan';
                else if (isTugas)     pb.textContent = '👁 Preview Tugas';
                else                  pb.textContent = '👁 Preview Surat';
            }

            if (!isBiasa) {
                ['perihal','asal_pengirim','isi_surat','penanda_tangan','jabatan_ttd'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
            }
        }

        // ── Override openModal ────────────────────────────────
        const originalOpenModal = window.openModal;
        window.openModal = function(modalId) {
            if (modalId === 'modal' || !modalId) {
                resetForm();
                document.getElementById('submitBtn').name = 'tambah_surat';
                document.getElementById('submitBtn').textContent = 'Simpan';
                document.getElementById('modal-title').textContent = 'Tambah Surat Keluar';
                toggleMemoFields('Surat Keluar');
            }
            const modal = document.getElementById(modalId || 'modal');
            if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
        };

        // ── Reset form ────────────────────────────────────────
        function resetForm() {
            document.getElementById('edit_id').value           = '';
            document.getElementById('nomor_surat').value       = '';
            document.getElementById('tanggal_surat').value     = '';
            document.getElementById('status_tindak_lanjut').value = 'Pending';
            document.getElementById('file').value              = '';
            // surat biasa
            document.getElementById('asal_pengirim').value     = '';
            document.getElementById('perihal').value           = '';
            document.getElementById('lampiran').value          = '';
            document.getElementById('tujuan_alamat').value     = '';
            document.getElementById('up_nama').value           = '';
            document.getElementById('ucapan_mitra').value      = '';
            document.getElementById('isi_surat').value         = '';
            document.getElementById('penanda_tangan').value    = '';
            document.getElementById('jabatan_ttd').value       = '';
            // memo
            document.getElementById('asal_pengirim_memo').value= '';
            document.getElementById('dari').value              = '';
            document.getElementById('perihal_memo').value      = '';
            document.getElementById('isi_surat_memo').value    = '';
            document.getElementById('penanda_tangan_memo').value= '';
            document.getElementById('jabatan_ttd_memo').value  = '';
            document.getElementById('tembusan_raw').value      = '';
            // edaran
            try { document.getElementById('perihal_edaran').value  = ''; } catch(e){}
            try { document.getElementById('isi_surat_edaran').value= ''; } catch(e){}
            try { document.getElementById('penanda_edaran').value  = ''; } catch(e){}
            try { document.getElementById('jabatan_edaran').value  = ''; } catch(e){}
            // pernyataan
            try { document.getElementById('isi_surat_pernyataan').value = ''; } catch(e){}
            try { document.getElementById('penanda_pernyataan').value   = 'dr. Andara Dwike, MARS, M.H., FISQua'; } catch(e){}
            try { document.getElementById('jabatan_pernyataan').value   = 'Direktur Utama Rumah Sakit Taman Harapan Baru'; } catch(e){}
            // tugas
            ['penerima_tugas_nama','penerima_tugas_nik','penerima_tugas_jabatan',
             'undangan_dari','nama_kegiatan','hari_tanggal','waktu_acara','tempat_tugas'
            ].forEach(id => { try { document.getElementById(id).value = ''; } catch(e){} });
            try { document.getElementById('pemberi_tugas_nama').value    = 'dr. Andara Dwike, MARS, M.H., FISQua'; } catch(e){}
            try { document.getElementById('pemberi_tugas_jabatan').value = 'Direktur Utama Rumah Sakit Taman Harapan Baru'; } catch(e){}
            // undangan
            ['perihal_undangan','lampiran_undangan','tujuan_nama_undangan','tujuan_alamat_undangan',
             'up_undangan','diundang_keluar','agenda_undangan','hari_tanggal_undangan',
             'tempat_undangan','waktu_mulai_undangan','waktu_selesai_undangan',
             'penanda_undangan','jabatan_undangan'
            ].forEach(id => { try { document.getElementById(id).value=''; } catch(e){} });
            try { document.getElementById('perihal_undangan').value = 'UNDANGAN'; } catch(e){}
        }

        // ── Edit modal ────────────────────────────────────────
        function openEditModal(doc) {
            resetForm();
            const kat = doc.kategori || 'Surat Keluar';
            document.getElementById('edit_id').value          = doc.id;
            document.getElementById('nomor_surat').value      = doc.nomor_surat || '';
            document.getElementById('tanggal_surat').value    = doc.tanggal_surat || '';
            document.getElementById('kategoriSelectKeluar').value = kat;
            document.getElementById('status_tindak_lanjut').value = doc.status_tindak_lanjut || 'Pending';

            toggleMemoFields(kat);

            if (kat === 'Internal Memo') {
                document.getElementById('asal_pengirim_memo').value = doc.asal_pengirim || '';
                document.getElementById('dari').value               = doc.dari          || '';
                document.getElementById('perihal_memo').value       = doc.perihal       || '';
                document.getElementById('isi_surat_memo').value     = doc.isi_surat     || '';
                document.getElementById('penanda_tangan_memo').value= doc.penanda_tangan || '';
                document.getElementById('jabatan_ttd_memo').value   = doc.jabatan_ttd   || '';
                let tArr = [];
                try { tArr = doc.tembusan ? JSON.parse(doc.tembusan) : []; } catch(e){}
                document.getElementById('tembusan_raw').value = tArr.join('\n');
            } else if (kat === 'Surat Edaran') {
                document.getElementById('perihal_edaran').value  = doc.perihal        || '';
                document.getElementById('isi_surat_edaran').value= doc.isi_surat      || '';
                document.getElementById('penanda_edaran').value  = doc.penanda_tangan || '';
                document.getElementById('jabatan_edaran').value  = doc.jabatan_ttd    || '';
            } else if (kat === 'Surat Pernyataan') {
                document.getElementById('isi_surat_pernyataan').value = doc.isi_surat      || '';
                document.getElementById('penanda_pernyataan').value   = doc.penanda_tangan || 'dr. Andara Dwike, MARS, M.H., FISQua';
                document.getElementById('jabatan_pernyataan').value   = doc.jabatan_ttd    || 'Direktur Utama Rumah Sakit Taman Harapan Baru';
            } else if (kat === 'Surat Tugas') {
                document.getElementById('pemberi_tugas_nama').value    = doc.penanda_tangan || 'dr. Andara Dwike, MARS, M.H., FISQua';
                document.getElementById('pemberi_tugas_jabatan').value = doc.jabatan_ttd    || 'Direktur Utama Rumah Sakit Taman Harapan Baru';
                document.getElementById('penerima_tugas_nama').value   = doc.penerima_nama  || '';
                document.getElementById('penerima_tugas_nik').value    = doc.penerima_ktp   || '';
                document.getElementById('penerima_tugas_jabatan').value= doc.jabatan_kiri   || '';
                document.getElementById('undangan_dari').value         = doc.untuk_kuasa    || '';
                document.getElementById('nama_kegiatan').value         = doc.perihal        || '';
                document.getElementById('hari_tanggal').value          = doc.hari_tanggal   || '';
                document.getElementById('waktu_acara').value           = doc.waktu_acara    || '';
                document.getElementById('tempat_tugas').value          = doc.tujuan_alamat  || '';
            } else if (kat === 'Surat Undangan') {
                document.getElementById('perihal_undangan').value        = doc.perihal       || 'UNDANGAN';
                document.getElementById('lampiran_undangan').value       = doc.lampiran      || '';
                document.getElementById('tujuan_nama_undangan').value    = doc.asal_pengirim || '';
                document.getElementById('tujuan_alamat_undangan').value  = doc.tujuan_alamat || '';
                document.getElementById('up_undangan').value             = doc.up_nama       || '';
                document.getElementById('diundang_keluar').value         = doc.untuk_kuasa   || '';
                document.getElementById('agenda_undangan').value         = doc.isi_surat     || '';
                document.getElementById('hari_tanggal_undangan').value   = doc.hari_tanggal  || '';
                document.getElementById('waktu_mulai_undangan').value    = doc.waktu_acara   || '';
                document.getElementById('waktu_selesai_undangan').value  = doc.waktu_selesai || '';
                document.getElementById('tempat_undangan').value         = doc.tujuan_alamat || '';
                document.getElementById('penanda_undangan').value        = doc.penanda_tangan|| '';
                document.getElementById('jabatan_undangan').value        = doc.jabatan_ttd   || '';
            } else {
                document.getElementById('asal_pengirim').value  = doc.asal_pengirim  || '';
                document.getElementById('perihal').value        = doc.perihal        || '';
                document.getElementById('lampiran').value       = doc.lampiran       || '';
                document.getElementById('tujuan_alamat').value  = doc.tujuan_alamat  || '';
                document.getElementById('up_nama').value        = doc.up_nama        || '';
                document.getElementById('ucapan_mitra').value   = doc.ucapan_mitra   || '';
                document.getElementById('isi_surat').value      = doc.isi_surat      || '';
                document.getElementById('penanda_tangan').value = doc.penanda_tangan || '';
                document.getElementById('jabatan_ttd').value    = doc.jabatan_ttd    || '';
            }

            document.getElementById('submitBtn').name = 'edit_surat';
            document.getElementById('submitBtn').textContent = 'Simpan Perubahan';
            document.getElementById('modal-title').textContent = 'Edit ' + kat;

            const modal = document.getElementById('modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // ── Close modal ───────────────────────────────────────
        function closeModal(modalId = 'modal') {
            const el = document.getElementById(modalId);
            if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
        }

        // ── Preview ───────────────────────────────────────────
        function previewSurat() {
            const kat = document.getElementById('kategoriSelectKeluar').value;
            if (kat === 'Internal Memo') {
                const p = new URLSearchParams({
                    no_memo:     document.getElementById('nomor_surat').value,
                    kepada:      document.getElementById('asal_pengirim_memo').value,
                    dari:        document.getElementById('dari').value,
                    perihal:     document.getElementById('perihal_memo').value,
                    tanggal:     document.getElementById('tanggal_surat').value,
                    isi:         document.getElementById('isi_surat_memo').value,
                    nama_ttd:    document.getElementById('penanda_tangan_memo').value,
                    jabatan_ttd: document.getElementById('jabatan_ttd_memo').value,
                    tembusan:    document.getElementById('tembusan_raw').value,
                });
                window.open('cetak_internal_memo.php?' + p.toString(), '_blank');
            } else if (kat === 'Surat Edaran') {
                const p = new URLSearchParams({
                    no_edaran:   document.getElementById('nomor_surat').value,
                    tentang:     document.getElementById('perihal_edaran').value,
                    tanggal:     document.getElementById('tanggal_surat').value,
                    isi:         document.getElementById('isi_surat_edaran').value,
                    nama_ttd:    document.getElementById('penanda_edaran').value,
                    jabatan_ttd: document.getElementById('jabatan_edaran').value,
                });
                window.open('cetak_surat_edaran.php?' + p.toString(), '_blank');
            } else if (kat === 'Surat Pernyataan') {
                const p = new URLSearchParams({
                    no_sp:       document.getElementById('nomor_surat').value,
                    tanggal:     document.getElementById('tanggal_surat').value,
                    isi:         document.getElementById('isi_surat_pernyataan').value,
                    nama_ttd:    document.getElementById('penanda_pernyataan').value,
                    jabatan_ttd: document.getElementById('jabatan_pernyataan').value,
                });
                window.open('cetak_surat_pernyataan.php?' + p.toString(), '_blank');
            } else if (kat === 'Surat Tugas') {
                const p = new URLSearchParams({
                    no_st:            document.getElementById('nomor_surat').value,
                    tanggal:          document.getElementById('tanggal_surat').value,
                    pemberi_nama:     document.getElementById('pemberi_tugas_nama').value,
                    pemberi_jabatan:  document.getElementById('pemberi_tugas_jabatan').value,
                    penerima_nama:    document.getElementById('penerima_tugas_nama').value,
                    penerima_nik:     document.getElementById('penerima_tugas_nik').value,
                    penerima_jabatan: document.getElementById('penerima_tugas_jabatan').value,
                    undangan_dari:    document.getElementById('undangan_dari').value,
                    nama_kegiatan:    document.getElementById('nama_kegiatan').value,
                    hari_tanggal:     document.getElementById('hari_tanggal').value,
                    waktu_acara:      document.getElementById('waktu_acara').value,
                    tempat:           document.getElementById('tempat_tugas').value,
                });
                window.open('cetak_surat_tugas.php?' + p.toString(), '_blank');
            } else if (kat === 'Surat Undangan') {
                const p = new URLSearchParams({
                    no_undangan:   document.getElementById('nomor_surat').value,
                    tanggal:       document.getElementById('tanggal_surat').value,
                    perihal:       document.getElementById('perihal_undangan').value,
                    lampiran:      document.getElementById('lampiran_undangan').value,
                    tujuan_nama:   document.getElementById('tujuan_nama_undangan').value,
                    tujuan_alamat: document.getElementById('tujuan_alamat_undangan').value,
                    up_nama:       document.getElementById('up_undangan').value,
                    diundang:      document.getElementById('diundang_keluar').value,
                    acara:         document.getElementById('agenda_undangan').value,
                    hari_tanggal:  document.getElementById('hari_tanggal_undangan').value,
                    tempat:        document.getElementById('tempat_undangan').value,
                    waktu_mulai:   document.getElementById('waktu_mulai_undangan').value,
                    waktu_selesai: document.getElementById('waktu_selesai_undangan').value,
                    agenda:        document.getElementById('agenda_undangan').value,
                    nama_ttd:      document.getElementById('penanda_undangan').value,
                    jabatan_ttd:   document.getElementById('jabatan_undangan').value,
                });
                window.open('cetak_surat_undangan.php?' + p.toString(), '_blank');
            } else {
                const p = new URLSearchParams({
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
                window.open('cetak_surat_keluar.php?' + p.toString(), '_blank');
            }
        }
    </script>
</body>
</html>
