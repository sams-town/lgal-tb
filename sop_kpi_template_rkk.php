<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
if (!hasPermission('sop_view')) { header("Location: dashboard.php"); exit; }

// ── Auto-migrate: tambah kolom tugas ke kpi_rkk_tugas jika belum ada ─────────
try {
    $cols = $pdo->query("SHOW COLUMNS FROM kpi_rkk_tugas")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('tugas', $cols)) {
        $pdo->exec("ALTER TABLE kpi_rkk_tugas ADD COLUMN `tugas` varchar(255) NOT NULL DEFAULT '' AFTER `template_id`");
    }
} catch (PDOException $e) { /* abaikan jika tabel belum ada */ }

// ── POST Handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Template CRUD ---
    if ($action === 'add_template') {
        $stmt = $pdo->prepare("INSERT INTO kpi_rkk_template (jabatan, unit) VALUES (?, ?)");
        $stmt->execute([trim($_POST['jabatan']), trim($_POST['unit'])]);
        header("Location: sop_kpi_template_rkk.php?success=add");
        exit;
    } elseif ($action === 'edit_template') {
        $stmt = $pdo->prepare("UPDATE kpi_rkk_template SET jabatan=?, unit=? WHERE id=?");
        $stmt->execute([trim($_POST['jabatan']), trim($_POST['unit']), (int)$_POST['id']]);
        header("Location: sop_kpi_template_rkk.php?success=edit");
        exit;
    } elseif ($action === 'delete_template') {
        $stmt = $pdo->prepare("DELETE FROM kpi_rkk_template WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
        header("Location: sop_kpi_template_rkk.php?success=delete");
        exit;

    // --- Tugas dalam Template CRUD ---
    } elseif ($action === 'add_tugas') {
        $tid   = (int)$_POST['template_id'];
        $tugas = trim($_POST['tugas']);
        $desk  = trim($_POST['deskripsi'] ?? '');
        $tipe  = in_array($_POST['tipe_tugas'] ?? '', ['Pokok','Tambahan']) ? $_POST['tipe_tugas'] : 'Pokok';
        $stmt  = $pdo->prepare("INSERT INTO kpi_rkk_tugas (template_id, tugas, deskripsi, tipe_tugas) VALUES (?,?,?,?)");
        $stmt->execute([$tid, $tugas, $desk, $tipe]);
        header("Location: sop_kpi_template_rkk.php?success=tugas_add&open=$tid");
        exit;
    } elseif ($action === 'edit_tugas') {
        $id    = (int)$_POST['tugas_id'];
        $tid   = (int)$_POST['template_id'];
        $tugas = trim($_POST['tugas']);
        $desk  = trim($_POST['deskripsi'] ?? '');
        $tipe  = in_array($_POST['tipe_tugas'] ?? '', ['Pokok','Tambahan']) ? $_POST['tipe_tugas'] : 'Pokok';
        $stmt  = $pdo->prepare("UPDATE kpi_rkk_tugas SET tugas=?, deskripsi=?, tipe_tugas=? WHERE id=?");
        $stmt->execute([$tugas, $desk, $tipe, $id]);
        header("Location: sop_kpi_template_rkk.php?success=tugas_edit&open=$tid");
        exit;
    } elseif ($action === 'delete_tugas') {
        $id  = (int)$_POST['tugas_id'];
        $tid = (int)$_POST['template_id'];
        $pdo->prepare("DELETE FROM kpi_rkk_tugas WHERE id=?")->execute([$id]);
        header("Location: sop_kpi_template_rkk.php?success=tugas_delete&open=$tid");
        exit;
    }
}

