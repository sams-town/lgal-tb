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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO kpi_rkk_karyawan (karyawan_id, tugas, deskripsi, jenis) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['karyawan_id'], $_POST['tugas'], $_POST['deskripsi'], $_POST['jenis']]);
        header("Location: sop_kpi_rkk.php?success=add&karyawan_id=".$_POST['karyawan_id']);
        exit;
    } elseif ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE kpi_rkk_karyawan SET tugas=?, deskripsi=?, jenis=? WHERE id=?");
        $stmt->execute([$_POST['tugas'], $_POST['deskripsi'], $_POST['jenis'], $_POST['id']]);
        header("Location: sop_kpi_rkk.php?success=edit&karyawan_id=".$_POST['karyawan_id']);
        exit;
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM kpi_rkk_karyawan WHERE id=?");
        $stmt->execute([$_POST['id']]);
        header("Location: sop_kpi_rkk.php?success=delete&karyawan_id=".$_POST['karyawan_id']);
        exit;
    } elseif ($action === 'copy_template') {
        $karyawan_id = $_POST['karyawan_id'];
        $template_id = $_POST['template_id'];
        
        $stmtTugas = $pdo->prepare("SELECT * FROM kpi_rkk_tugas WHERE template_id = ?");
        $stmtTugas->execute([$template_id]);
        $tugasList = $stmtTugas->fetchAll();
        
        $stmtInsert = $pdo->prepare("INSERT INTO kpi_rkk_karyawan (karyawan_id, tugas, deskripsi, jenis) VALUES (?, ?, ?, ?)");
        foreach($tugasList as $t) {
            $stmtInsert->execute([$karyawan_id, $t['tugas'], $t['deskripsi'], 'Pokok']);
        }
        header("Location: sop_kpi_rkk.php?success=copy&karyawan_id=".$karyawan_id);
        exit;
    }
}

// Fetch Karyawan
$stmtK = $pdo->query("SELECT id, nama, jabatan, unit FROM kpi_karyawan WHERE status='Aktif' ORDER BY nama ASC");
$karyawanList = $stmtK->fetchAll();

$karyawan_id = $_GET['karyawan_id'] ?? '';
$rkkList = [];
$karyawanData = null;

if ($karyawan_id) {
    // Info karyawan
    $stmtKaryawan = $pdo->prepare("SELECT * FROM kpi_karyawan WHERE id = ?");
    $stmtKaryawan->execute([$karyawan_id]);
    $karyawanData = $stmtKaryawan->fetch();
    
    // List RKK
    $stmtRKK = $pdo->prepare("SELECT * FROM kpi_rkk_karyawan WHERE karyawan_id = ? ORDER BY jenis DESC, id ASC");
    $stmtRKK->execute([$karyawan_id]);
    $rkkList = $stmtRKK->fetchAll();
}

