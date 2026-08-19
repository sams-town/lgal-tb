<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
if (!hasPermission('sop_view')) {
    header("Location: dashboard.php");
    exit;
}

// --- Backend Logic (CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nik                   = $_POST['nik'];
        $nama                  = $_POST['nama'];
        $unit                  = $_POST['unit'];
        $kategori_bagian       = $_POST['kategori_bagian'] ?? null;
        $jabatan               = $_POST['jabatan'];
        $atasan_langsung       = $_POST['atasan_langsung'] ?? null;
        $atasan_tidak_langsung = $_POST['atasan_tidak_langsung'] ?? null;
        $tanggal_bergabung     = !empty($_POST['tanggal_bergabung']) ? $_POST['tanggal_bergabung'] : null;
        $user_id               = !empty($_POST['user_id'])   ? (int)$_POST['user_id']   : null;
        $atasan_id             = !empty($_POST['atasan_id']) ? (int)$_POST['atasan_id'] : null;

        $stmt = $pdo->prepare("INSERT INTO kpi_karyawan (nik, nama, unit, kategori_bagian, jabatan, atasan_langsung, atasan_tidak_langsung, tanggal_bergabung, user_id, atasan_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nik, $nama, $unit, $kategori_bagian, $jabatan, $atasan_langsung, $atasan_tidak_langsung, $tanggal_bergabung, $user_id, $atasan_id]);
        header("Location: sop_kpi_karyawan.php?success=add");
        exit;
    }
    elseif ($action === 'edit') {
        $id                    = $_POST['id'];
        $nik                   = $_POST['nik'];
        $nama                  = $_POST['nama'];
        $unit                  = $_POST['unit'];
        $kategori_bagian       = $_POST['kategori_bagian'] ?? null;
        $jabatan               = $_POST['jabatan'];
        $atasan_langsung       = $_POST['atasan_langsung'] ?? null;
        $atasan_tidak_langsung = $_POST['atasan_tidak_langsung'] ?? null;
        $tanggal_bergabung     = !empty($_POST['tanggal_bergabung']) ? $_POST['tanggal_bergabung'] : null;
        $status                = $_POST['status'];
        $user_id               = !empty($_POST['user_id'])   ? (int)$_POST['user_id']   : null;
        $atasan_id             = !empty($_POST['atasan_id']) ? (int)$_POST['atasan_id'] : null;

        $stmt = $pdo->prepare("UPDATE kpi_karyawan SET nik=?, nama=?, unit=?, kategori_bagian=?, jabatan=?, atasan_langsung=?, atasan_tidak_langsung=?, tanggal_bergabung=?, status=?, user_id=?, atasan_id=? WHERE id=?");
        $stmt->execute([$nik, $nama, $unit, $kategori_bagian, $jabatan, $atasan_langsung, $atasan_tidak_langsung, $tanggal_bergabung, $status, $user_id, $atasan_id, $id]);
        header("Location: sop_kpi_karyawan.php?success=edit");
        exit;
    }
    elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM kpi_karyawan WHERE id=?");
        $stmt->execute([$_POST['id']]);
        header("Location: sop_kpi_karyawan.php?success=delete");
        exit;
    } elseif ($action === 'sync_komite') {
        // Ensure tenaga_medis_id column exists (in case session blocked database.php from altering)
        try {
            $colExists = $pdo->query("SHOW COLUMNS FROM kpi_karyawan LIKE 'tenaga_medis_id'")->rowCount();
            if ($colExists == 0) {
                $pdo->exec("ALTER TABLE kpi_karyawan ADD COLUMN tenaga_medis_id INT NULL AFTER status");
                $pdo->exec("ALTER TABLE kpi_karyawan ADD KEY tenaga_medis_id (tenaga_medis_id)");
            }
        } catch (Exception $ex) {}
        
        // Sync tenaga_medis to kpi_karyawan
        $stmtKomite = $pdo->query("SELECT id, nama_lengkap, unit_ruangan, jabatan_keperawatan, tipe_form FROM tenaga_medis");
        $komiteList = $stmtKomite->fetchAll();
        
        $synced = 0;
        foreach($komiteList as $k) {
            // Check if exists
            $stmtCheck = $pdo->prepare("SELECT id FROM kpi_karyawan WHERE tenaga_medis_id = ? OR nik = ? OR nama = ?");
            // Generating a fake NIK if syncing
            $nik = 'KOM-'.str_pad($k['id'], 3, '0', STR_PAD_LEFT);
            $stmtCheck->execute([$k['id'], $nik, $k['nama_lengkap']]);
            
            if ($stmtCheck->rowCount() == 0) {
                // Insert new
                $unit = $k['unit_ruangan'] ?? 'Belum Ditentukan';
                $jabatan = $k['jabatan_keperawatan'];
                if (!$jabatan) {
                    if ($k['tipe_form'] == 'komite-medik') $jabatan = 'Dokter';
                    elseif ($k['tipe_form'] == 'komite-keperawatan') $jabatan = 'Perawat';
                    else $jabatan = 'Tenaga Kesehatan';
                }
                
                $stmtInsert = $pdo->prepare("INSERT INTO kpi_karyawan (nik, nama, unit, jabatan, tenaga_medis_id) VALUES (?, ?, ?, ?, ?)");
                $stmtInsert->execute([$nik, $k['nama_lengkap'], $unit, $jabatan, $k['id']]);
                $synced++;
            } else {
                // Update existing if tenaga_medis_id is null
                $existing = $stmtCheck->fetch();
                $pdo->prepare("UPDATE kpi_karyawan SET tenaga_medis_id = ? WHERE id = ? AND tenaga_medis_id IS NULL")->execute([$k['id'], $existing['id']]);
            }
        }
        
        header("Location: sop_kpi_karyawan.php?success=sync&count=".$synced);
        exit;
    }
}