// ── Ambil Template + jumlah tugas ────────────────────────────────────────────
$templateList = $pdo->query("
    SELECT t.*, COUNT(r.id) as jumlah_tugas
    FROM kpi_rkk_template t
    LEFT JOIN kpi_rkk_tugas r ON t.id = r.template_id
    GROUP BY t.id
    ORDER BY t.jabatan ASC
")->fetchAll();

// Jika ada ?open=ID → ambil tugas untuk template itu
$openId    = (int)($_GET['open'] ?? 0);
$tugasList = [];
$openData  = null;
if ($openId) {
    $s = $pdo->prepare("SELECT * FROM kpi_rkk_template WHERE id=?");
    $s->execute([$openId]);
    $openData = $s->fetch();
    $s2 = $pdo->prepare("SELECT * FROM kpi_rkk_tugas WHERE template_id=? ORDER BY tipe_tugas DESC, id ASC");
    $s2->execute([$openId]);
    $tugasList = $s2->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template RKK per Jabatan - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 flex flex-col relative h-screen overflow-hidden">
        <?php include 'includes/header.php'; ?>

        <div class="flex-1 p-6 overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- Page Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Template RKK / Job Des</h1>
                        <p class="text-gray-500 text-sm mt-1">Pengaturan Master Template Tugas per Jabatan</p>
                    </div>
                    <button onclick="openModal('modalTemplate')"
                        class="bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2.5 px-5 rounded-xl flex items-center gap-2 text-sm">
                        <i data-lucide="file-plus" class="w-4 h-4"></i> Buat Template Baru
                    </button>
                </div>

                <!-- Notifikasi -->
                <?php if (isset($_GET['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <?php
                        $msg = [
                            'add'          => 'Template berhasil dibuat!',
                            'edit'         => 'Template berhasil diperbarui!',
                            'delete'       => 'Template berhasil dihapus!',
                            'tugas_add'    => 'Tugas berhasil ditambahkan ke template!',
                            'tugas_edit'   => 'Tugas berhasil diperbarui!',
                            'tugas_delete' => 'Tugas berhasil dihapus!',
                        ];
                        echo $msg[$_GET['success']] ?? 'Berhasil disimpan!';
                    ?>
                </div>
                <?php endif; ?>

                <!-- ── PANEL DETAIL TUGAS (muncul jika ada ?open=ID) ─────── -->
                <?php if ($openId && $openData): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-teal-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-teal-500 to-emerald-600 text-white flex justify-between items-center">
                        <div>
                            <h2 class="font-bold text-lg"><?= htmlspecialchars($openData['jabatan']) ?></h2>
                            <p class="text-teal-50 text-sm"><?= htmlspecialchars($openData['unit']) ?> — Daftar Tugas / Kewenangan</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="openModalTugas(<?= $openId ?>)"
                                class="bg-white text-teal-700 hover:bg-teal-50 px-3 py-1.5 rounded-xl text-sm font-bold flex items-center gap-1.5">
                                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Tugas
                            </button>
                            <a href="sop_kpi_template_rkk.php"
                                class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-sm font-semibold flex items-center gap-1.5">
                                <i data-lucide="x" class="w-4 h-4"></i> Tutup
                            </a>
                        </div>
                    </div>
                    <div class="p-5">
                        <?php if (empty($tugasList)): ?>
                            <div class="text-center py-8 text-gray-400">
                                <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                                <p class="text-sm">Belum ada tugas. Klik <strong>Tambah Tugas</strong> untuk mengisi.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($tugasList as $tg): ?>
                                <div class="flex justify-between items-start p-3 border border-gray-200 rounded-xl hover:border-teal-300 group transition-colors">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <span class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($tg['tugas']) ?></span>
                                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= ($tg['tipe_tugas']==='Pokok') ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' ?>">
                                                <?= htmlspecialchars($tg['tipe_tugas']) ?>
                                            </span>
                                        </div>
                                        <?php if ($tg['deskripsi']): ?>
                                            <p class="text-xs text-gray-500"><?= nl2br(htmlspecialchars($tg['deskripsi'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1 ml-3 flex-shrink-0">
                                        <button onclick='editTugas(<?= json_encode($tg) ?>)'
                                            class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Hapus tugas ini?');">
                                            <input type="hidden" name="action" value="delete_tugas">
                                            <input type="hidden" name="tugas_id" value="<?= $tg['id'] ?>">
                                            <input type="hidden" name="template_id" value="<?= $openId ?>">
                                            <button type="submit" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── TEMPLATE GRID ─────────────────────────────────────── -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($templateList)): ?>
                        <div class="col-span-full p-10 text-center text-gray-400 border-2 border-dashed border-gray-200 rounded-2xl">
                            Belum ada template. Klik <strong>Buat Template Baru</strong> untuk mulai.
                        </div>
                    <?php endif; ?>

                    <?php
                    $gradients = [
                        ['from-teal-500','to-emerald-600'],
                        ['from-blue-500','to-indigo-600'],
                        ['from-purple-500','to-fuchsia-600'],
                        ['from-amber-500','to-orange-600'],
                    ];
                    foreach ($templateList as $idx => $t):
                        $g = $gradients[$idx % count($gradients)];
                        $isOpen = ($openId === (int)$t['id']);
                    ?>
                    <div class="bg-white rounded-2xl shadow-sm border <?= $isOpen ? 'border-teal-400 ring-2 ring-teal-200' : 'border-gray-200' ?> overflow-hidden hover:shadow-md transition-shadow">
                        <div class="p-5 bg-gradient-to-r <?= $g[0] ?> <?= $g[1] ?> text-white flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-lg"><?= htmlspecialchars($t['jabatan']) ?></h3>
                                <p class="text-white/80 text-sm mt-0.5"><?= htmlspecialchars($t['unit']) ?></p>
                            </div>
                            <button onclick='editTemplate(<?= json_encode($t) ?>)'
                                class="bg-white/20 hover:bg-white/30 p-1.5 rounded-lg">
                                <i data-lucide="edit-3" class="w-4 h-4 text-white"></i>
                            </button>
                        </div>
                        <div class="px-5 py-3">
                            <div class="flex justify-between items-center text-sm text-gray-600">
                                <span>Jumlah Tugas</span>
                                <span class="font-bold bg-gray-100 px-2 py-1 rounded-lg text-gray-800">
                                    <?= $t['jumlah_tugas'] ?> Item
                                </span>
                            </div>
                        </div>
                        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex gap-2">
                            <a href="sop_kpi_template_rkk.php?open=<?= $t['id'] ?>"
                                class="flex-1 text-center py-2 bg-white border border-gray-200 rounded-lg text-sm font-semibold text-teal-700 hover:bg-teal-50 hover:border-teal-300 transition-colors">
                                <i data-lucide="list" class="w-3.5 h-3.5 inline mr-1"></i>
                                <?= $isOpen ? 'Sedang Dibuka' : 'Lihat & Isi Tugas' ?>
                            </a>
                            <form method="POST" class="inline" onsubmit="return confirm('Hapus template ini beserta semua tugas di dalamnya?');">
                                <input type="hidden" name="action" value="delete_template">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-red-500 hover:bg-red-50 transition-colors">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
    </main>

    <!-- ══ Modal Tambah/Edit Template ══════════════════════════════════════ -->
    <div id="modalTemplate" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 m-4 shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4" id="modalTemplateTitle">Tambah Template</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_template" id="modalTemplateAction">
                <input type="hidden" name="id" id="tmpl_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jabatan</label>
                        <input type="text" name="jabatan" id="tmpl_jabatan" required
                            placeholder="Contoh: Perawat Pelaksana"
                            class="w-full border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-400 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Unit / Departemen</label>
                        <input type="text" name="unit" id="tmpl_unit" required
                            placeholder="Contoh: IGD"
                            class="w-full border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-400 outline-none text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalTemplate')"
                        class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 text-sm">Batal</button>
                    <button type="submit"
                        class="px-4 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-medium text-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ Modal Tambah/Edit Tugas dalam Template ═══════════════════════════ -->
    <div id="modalTugas" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 m-4 shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4" id="modalTugasTitle">Tambah Tugas</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_tugas" id="tugasAction">
                <input type="hidden" name="template_id" id="tugasTemplateId">
                <input type="hidden" name="tugas_id"    id="tugasId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Tugas / Kewenangan <span class="text-red-500">*</span></label>
                        <input type="text" name="tugas" id="tugasNama" required
                            placeholder="Contoh: Melakukan pemeriksaan fisik pasien"
                            class="w-full border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-400 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi Detail</label>
                        <textarea name="deskripsi" id="tugasDeskripsi" rows="3"
                            placeholder="Uraian rinci tentang tugas ini..."
                            class="w-full border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-400 outline-none text-sm resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis Tugas</label>
                        <select name="tipe_tugas" id="tugasTipe"
                            class="w-full border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-400 outline-none text-sm bg-white">
                            <option value="Pokok">Tugas Pokok</option>
                            <option value="Tambahan">Tugas Tambahan</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalTugas')"
                        class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 text-sm">Batal</button>
                    <button type="submit"
                        class="px-4 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-medium text-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.getElementById(id).classList.add('flex');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }

        // ── Template modal ────────────────────────────────────────────────
        function editTemplate(data) {
            document.getElementById('modalTemplateAction').value = 'edit_template';
            document.getElementById('modalTemplateTitle').textContent = 'Edit Template';
            document.getElementById('tmpl_id').value      = data.id;
            document.getElementById('tmpl_jabatan').value = data.jabatan;
            document.getElementById('tmpl_unit').value    = data.unit;
            openModal('modalTemplate');
        }
        // Reset ke mode tambah saat buka via tombol Buat Template Baru
        document.querySelector('[onclick="openModal(\'modalTemplate\')"]').addEventListener('click', function() {
            document.getElementById('modalTemplateAction').value = 'add_template';
            document.getElementById('modalTemplateTitle').textContent = 'Tambah Template';
            document.getElementById('tmpl_id').value      = '';
            document.getElementById('tmpl_jabatan').value = '';
            document.getElementById('tmpl_unit').value    = '';
        });

        // ── Tugas modal ───────────────────────────────────────────────────
        function openModalTugas(templateId) {
            document.getElementById('tugasAction').value      = 'add_tugas';
            document.getElementById('tugasTemplateId').value  = templateId;
            document.getElementById('tugasId').value          = '';
            document.getElementById('tugasNama').value        = '';
            document.getElementById('tugasDeskripsi').value   = '';
            document.getElementById('tugasTipe').value        = 'Pokok';
            document.getElementById('modalTugasTitle').textContent = 'Tambah Tugas';
            openModal('modalTugas');
        }
        function editTugas(data) {
            document.getElementById('tugasAction').value      = 'edit_tugas';
            document.getElementById('tugasTemplateId').value  = data.template_id;
            document.getElementById('tugasId').value          = data.id;
            document.getElementById('tugasNama').value        = data.tugas       || '';
            document.getElementById('tugasDeskripsi').value   = data.deskripsi   || '';
            document.getElementById('tugasTipe').value        = data.tipe_tugas  || 'Pokok';
            document.getElementById('modalTugasTitle').textContent = 'Edit Tugas';
            openModal('modalTugas');
        }
    </script>
</body>
</html>