// Fetch Template untuk fitur copy
$stmtTemp = $pdo->query("SELECT id, jabatan, unit FROM kpi_rkk_template ORDER BY jabatan ASC");
$templateList = $stmtTemp->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log RKK / Job Des - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
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
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Log RKK / Job Des</h1>
                        <p class="text-gray-500 mt-1">Rincian Kewenangan Klinis & Tugas per Karyawan</p>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span>Perubahan berhasil disimpan!</span>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Sidebar: Pilih Karyawan -->
                    <div class="lg:col-span-1 space-y-4">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                            <h3 class="font-bold text-gray-800 mb-4">Pilih Karyawan</h3>
                            <form method="GET">
                                <select name="karyawan_id" onchange="this.form.submit()" class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                    <option value="">-- Pilih Karyawan --</option>
                                    <?php foreach($karyawanList as $k): ?>
                                    <option value="<?= $k['id'] ?>" <?= ($karyawan_id == $k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="lg:col-span-3">
                        <?php if($karyawan_id && $karyawanData): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <!-- Info Karyawan -->
                            <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-teal-500 to-emerald-600 text-white flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div>
                                    <h2 class="text-2xl font-bold"><?= htmlspecialchars($karyawanData['nama']) ?></h2>
                                    <p class="text-teal-50 mt-1"><?= htmlspecialchars($karyawanData['jabatan']) ?> • <?= htmlspecialchars($karyawanData['unit']) ?></p>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="openModal('modalCopy')" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors flex items-center gap-2">
                                        <i data-lucide="copy" class="w-4 h-4"></i> Salin dari Template
                                    </button>
                                    <button onclick="openModal('modalAdd')" class="bg-white text-teal-600 hover:bg-teal-50 px-4 py-2 rounded-xl text-sm font-bold shadow-sm transition-colors flex items-center gap-2">
                                        <i data-lucide="plus" class="w-4 h-4"></i> Tambah Tugas
                                    </button>
                                </div>
                            </div>

                            <!-- List Tugas -->
                            <div class="p-6 space-y-4">
                                <?php if(empty($rkkList)): ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <i data-lucide="file-x" class="w-12 h-12 mx-auto text-gray-300 mb-3"></i>
                                        <p>Belum ada rincian tugas untuk karyawan ini.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php foreach($rkkList as $r): ?>
                                <div class="p-4 border border-gray-200 rounded-xl hover:border-teal-300 transition-colors group flex justify-between items-start">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="font-bold text-gray-800"><?= htmlspecialchars($r['tugas']) ?></h4>
                                            <?php if($r['jenis'] === 'Pokok'): ?>
                                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-700">Pokok</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-700">Tambahan</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-sm text-gray-600"><?= nl2br(htmlspecialchars($r['deskripsi'])) ?></p>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                        <button onclick='editData(<?= json_encode($r) ?>)' class="text-blue-500 hover:bg-blue-50 p-2 rounded-lg transition-colors"><i data-lucide="edit" class="w-4 h-4"></i></button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Hapus tugas ini?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="karyawan_id" value="<?= $karyawan_id ?>">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center text-gray-500">
                            <i data-lucide="users" class="w-16 h-16 mx-auto text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-bold text-gray-700 mb-2">Pilih Karyawan</h3>
                            <p>Silakan pilih karyawan dari menu di sebelah kiri untuk melihat dan mengelola RKK / Job Description.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <?php if($karyawan_id): ?>
    <!-- Modal Tambah/Edit Tugas -->
    <div id="modalAdd" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 m-4 relative shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4" id="modalTitle">Tambah Tugas</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add" id="modalAction">
                <input type="hidden" name="karyawan_id" value="<?= $karyawan_id ?>">
                <input type="hidden" name="id" id="tugas_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Tugas / Kewenangan</label>
                        <input type="text" name="tugas" id="tugas" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi Detail</label>
                        <textarea name="deskripsi" id="deskripsi" rows="3" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis Tugas</label>
                        <select name="jenis" id="jenis" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                            <option value="Pokok">Tugas Pokok</option>
                            <option value="Tambahan">Tugas Tambahan</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalAdd')" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-medium">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Copy Template -->
    <div id="modalCopy" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 m-4 relative shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Salin dari Template Jabatan</h2>
            <form method="POST" onsubmit="return confirm('Proses ini akan menambahkan tugas dari template ke karyawan ini. Lanjutkan?');">
                <input type="hidden" name="action" value="copy_template">
                <input type="hidden" name="karyawan_id" value="<?= $karyawan_id ?>">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih Template</label>
                        <select name="template_id" required class="w-full border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-teal-500 outline-none bg-gray-50">
                            <option value="">-- Pilih Template --</option>
                            <?php foreach($templateList as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['jabatan']) ?> (<?= htmlspecialchars($t['unit']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalCopy')" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium">Salin Tugas</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        lucide.createIcons();

        function openModal(id) {
            if(id === 'modalAdd'){
                $('#modalAction').val('add');
                $('#modalTitle').text('Tambah Tugas');
                $('#tugas_id').val('');
                $('#tugas').val('');
                $('#deskripsi').val('');
                $('#jenis').val('Pokok');
            }
            document.getElementById(id).classList.remove('hidden');
            document.getElementById(id).classList.add('flex');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }
        function editData(data) {
            $('#modalAction').val('edit');
            $('#modalTitle').text('Edit Tugas');
            $('#tugas_id').val(data.id);
            $('#tugas').val(data.tugas);
            $('#deskripsi').val(data.deskripsi);
            $('#jenis').val(data.jenis);
            
            document.getElementById('modalAdd').classList.remove('hidden');
            document.getElementById('modalAdd').classList.add('flex');
        }
    </script>
</body>
</html>
