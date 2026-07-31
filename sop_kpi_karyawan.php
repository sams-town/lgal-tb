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
        $nik = $_POST['nik'];
        $nama = $_POST['nama'];
        $unit = $_POST['unit'];
        $jabatan = $_POST['jabatan'];
        
        $stmt = $pdo->prepare("INSERT INTO kpi_karyawan (nik, nama, unit, jabatan) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nik, $nama, $unit, $jabatan]);
        header("Location: sop_kpi_karyawan.php?success=add");
        exit;
    }
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $nik = $_POST['nik'];
        $nama = $_POST['nama'];
        $unit = $_POST['unit'];
        $jabatan = $_POST['jabatan'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE kpi_karyawan SET nik=?, nama=?, unit=?, jabatan=?, status=? WHERE id=?");
        $stmt->execute([$nik, $nama, $unit, $jabatan, $status, $id]);
        header("Location: sop_kpi_karyawan.php?success=edit");
        exit;
    }
    elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM kpi_karyawan WHERE id=?");
        $stmt->execute([$_POST['id']]);
        header("Location: sop_kpi_karyawan.php?success=delete");
        exit;
    } elseif ($action === 'sync_komite') {
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

// Fetch all data
$stmt = $pdo->query("SELECT * FROM kpi_karyawan ORDER BY id DESC");
$karyawanList = $stmt->fetchAll();

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
                    <div class="flex gap-2">
                        <form method="POST" class="inline" onsubmit="return confirm('Tarik data tenaga medis dari tabel Komite?');">
                            <input type="hidden" name="action" value="sync_komite">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Sinkronisasi Data Komite
                            </button>
                        </form>
                        <button onclick="openModal('modalAdd')" class="bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center gap-2">
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

                <!-- Table Area -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden p-6">
                    <div class="overflow-x-auto">
                        <table id="karyawanTable" class="w-full text-left" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">ID / NIK</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama Karyawan</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit / Departemen</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Jabatan</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($karyawanList as $k): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="text-sm font-medium text-gray-900 py-3"><?= htmlspecialchars($k['nik']) ?></td>
                                    <td class="py-3">
                                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($k['nama']) ?></div>
                                    </td>
                                    <td class="text-sm text-gray-600 py-3"><?= htmlspecialchars($k['unit']) ?></td>
                                    <td class="text-sm text-gray-600 py-3"><?= htmlspecialchars($k['jabatan']) ?></td>
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
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 m-4 relative shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Tambah Karyawan</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">NIK</label>
                        <input type="text" name="nik" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap</label>
                        <input type="text" name="nama" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Unit</label>
                        <input type="text" name="unit" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jabatan</label>
                        <input type="text" name="jabatan" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
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
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 m-4 relative shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Edit Karyawan</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">NIK</label>
                        <input type="text" name="nik" id="edit_nik" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap</label>
                        <input type="text" name="nama" id="edit_nama" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Unit</label>
                        <input type="text" name="unit" id="edit_unit" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jabatan</label>
                        <input type="text" name="jabatan" id="edit_jabatan" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                        <select name="status" id="edit_status" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none bg-white">
                            <option value="Aktif">Aktif</option>
                            <option value="Nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalEdit')" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-medium">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        $(document).ready(function() {
            $('#karyawanTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                },
                "pageLength": 10
            });
        });

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.getElementById(id).classList.add('flex');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }
        function editData(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nik').value = data.nik;
            document.getElementById('edit_nama').value = data.nama;
            document.getElementById('edit_unit').value = data.unit;
            document.getElementById('edit_jabatan').value = data.jabatan;
            document.getElementById('edit_status').value = data.status;
            openModal('modalEdit');
        }
    </script>
</body>
</html>