// Fetch daftar users untuk dropdown link akun
$usersList = [];
try {
    $stmtU = $pdo->query("SELECT id, nama, nama_role FROM users ORDER BY nama ASC");
    $usersList = $stmtU->fetchAll();
} catch (Exception $e) { $usersList = []; }

// Fetch all data
$stmt = $pdo->query("
    SELECT k.*, 
           t.spesialis, t.status_kepegawaian, t.nomor_pkwt, t.no_sip, t.no_str, t.masa_berlaku_sip_akhir
    FROM kpi_karyawan k
    LEFT JOIN tenaga_medis t ON k.tenaga_medis_id = t.id
    ORDER BY k.id DESC
");
$karyawanList = $stmt->fetchAll();

$karyawanNonKomite = [];
$karyawanKomite = [];
foreach ($karyawanList as $k) {
    if (empty($k['tenaga_medis_id'])) {
        $karyawanNonKomite[] = $k;
    } else {
        $karyawanKomite[] = $k;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Karyawan KPI - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- DataTables & jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        .dataTables_wrapper .dataTables_filter input { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 4px 8px; outline: none; }
        .dataTables_wrapper .dataTables_filter input:focus { border-color: #0d9488; }
        .dataTables_wrapper .dataTables_length select { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 4px 8px; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 flex flex-col relative h-screen overflow-hidden">
        <?php include 'includes/header.php'; ?>
        
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Data Karyawan</h1>
                        <p class="text-gray-500 mt-1">Daftar Karyawan untuk Penilaian Performa</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="export_handler.php?action=export_data&module=kpi_karyawan" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                            <i data-lucide="download" class="w-4 h-4"></i> Export
                        </a>
                        <button onclick="openModal('importModal')" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 px-4 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                            <i data-lucide="upload" class="w-4 h-4"></i> Import
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Tarik data tenaga medis dari tabel Komite?');">
                            <input type="hidden" name="action" value="sync_komite">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Sinkronisasi
                            </button>
                        </form>
                        <button onclick="openModal('modalAdd')" class="bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2.5 px-4 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Karyawan
                        </button>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span>
                        <?php 
                        if($_GET['success'] == 'add') echo "Data karyawan berhasil ditambahkan!";
                        elseif($_GET['success'] == 'edit') echo "Data karyawan berhasil diperbarui!";
                        elseif($_GET['success'] == 'delete') echo "Data karyawan berhasil dihapus!";
                        elseif($_GET['success'] == 'sync') echo "Sinkronisasi selesai! " . ($_GET['count'] ?? 0) . " data baru ditarik dari Komite.";
                        ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['import_success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span><?= htmlspecialchars($_SESSION['import_success']) ?></span>
                </div>
                <?php unset($_SESSION['import_success']); endif; ?>

                <?php if (isset($_SESSION['import_error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                    <span><?= htmlspecialchars($_SESSION['import_error']) ?></span>
                </div>
                <?php unset($_SESSION['import_error']); endif; ?>

                <!-- Tabs -->
                <div class="flex gap-4 border-b border-gray-200">
                    <button onclick="switchTab('non-komite')" id="btn-non-komite" class="px-4 py-3 font-semibold text-teal-600 border-b-2 border-teal-600 transition-all">Non-Komite (Manual)</button>
                    <button onclick="switchTab('komite')" id="btn-komite" class="px-4 py-3 font-semibold text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition-all">Komite Medis (Sinkronisasi)</button>
                </div>

                <!-- Table Non-Komite -->
                <div id="tab-non-komite" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden p-6 block">
                    <div class="overflow-x-auto">
                        <table id="tableNonKomite" class="w-full text-left" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">NIK</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Kategori Bagian</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Jabatan</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Atasan Langsung</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Atasan Tidak Langsung</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal Bergabung</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($karyawanNonKomite as $k): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-3">
                                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($k['nama']) ?></div>
                                    </td>
                                    <td class="text-sm font-medium text-gray-900 py-3"><?= htmlspecialchars($k['nik']) ?></td>
                                    <td class="text-sm text-gray-600 py-3"><?= htmlspecialchars($k['unit']) ?></td>
                                    <td class="text-sm text-gray-600 py-3"><?= htmlspecialchars($k['kategori_bagian'] ?? '-') ?></td>
                                    <td class="text-sm text-gray-600 py-3"><?= htmlspecialchars($k['jabatan']) ?></td>
                                    <td class="text-sm text-gray-600 py-3"><?= htmlspecialchars($k['atasan_langsung'] ?? '-') ?></td>
                                    <td class="text-sm text-gray-600 py-3"><?= htmlspecialchars($k['atasan_tidak_langsung'] ?? '-') ?></td>
                                    <td class="text-sm text-gray-600 py-3">
                                        <?= !empty($k['tanggal_bergabung']) ? date('d/m/Y', strtotime($k['tanggal_bergabung'])) : '-' ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if($k['status'] === 'Aktif'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right py-3">
                                        <button onclick='editData(<?= json_encode($k) ?>)' class="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition-colors inline-flex items-center" title="Edit">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors inline-flex items-center ml-1" title="Hapus">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Table Komite -->
                <div id="tab-komite" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden p-6 hidden">
                    <div class="overflow-x-auto">
                        <table id="tableKomite" class="w-full text-left" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">ID / NIK</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama & Spesialis</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit & Jabatan</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Detail Medis</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Status KPI</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($karyawanKomite as $k): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="text-sm font-medium text-gray-900 py-3"><?= htmlspecialchars($k['nik']) ?></td>
                                    <td class="py-3">
                                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($k['nama']) ?></div>
                                        <?php if(!empty($k['spesialis'])): ?>
                                            <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($k['spesialis']) ?></div>
                                        <?php endif; ?>
                                        <div class="text-xs text-blue-600 font-medium mt-1">Sinkronisasi Komite</div>
                                    </td>
                                    <td class="py-3">
                                        <div class="text-sm text-gray-800 font-medium"><?= htmlspecialchars($k['jabatan']) ?></div>
                                        <div class="text-xs text-gray-600 mt-0.5"><?= htmlspecialchars($k['unit']) ?></div>
                                    </td>
                                    <td class="py-3">
                                        <div class="text-xs text-gray-700 mb-1"><span class="font-semibold">Pegawai:</span> <?= htmlspecialchars($k['status_kepegawaian'] ?? '-') ?></div>
                                        <div class="text-xs text-gray-700 mb-1"><span class="font-semibold">STR:</span> <?= htmlspecialchars($k['no_str'] ?? '-') ?></div>
                                        <div class="text-xs text-gray-700"><span class="font-semibold">SIP:</span> <?= htmlspecialchars($k['no_sip'] ?? '-') ?></div>
                                        <?php if(!empty($k['masa_berlaku_sip_akhir'])): ?>
                                            <div class="text-xs text-emerald-600 mt-0.5">(s/d <?= date('d M Y', strtotime($k['masa_berlaku_sip_akhir'])) ?>)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if($k['status'] === 'Aktif'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right py-3">
                                        <button onclick='editData(<?= json_encode($k) ?>)' class="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition-colors inline-flex items-center" title="Edit">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors inline-flex items-center ml-1" title="Hapus">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Tambah -->
    <div id="modalAdd" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-2xl p-6 m-4 relative shadow-xl max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Tambah Karyawan</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" name="nama" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">NIK <span class="text-red-500">*</span></label>
                            <input type="text" name="nik" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
                            <input type="text" name="unit" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Kategori Bagian</label>
                            <input type="text" name="kategori_bagian" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none" placeholder="Contoh: Keperawatan, Farmasi...">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Jabatan <span class="text-red-500">*</span></label>
                            <input type="text" name="jabatan" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Bergabung</label>
                            <input type="date" name="tanggal_bergabung" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Atasan Langsung</label>
                            <input type="text" name="atasan_langsung" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none" placeholder="Nama atasan langsung">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Atasan Tidak Langsung</label>
                            <input type="text" name="atasan_tidak_langsung" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none" placeholder="Nama atasan tidak langsung">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Link Akun Login (User)</label>
                        <select name="user_id" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none bg-white text-sm">
                            <option value="">-- Tidak dihubungkan --</option>
                            <?php foreach ($usersList as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['nama'] . ' (' . $u['nama_role'] . ')') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Hubungkan agar karyawan bisa melihat nilainya sendiri.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Atasan (Penilai)</label>
                        <select name="atasan_id" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none bg-white text-sm">
                            <option value="">-- Tidak ada atasan --</option>
                            <?php foreach ($usersList as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['nama'] . ' (' . $u['nama_role'] . ')') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Atasan yang berhak mengisi penilaian harian karyawan ini.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalAdd')" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-medium">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="modalEdit" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-2xl p-6 m-4 relative shadow-xl max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Edit Karyawan</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" name="nama" id="edit_nama" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">NIK <span class="text-red-500">*</span></label>
                            <input type="text" name="nik" id="edit_nik" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
                            <input type="text" name="unit" id="edit_unit" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Kategori Bagian</label>
                            <input type="text" name="kategori_bagian" id="edit_kategori_bagian" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none" placeholder="Contoh: Keperawatan, Farmasi...">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Jabatan <span class="text-red-500">*</span></label>
                            <input type="text" name="jabatan" id="edit_jabatan" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Bergabung</label>
                            <input type="date" name="tanggal_bergabung" id="edit_tanggal_bergabung" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Atasan Langsung</label>
                            <input type="text" name="atasan_langsung" id="edit_atasan_langsung" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none" placeholder="Nama atasan langsung">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Atasan Tidak Langsung</label>
                            <input type="text" name="atasan_tidak_langsung" id="edit_atasan_tidak_langsung" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none" placeholder="Nama atasan tidak langsung">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                        <select name="status" id="edit_status" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none bg-white">
                            <option value="Aktif">Aktif</option>
                            <option value="Nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Link Akun Login (User)</label>
                        <select name="user_id" id="edit_user_id" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none bg-white text-sm">
                            <option value="">-- Tidak dihubungkan --</option>
                            <?php foreach ($usersList as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['nama'] . ' (' . $u['nama_role'] . ')') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Hubungkan agar karyawan bisa melihat nilainya sendiri.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Atasan (Penilai)</label>
                        <select name="atasan_id" id="edit_atasan_id" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none bg-white text-sm">
                            <option value="">-- Tidak ada atasan --</option>
                            <?php foreach ($usersList as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['nama'] . ' (' . $u['nama_role'] . ')') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Atasan yang berhak mengisi penilaian harian karyawan ini.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalEdit')" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-medium">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Import -->
    <div id="importModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 m-4 relative shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Import Data Karyawan</h2>
            <form action="export_handler.php?action=import_data&module=kpi_karyawan" method="POST" enctype="multipart/form-data">
                <div class="space-y-4">
                    <div class="p-4 bg-blue-50 text-blue-800 rounded-xl text-sm mb-4">
                        <p class="font-semibold mb-1">Panduan Import:</p>
                        <ul class="list-disc ml-5 space-y-1">
                            <li>Gunakan format file CSV.</li>
                            <li>Kolom harus berurutan: NIK, Nama Karyawan, Unit, Jabatan, Status.</li>
                            <li><a href="export_handler.php?action=download_template&module=kpi_karyawan" class="text-blue-600 hover:underline font-medium">Download Template CSV</a></li>
                        </ul>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih File CSV</label>
                        <input type="file" name="csv_file" accept=".csv" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('importModal')" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded-xl hover:bg-amber-600 font-medium flex items-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4"></i> Upload & Import
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        $(document).ready(function() {
            $('#tableNonKomite, #tableKomite').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                },
                "pageLength": 10
            });
        });

        function switchTab(tabName) {
            // Hide all tabs
            document.getElementById('tab-non-komite').classList.add('hidden');
            document.getElementById('tab-non-komite').classList.remove('block');
            document.getElementById('tab-komite').classList.add('hidden');
            document.getElementById('tab-komite').classList.remove('block');
            
            // Show selected tab
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            document.getElementById('tab-' + tabName).classList.add('block');
            
            // Update button styles
            document.getElementById('btn-non-komite').className = "px-4 py-3 font-semibold transition-all " + (tabName === 'non-komite' ? 'text-teal-600 border-b-2 border-teal-600' : 'text-gray-500 border-b-2 border-transparent hover:text-gray-700');
            document.getElementById('btn-komite').className = "px-4 py-3 font-semibold transition-all " + (tabName === 'komite' ? 'text-teal-600 border-b-2 border-teal-600' : 'text-gray-500 border-b-2 border-transparent hover:text-gray-700');
        }

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.getElementById(id).classList.add('flex');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }
        function editData(data) {
            document.getElementById('edit_id').value                    = data.id;
            document.getElementById('edit_nama').value                  = data.nama;
            document.getElementById('edit_nik').value                   = data.nik;
            document.getElementById('edit_unit').value                  = data.unit;
            document.getElementById('edit_kategori_bagian').value       = data.kategori_bagian  || '';
            document.getElementById('edit_jabatan').value               = data.jabatan;
            document.getElementById('edit_atasan_langsung').value       = data.atasan_langsung       || '';
            document.getElementById('edit_atasan_tidak_langsung').value = data.atasan_tidak_langsung || '';
            document.getElementById('edit_tanggal_bergabung').value     = data.tanggal_bergabung     || '';
            document.getElementById('edit_status').value                = data.status;
            document.getElementById('edit_user_id').value               = data.user_id   || '';
            document.getElementById('edit_atasan_id').value             = data.atasan_id || '';
            openModal('modalEdit');
        }
    </script>
</body>
</html>
