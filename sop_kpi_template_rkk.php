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
        $stmt = $pdo->prepare("INSERT INTO kpi_rkk_template (jabatan, unit) VALUES (?, ?)");
        $stmt->execute([$_POST['jabatan'], $_POST['unit']]);
        header("Location: sop_kpi_template_rkk.php?success=add");
        exit;
    } elseif ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE kpi_rkk_template SET jabatan=?, unit=? WHERE id=?");
        $stmt->execute([$_POST['jabatan'], $_POST['unit'], $_POST['id']]);
        header("Location: sop_kpi_template_rkk.php?success=edit");
        exit;
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM kpi_rkk_template WHERE id=?");
        $stmt->execute([$_POST['id']]);
        header("Location: sop_kpi_template_rkk.php?success=delete");
        exit;
    }
}

// Ambil Template
$stmt = $pdo->query("
    SELECT t.*, COUNT(r.id) as jumlah_tugas 
    FROM kpi_rkk_template t
    LEFT JOIN kpi_rkk_tugas r ON t.id = r.template_id
    GROUP BY t.id
    ORDER BY t.jabatan ASC
");
$templateList = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template RKK per Jabatan - RS Taman Harapan Baru</title>
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
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Template RKK / Job Des</h1>
                        <p class="text-gray-500 mt-1">Pengaturan Master Template Tugas per Jabatan</p>
                    </div>
                    <button onclick="openModal('modalAdd')" class="bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                        <i data-lucide="file-plus" class="w-4 h-4"></i> Buat Template Baru
                    </button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span>Template berhasil disimpan/dihapus!</span>
                </div>
                <?php endif; ?>

                <!-- Template Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($templateList)): ?>
                        <div class="col-span-full p-8 text-center text-gray-500 border-2 border-dashed border-gray-300 rounded-2xl">
                            Belum ada template. Silakan buat template baru.
                        </div>
                    <?php endif; ?>
                    
                    <?php foreach($templateList as $t): 
                        // Generate color gradient based on unit/jabatan logic, or use generic
                        $colors = [
                            ['from-teal-500', 'to-emerald-600'],
                            ['from-blue-500', 'to-indigo-600'],
                            ['from-purple-500', 'to-fuchsia-600'],
                            ['from-amber-500', 'to-orange-600'],
                        ];
                        $color = $colors[array_rand($colors)];
                    ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                        <div class="p-5 border-b border-gray-100 bg-gradient-to-r <?= $color[0] ?> <?= $color[1] ?> text-white flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-lg"><?= htmlspecialchars($t['jabatan']) ?></h3>
                                <p class="text-white/80 text-sm mt-1"><?= htmlspecialchars($t['unit']) ?></p>
                            </div>
                            <button onclick='editData(<?= json_encode($t) ?>)' class="bg-white/20 hover:bg-white/30 p-1.5 rounded-lg transition-colors"><i data-lucide="edit-3" class="w-4 h-4 text-white"></i></button>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="flex justify-between items-center text-sm text-gray-600">
                                <span>Jumlah Tugas Pokok</span>
                                <span class="font-bold bg-gray-100 px-2 py-1 rounded-lg"><?= $t['jumlah_tugas'] ?> Item</span>
                            </div>
                        </div>
                        <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex gap-2">
                            <button class="flex-1 text-center py-2 bg-white border border-gray-200 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-100 transition-colors">Lihat Detail Tugas</button>
                            <form method="POST" class="inline" onsubmit="return confirm('Hapus template ini beserta semua tugas di dalamnya?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-red-500 hover:bg-red-50 transition-colors" title="Hapus"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
    </main>
    
    <!-- Modal Tambah/Edit -->
    <div id="modalAdd" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 m-4 relative shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4" id="modalTitle">Tambah Template</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add" id="modalAction">
                <input type="hidden" name="id" id="template_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jabatan</label>
                        <input type="text" name="jabatan" id="jabatan" required placeholder="Contoh: Perawat Pelaksana" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Unit / Departemen</label>
                        <input type="text" name="unit" id="unit" required placeholder="Contoh: IGD" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
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

        function openModal(id) {
            $('#modalAction').val('add');
            $('#modalTitle').text('Tambah Template');
            $('#template_id').val('');
            $('#jabatan').val('');
            $('#unit').val('');
            
            document.getElementById(id).classList.remove('hidden');
            document.getElementById(id).classList.add('flex');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }
        function editData(data) {
            $('#modalAction').val('edit');
            $('#modalTitle').text('Edit Template');
            $('#template_id').val(data.id);
            $('#jabatan').val(data.jabatan);
            $('#unit').val(data.unit);
            
            document.getElementById('modalAdd').classList.remove('hidden');
            document.getElementById('modalAdd').classList.add('flex');
        }
    </script>
</body>
</html>
