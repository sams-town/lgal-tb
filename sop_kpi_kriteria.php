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
        $stmt = $pdo->prepare("INSERT INTO kpi_kriteria (kategori, nama_indikator, deskripsi, bobot) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['kategori'], $_POST['nama_indikator'], $_POST['deskripsi'], $_POST['bobot']]);
        header("Location: sop_kpi_kriteria.php?success=add");
        exit;
    } elseif ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE kpi_kriteria SET kategori=?, nama_indikator=?, deskripsi=?, bobot=? WHERE id=?");
        $stmt->execute([$_POST['kategori'], $_POST['nama_indikator'], $_POST['deskripsi'], $_POST['bobot'], $_POST['id']]);
        header("Location: sop_kpi_kriteria.php?success=edit");
        exit;
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM kpi_kriteria WHERE id=?");
        $stmt->execute([$_POST['id']]);
        header("Location: sop_kpi_kriteria.php?success=delete");
        exit;
    }
}

// Fetch Kriteria
$stmt = $pdo->query("SELECT * FROM kpi_kriteria ORDER BY kategori ASC, id ASC");
$kriteriaRaw = $stmt->fetchAll();

$kategoriList = [];
$totalBobotAll = 0;
foreach ($kriteriaRaw as $row) {
    if (!isset($kategoriList[$row['kategori']])) {
        $kategoriList[$row['kategori']] = ['bobot' => 0, 'items' => []];
    }
    $kategoriList[$row['kategori']]['items'][] = $row;
    $kategoriList[$row['kategori']]['bobot'] += $row['bobot'];
    $totalBobotAll += $row['bobot'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kriteria Penilaian - RS Taman Harapan Baru</title>
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
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Kriteria Penilaian</h1>
                        <p class="text-gray-500 mt-1">Pengaturan Parameter dan Bobot Nilai KPI Karyawan</p>
                    </div>
                    <button onclick="openModal('modalAdd')" class="bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i> Tambah Kriteria
                    </button>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span>Data berhasil disimpan!</span>
                </div>
                <?php endif; ?>
                
                <?php if ($totalBobotAll != 100 && $totalBobotAll > 0): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    <span>Peringatan: Total bobot seluruh kriteria saat ini adalah <b><?= $totalBobotAll ?>%</b> (Disarankan 100%).</span>
                </div>
                <?php endif; ?>

                <!-- Grid Configuration -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Panel: Kategori Kriteria -->
                    <div class="lg:col-span-1 space-y-4">
                        <h3 class="text-lg font-bold text-gray-800">Kategori Penilaian</h3>
                        <?php if(empty($kategoriList)): ?>
                            <p class="text-gray-500 text-sm">Belum ada kategori.</p>
                        <?php endif; ?>
                        
                        <?php 
                        $firstKat = array_key_first($kategoriList);
                        foreach($kategoriList as $kat => $data): 
                            $isActive = ($kat === $firstKat);
                        ?>
                        <div onclick="showCategory('<?= htmlspecialchars(str_replace(' ', '_', $kat)) ?>')" class="kategori-tab bg-white p-4 rounded-xl border <?= $isActive ? 'border-teal-200 border-l-4 border-l-teal-500 shadow-sm' : 'border-gray-200 shadow-sm hover:border-teal-200 transition-colors' ?> cursor-pointer" id="tab_<?= htmlspecialchars(str_replace(' ', '_', $kat)) ?>">
                            <h4 class="font-bold text-gray-900"><?= htmlspecialchars($kat) ?></h4>
                            <p class="text-sm text-gray-500 mt-1">Bobot Total: <span class="font-bold text-teal-600"><?= $data['bobot'] ?>%</span></p>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Right Panel: Detail Indikator -->
                    <div class="lg:col-span-2">
                        <?php foreach($kategoriList as $kat => $data): 
                            $isActive = ($kat === $firstKat);
                        ?>
                        <div id="content_<?= htmlspecialchars(str_replace(' ', '_', $kat)) ?>" class="kategori-content <?= $isActive ? '' : 'hidden' ?> bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                                <h3 class="font-bold text-gray-800">Indikator: <?= htmlspecialchars($kat) ?></h3>
                                <button onclick="openModal('modalAdd')" class="text-sm text-teal-600 font-semibold hover:text-teal-700 flex items-center gap-1">
                                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Tambah
                                </button>
                            </div>
                            <div class="p-5 space-y-4">
                                <?php foreach($data['items'] as $item): ?>
                                <div class="p-4 border border-gray-200 rounded-xl flex justify-between items-center group hover:border-teal-200 transition-colors">
                                    <div>
                                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($item['nama_indikator']) ?></h4>
                                        <p class="text-xs text-gray-500 mt-1"><?= nl2br(htmlspecialchars($item['deskripsi'])) ?></p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-center px-3 py-1 bg-gray-100 rounded-lg">
                                            <span class="block text-xs text-gray-500">Bobot</span>
                                            <span class="font-bold text-gray-800"><?= $item['bobot'] ?>%</span>
                                        </div>
                                        <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                            <button onclick='editData(<?= json_encode($item) ?>)' class="text-blue-500 hover:bg-blue-50 p-1.5 rounded-lg"><i data-lucide="edit" class="w-4 h-4"></i></button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Hapus indikator ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="text-red-500 hover:bg-red-50 p-1.5 rounded-lg"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>
    
    <!-- Modal Tambah/Edit Kriteria -->
    <div id="modalAdd" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 m-4 relative shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4" id="modalTitle">Tambah Kriteria</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add" id="modalAction">
                <input type="hidden" name="id" id="kriteria_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Kategori</label>
                        <input type="text" name="kategori" id="kategori" required placeholder="Contoh: Kompetensi Teknis" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Indikator</label>
                        <input type="text" name="nama_indikator" id="nama_indikator" required placeholder="Contoh: Kerjasama Tim" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="deskripsi" id="deskripsi" rows="3" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Bobot (%)</label>
                        <input type="number" name="bobot" id="bobot" required min="1" max="100" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalAdd')" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-medium">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function showCategory(id) {
            $('.kategori-content').addClass('hidden');
            $('#content_' + id).removeClass('hidden');
            
            $('.kategori-tab').removeClass('border-teal-200 border-l-4 border-l-teal-500').addClass('border-gray-200 hover:border-teal-200');
            $('#tab_' + id).removeClass('border-gray-200 hover:border-teal-200').addClass('border-teal-200 border-l-4 border-l-teal-500');
        }

        function openModal(id) {
            $('#modalAction').val('add');
            $('#modalTitle').text('Tambah Kriteria');
            $('#kriteria_id').val('');
            $('#kategori').val('');
            $('#nama_indikator').val('');
            $('#deskripsi').val('');
            $('#bobot').val('');
            
            document.getElementById(id).classList.remove('hidden');
            document.getElementById(id).classList.add('flex');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }
        function editData(data) {
            $('#modalAction').val('edit');
            $('#modalTitle').text('Edit Kriteria');
            $('#kriteria_id').val(data.id);
            $('#kategori').val(data.kategori);
            $('#nama_indikator').val(data.nama_indikator);
            $('#deskripsi').val(data.deskripsi);
            $('#bobot').val(data.bobot);
            
            document.getElementById('modalAdd').classList.remove('hidden');
            document.getElementById('modalAdd').classList.add('flex');
        }
    </script>
</body>
</html>
