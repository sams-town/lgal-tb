<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Check access permission for Legal
if (!isUserLegalOrAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

// Handle status and reject reason update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $pks_id = (int)$_POST['pks_id'];
    $new_status = $_POST['status'] ?? 'Dalam Proses';
    $reject_reason = $_POST['reject_reason'] ?? null;
    
    try {
        $stmt = $pdo->prepare("UPDATE pengajuan_pks SET status = ?, reject_reason = ? WHERE id = ?");
        $stmt->execute([$new_status, $reject_reason, $pks_id]);
        $_SESSION['pks_success'] = "Status pengajuan berhasil diperbarui!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal memperbarui status: " . $e->getMessage();
    }
    header("Location: pks.php");
    exit;
}

// Handle form submission for adding new PKS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pks'])) {
    $tanggal_pengajuan = $_POST['tanggal_pengajuan'] ?? null;
    $unit_pengusul = $_POST['unit_pengusul'] ?? null;
    $jenis_kerjasama = $_POST['jenis_kerjasama'] ?? null;
    $objek_kerjasama = $_POST['objek_kerjasama'] ?? null;
    $analisa_alasan = $_POST['analisa_alasan'] ?? null;
    
    // Process calon mitra
    $calon_mitra = [];
    if (isset($_POST['nama_mitra']) && is_array($_POST['nama_mitra'])) {
        foreach ($_POST['nama_mitra'] as $index => $nama) {
            if (!empty($nama)) {
                $calon_mitra[] = [
                    'nama' => $nama,
                    'narahubung' => $_POST['narahubung'][$index] ?? ''
                ];
            }
        }
    }
    $calon_mitra_json = json_encode($calon_mitra);
    
    $keunggulan_mitra = $_POST['keunggulan_mitra'] ?? null;
    $kekurangan_mitra = $_POST['kekurangan_mitra'] ?? null;
    $biaya = $_POST['biaya'] ?? null;
    $referensi_kerjasama = $_POST['referensi_kerjasama'] ?? null;
    $capaian_mutu = $_POST['capaian_mutu'] ?? null;
    $rekomendasi_pengadaan = $_POST['rekomendasi_pengadaan'] ?? null;
    $rekomendasi_legal = $_POST['rekomendasi_legal'] ?? null;
    
    $nomor_dokumen = $_POST['nomor_dokumen'] ?? null;
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? null;
    $tanggal_berakhir = $_POST['tanggal_berakhir'] ?? null;
    
    $file_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/pks/';
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
        // Initialize step status for new PKS
        $initialStepStatus = [
            'km' => 'pending',
            'legal' => 'pending',
            'sekretariat' => 'pending',
            'dk' => 'pending',
            'dsdml' => 'pending',
            'du' => 'pending'
        ];
        $step_status_json = json_encode($initialStepStatus);
        
        $stmt = $pdo->prepare("
            INSERT INTO pengajuan_pks (
                tanggal_pengajuan, unit_pengusul, jenis_kerjasama, objek_kerjasama, 
                analisa_alasan, calon_mitra, keunggulan_mitra, kekurangan_mitra, 
                biaya, referensi_kerjasama, capaian_mutu, rekomendasi_pengadaan, 
                rekomendasi_legal, nomor_dokumen, tanggal_mulai, tanggal_berakhir, 
                file_path, step_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tanggal_pengajuan, $unit_pengusul, $jenis_kerjasama, $objek_kerjasama,
            $analisa_alasan, $calon_mitra_json, $keunggulan_mitra, $kekurangan_mitra,
            $biaya, $referensi_kerjasama, $capaian_mutu, $rekomendasi_pengadaan,
            $rekomendasi_legal, $nomor_dokumen, $tanggal_mulai, $tanggal_berakhir,
            $file_path, $step_status_json
        ]);
        
        // Send notification for new PKS
        notifyByPermission(
            "Pengajuan PKS Baru",
            "Ada pengajuan PKS baru untuk kerjasama $jenis_kerjasama dengan objek: $objek_kerjasama dari unit $unit_pengusul.",
            "legal"
        );
        
        $_SESSION['pks_success'] = "Pengajuan PKS berhasil disimpan!";
        
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menyimpan data: " . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header("Location: pks.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    if (!isUserLegalOrAdmin()) {
        $_SESSION['pks_error'] = "Anda tidak memiliki akses untuk menghapus data ini!";
        header("Location: pks.php");
        exit;
    }
    $id = (int)$_GET['delete'];
    try {
        // Get file path before deleting
        $stmt = $pdo->prepare("SELECT file_path FROM pengajuan_pks WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM pengajuan_pks WHERE id = ?");
        $stmt->execute([$id]);

        // Delete file if exists
        if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        $_SESSION['pks_success'] = "Data PKS berhasil dihapus!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: pks.php");
    exit;
}

// Capture filter query and status filter
$filter_jenis = isset($_GET['jenis_kerjasama']) ? trim($_GET['jenis_kerjasama']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';

// Get PKS documents
try {
    $sql = "SELECT * FROM pengajuan_pks WHERE 1=1";
    $params = [];
    
    if (!empty($filter_jenis)) {
        $sql .= " AND jenis_kerjasama = :jenis_kerjasama";
        $params['jenis_kerjasama'] = $filter_jenis;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND status = :status_filter";
        $params['status_filter'] = $status_filter;
    }
    
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Calculate stats for PKS
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengajuan_pks");
    $totalDocs = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_pks WHERE jenis_kerjasama = ?");
    $stmt->execute(['Klinis']);
    $aktif = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_pks WHERE jenis_kerjasama = ?");
    $stmt->execute(['Non Klinis']);
    $mendekati = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalDocs = 0;
    $aktif = 0;
    $mendekati = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perjanjian Kerjasama (PKS) - RS Taman Harapan Baru</title>
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
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Perjanjian Kerjasama (PKS)</h1>
                        <p class="text-gray-600 mt-2">Manajemen seluruh perjanjian kerjasama rumah sakit dengan mitra dan pihak ketiga</p>
                    </div>
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

                <!-- Export/Import Buttons -->
                <div class="flex items-center gap-3">
                    <a href="export_handler.php?action=export_data&module=pks" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        📊
                        <span>Export Excel</span>
                    </a>
                    <button onclick="openImportModal()" class="flex items-center gap-2 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        📤
                        <span>Import Excel</span>
                    </button>
                    <a href="export_handler.php?action=download_template&module=pks" class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                        📄
                        <span>Download Template</span>
                    </a>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative cursor-pointer hover:bg-gray-50 transition-colors" onclick="toggleFilterDropdown(event)">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1 flex items-center gap-1">
                                    <span>Total Pengajuan</span>
                                    <span class="text-xs">▼</span>
                                </p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                        
                        <!-- Dropdown Menu for filtering -->
                        <div id="filterDropdown" class="hidden absolute left-6 top-20 bg-white border border-gray-200 rounded-xl shadow-lg z-10 w-48 py-2" onclick="event.stopPropagation()">
                            <a href="pks.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 font-medium">Semua Status</a>
                            <a href="pks.php?status_filter=Dalam+Proses" class="block px-4 py-2 text-sm text-amber-700 hover:bg-amber-50">Dalam Proses</a>
                            <a href="pks.php?status_filter=Diterima" class="block px-4 py-2 text-sm text-emerald-700 hover:bg-emerald-50">Diterima</a>
                            <a href="pks.php?status_filter=Ditolak" class="block px-4 py-2 text-sm text-red-700 hover:bg-red-50">Ditolak</a>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Klinis</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $aktif; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">🏥</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Non Klinis</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $mendekati; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">🏢</div>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <!-- Search Bar -->
                    <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
                        <div class="flex flex-col md:flex-row md:items-center gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Daftar Pengajuan PKS</h2>
                                <p class="text-xs text-gray-500 mt-1">Total: <?php echo count($documents); ?> dokumen ditemukan</p>
                            </div>
                            <form method="GET" class="flex items-center gap-2">
                                <?php if (!empty($status_filter)): ?>
                                    <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                                <?php endif; ?>
                                <div class="relative w-64">
                                    <select 
                                        name="jenis_kerjasama" 
                                        onchange="this.form.submit()"
                                        class="w-full pl-10 pr-8 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:border-emerald-500 transition-all appearance-none cursor-pointer"
                                    >
                                        <option value="">-- Semua Jenis Kerjasama --</option>
                                        <option value="Klinis" <?php echo $filter_jenis === 'Klinis' ? 'selected' : ''; ?>>Klinis</option>
                                        <option value="Non Klinis" <?php echo $filter_jenis === 'Non Klinis' ? 'selected' : ''; ?>>Non Klinis</option>
                                    </select>
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">📁</span>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">▼</span>
                                </div>
                                <?php if (!empty($filter_jenis) || !empty($status_filter)): ?>
                                    <a href="pks.php" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl transition-colors">
                                        Reset
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal Pengajuan</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Unit Pengusul</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Jenis Kerjasama</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Objek Kerjasama</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Calon Mitra</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada pengajuan kerjasama yang tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $index => $doc): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo formatDate($doc['tanggal_pengajuan'] ?? null); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo htmlspecialchars($doc['unit_pengusul'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php $jenis_kerjasama = $doc['jenis_kerjasama'] ?? '-'; ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $jenis_kerjasama === 'Klinis' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-blue-100 text-blue-800 border-blue-200'; ?>">
                                                    <?php echo htmlspecialchars($jenis_kerjasama); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 max-w-xs">
                                                <p class="text-sm truncate" title="<?php echo htmlspecialchars($doc['objek_kerjasama'] ?? '-'); ?>"><?php echo htmlspecialchars($doc['objek_kerjasama'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 max-w-xs">
                                                <?php 
                                                $calonMitra = json_decode($doc['calon_mitra'] ?? '[]', true);
                                                if (!empty($calonMitra)) {
                                                    $mitraNames = array_column($calonMitra, 'nama');
                                                    echo '<p class="text-sm truncate" title="' . htmlspecialchars(implode(', ', $mitraNames)) . '">' . htmlspecialchars(implode(', ', $mitraNames)) . '</p>';
                                                } else {
                                                    echo '<span class="text-gray-400 text-sm">-</span>';
                                                }
                                                ?>
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
                                                <form method="POST" action="pks.php" id="statusForm_<?php echo $doc['id']; ?>" class="inline-block">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="pks_id" value="<?php echo $doc['id']; ?>">
                                                    <select 
                                                        name="status" 
                                                        onchange="handleStatusChange(<?php echo $doc['id']; ?>, this.value)"
                                                        class="px-2 py-1 rounded-full text-xs font-semibold border cursor-pointer focus:outline-none 
                                                            <?php 
                                                            $currentStatus = $doc['status'] ?? 'Dalam Proses';
                                                            if ($currentStatus === 'Diterima') echo 'bg-emerald-100 text-emerald-800 border-emerald-200';
                                                            elseif ($currentStatus === 'Ditolak') echo 'bg-red-100 text-red-800 border-red-200';
                                                            else echo 'bg-amber-100 text-amber-800 border-amber-200';
                                                            ?>"
                                                    >
                                                        <option value="Dalam Proses" <?php echo $currentStatus === 'Dalam Proses' ? 'selected' : ''; ?>>Dalam Proses</option>
                                                        <option value="Diterima" <?php echo $currentStatus === 'Diterima' ? 'selected' : ''; ?>>Diterima</option>
                                                        <option value="Ditolak" <?php echo $currentStatus === 'Ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                                                    </select>
                                                    <input type="hidden" name="reject_reason" id="rejectReason_<?php echo $doc['id']; ?>" value="<?php echo htmlspecialchars($doc['reject_reason'] ?? ''); ?>">
                                                </form>
                                                <?php if (($doc['status'] ?? '') === 'Ditolak' && !empty($doc['reject_reason'])): ?>
                                                    <p class="text-xs text-red-500 mt-1 font-medium max-w-[150px] break-words" title="<?php echo htmlspecialchars($doc['reject_reason']); ?>">
                                                        Ket: <?php echo htmlspecialchars($doc['reject_reason']); ?>
                                                    </p>
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
                                                    <?php if (isUserLegalOrAdmin()): ?>
                                                        <a href="pks.php?delete=<?php echo $doc['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
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

    <!-- Modal PKS -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">FORMULIR PENGAJUAN KERJASAMA</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                <!-- Bagian 1: Informasi Umum -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Informasi Umum</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hari, Tanggal, Tahun</label>
                            <input type="date" name="tanggal_pengajuan" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit/Departemen Pengusul</label>
                            <input type="text" name="unit_pengusul" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kerjasama</label>
                            <select name="jenis_kerjasama" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="Klinis">Klinis</option>
                                <option value="Non Klinis">Non Klinis</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Objek Kerjasama</label>
                            <input type="text" name="objek_kerjasama" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                </div>

                <!-- Bagian 2: Diisi oleh Unit Pengusul -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Diisi oleh Unit/Departemen Pengusul/Pengguna</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Analisa dasar/alasan pengajuan kerjasama (disesuaikan dengan kebutuhan, rencana dan budget)</label>
                        <textarea name="analisa_alasan" required rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    </div>

                    <!-- Calon Mitra -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Usulan calon mitra kerjasama (minimal 3 mitra kerjasama)</label>
                        <div id="mitra-container" class="space-y-3">
                            <div class="mitra-item grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Nama Calon Mitra</label>
                                    <input type="text" name="nama_mitra[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Narahubung</label>
                                    <input type="text" name="narahubung[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                </div>
                            </div>
                            <div class="mitra-item grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Nama Calon Mitra</label>
                                    <input type="text" name="nama_mitra[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Narahubung</label>
                                    <input type="text" name="narahubung[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                </div>
                            </div>
                            <div class="mitra-item grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Nama Calon Mitra</label>
                                    <input type="text" name="nama_mitra[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Narahubung</label>
                                    <input type="text" name="narahubung[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="addMitra()" class="mt-2 text-emerald-600 hover:text-emerald-700 text-sm font-medium flex items-center gap-1">
                            <span>+</span> Tambah Mitra
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Keunggulan calon mitra kerjasama (dijabarkan)</label>
                        <textarea name="keunggulan_mitra" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kekurangan calon mitra kerjasama (dijabarkan)</label>
                        <textarea name="kekurangan_mitra" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Biaya-biaya</label>
                        <textarea name="biaya" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Referensi kerjasama (calon mitra kerjasama sudah melakukan kerjasama dengan perusahaan apa saja)</label>
                        <textarea name="referensi_kerjasama" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Capaian Mutu Kerjasama</label>
                        <textarea name="capaian_mutu" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    </div>
                </div>

                <!-- Bagian 3: Diisi oleh Unit Terkait -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Diisi oleh Unit Terkait</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hasil rekomendasi Bagian Pengadaan</label>
                        <textarea name="rekomendasi_pengadaan" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hasil rekomendasi Bagian Legal</label>
                        <textarea name="rekomendasi_legal" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    </div>
                </div>

                <!-- Informasi Dokumen Tambahan -->
                <div class="space-y-4 border-t pt-4">
                    <h3 class="text-lg font-semibold text-gray-800">Informasi Dokumen (Opsional)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Dokumen</label>
                            <input type="text" name="nomor_dokumen" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai Kerjasama</label>
                            <input type="date" name="tanggal_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Berakhir Kerjasama</label>
                            <input type="date" name="tanggal_berakhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas Softcopy (PDF)</label>
                        <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_pks" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addMitra() {
            const container = document.getElementById('mitra-container');
            const item = document.createElement('div');
            item.className = 'mitra-item grid grid-cols-1 md:grid-cols-2 gap-4 mt-3';
            item.innerHTML = `
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Nama Calon Mitra</label>
                    <input type="text" name="nama_mitra[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Narahubung</label>
                    <input type="text" name="narahubung[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
            `;
            container.appendChild(item);
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

        function handleStatusChange(pksId, value) {
            if (value === 'Ditolak') {
                const reason = prompt('Masukkan alasan penolakan:', document.getElementById('rejectReason_' + pksId).value);
                if (reason === null) {
                    // Cancelled, reset select to previous value
                    location.reload();
                    return;
                }
                document.getElementById('rejectReason_' + pksId).value = reason;
            }
            document.getElementById('statusForm_' + pksId).submit();
        }

        function toggleFilterDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('filterDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            const dropdown = document.getElementById('filterDropdown');
            if (dropdown) {
                dropdown.classList.add('hidden');
            }
        });
    </script>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Import Excel (CSV)</h2>
                <button onclick="closeImportModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" action="export_handler.php?action=import_data&module=pks" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih File CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                    <p class="text-xs text-gray-500 mt-2">Pastikan format file sesuai dengan template. Tanggal dapat menggunakan format DD/MM/YYYY atau YYYY-MM-DD.</p>
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
</body>
</html>
