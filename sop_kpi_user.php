<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
if (!hasPermission('sop_view')) { header('Location: dashboard.php'); exit; }

$userRole = $_SESSION['user']['nama_role'] ?? $_SESSION['user']['role'] ?? '';
$isSuperAdmin = ($userRole === 'Super Admin');

// Peran yang bisa input penilaian KPI
$ROLE_SUPERVISOR = ['Supervisor','Kepala Unit','Kepala Ruangan','Atasan','Admin HRD','HRD','Super Admin'];

$message = '';
$msgType = 'success';

// ── AUTO MIGRATE: pastikan kolom user_id & atasan_id ada di kpi_karyawan ─────
try {
    foreach (['user_id','atasan_id'] as $col) {
        $ex = $pdo->query("SHOW COLUMNS FROM kpi_karyawan LIKE '$col'")->rowCount();
        if ($ex == 0) $pdo->exec("ALTER TABLE kpi_karyawan ADD COLUMN `$col` INT DEFAULT NULL");
    }
} catch (PDOException $e) {}

// ── HANDLER: Tambah User ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='tambah_user') {
    if (!$isSuperAdmin) { $message='Hanya Super Admin yang bisa tambah user.'; $msgType='error'; goto render; }
    $nama    = trim($_POST['nama']);
    $email   = trim($_POST['email']);
    $pass    = $_POST['password'];
    $role_id = (int)$_POST['role_id'];
    $status  = (int)($_POST['is_active'] ?? 1);

    if (!$nama || !$email || !$pass) { $message='Nama, Email, dan Password wajib diisi.'; $msgType='error'; goto render; }
    try {
        $stmtU = $pdo->prepare("INSERT INTO users (nama,email,password,role_id,is_active) VALUES(?,?,?,?,?)");
        $stmtU->execute([$nama,$email,password_hash($pass,PASSWORD_DEFAULT),$role_id,$status]);
        $message = "User <strong>$nama</strong> berhasil ditambahkan.";
    } catch (PDOException $e) {
        $message = 'Gagal: ' . ($e->getCode()==23000 ? 'Email sudah digunakan.' : $e->getMessage());
        $msgType = 'error';
    }
    goto render;
}

// ── HANDLER: Edit User ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='edit_user') {
    if (!$isSuperAdmin) { $message='Hanya Super Admin.'; $msgType='error'; goto render; }
    $uid     = (int)$_POST['user_id'];
    $nama    = trim($_POST['nama']);
    $email   = trim($_POST['email']);
    $role_id = (int)$_POST['role_id'];
    $status  = (int)($_POST['is_active'] ?? 1);
    $pass    = trim($_POST['password'] ?? '');

    try {
        if ($pass) {
            $pdo->prepare("UPDATE users SET nama=?,email=?,role_id=?,is_active=?,password=? WHERE id=?")
                ->execute([$nama,$email,$role_id,$status,password_hash($pass,PASSWORD_DEFAULT),$uid]);
        } else {
            $pdo->prepare("UPDATE users SET nama=?,email=?,role_id=?,is_active=? WHERE id=?")
                ->execute([$nama,$email,$role_id,$status,$uid]);
        }
        $message = "User <strong>$nama</strong> berhasil diperbarui.";
    } catch (PDOException $e) {
        $message = 'Gagal: ' . $e->getMessage(); $msgType='error';
    }
    goto render;
}

// ── HANDLER: Hapus User ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='hapus_user') {
    if (!$isSuperAdmin) { $message='Hanya Super Admin.'; $msgType='error'; goto render; }
    $uid = (int)$_POST['user_id'];
    try {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        $message = 'User berhasil dihapus.';
    } catch (PDOException $e) { $message='Gagal: '.$e->getMessage(); $msgType='error'; }
    goto render;
}

// ── HANDLER: Assign Karyawan ke Supervisor ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='assign_supervisor') {
    $kid    = (int)$_POST['karyawan_id'];
    $sup_id = $_POST['atasan_id'] !== '' ? (int)$_POST['atasan_id'] : null;
    $usr_id = $_POST['user_id_karyawan'] !== '' ? (int)$_POST['user_id_karyawan'] : null;
    try {
        $pdo->prepare("UPDATE kpi_karyawan SET atasan_id=?,user_id=? WHERE id=?")
            ->execute([$sup_id,$usr_id,$kid]);
        $message = 'Penugasan supervisor/akun berhasil disimpan.';
    } catch (PDOException $e) { $message='Gagal: '.$e->getMessage(); $msgType='error'; }
    goto render;
}

render:

// ── Data untuk tampilan ──────────────────────────────────────────────────────
try {
    $users = $pdo->query("
        SELECT u.*, r.nama_role
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        ORDER BY u.nama ASC
    ")->fetchAll();
} catch (PDOException $e) { $users = []; }

try {
    $roles = $pdo->query("SELECT id,nama_role FROM roles ORDER BY nama_role ASC")->fetchAll();
} catch (PDOException $e) { $roles = []; }

try {
    $karyawanList = $pdo->query("
        SELECT k.*, u.nama as nama_user, u2.nama as nama_supervisor
        FROM kpi_karyawan k
        LEFT JOIN users u  ON u.id  = k.user_id
        LEFT JOIN users u2 ON u2.id = k.atasan_id
        WHERE k.status='Aktif'
        ORDER BY k.nama ASC
    ")->fetchAll();
} catch (PDOException $e) { $karyawanList = []; }

// User yang bisa jadi supervisor
$supervisorList = array_filter($users, fn($u) => in_array($u['nama_role'], $ROLE_SUPERVISOR));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User KPI - RS Taman Harapan Baru</title>
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
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Manajemen User & Supervisor KPI</h1>
                        <p class="text-gray-500 text-sm mt-1">Kelola akun login, role, dan penugasan supervisor untuk penilaian KPI</p>
                    </div>
                    <?php if ($isSuperAdmin): ?>
                    <button onclick="openModal('modalTambahUser')"
                        class="flex items-center gap-2 bg-teal-600 hover:bg-teal-700 text-white px-4 py-2.5 rounded-xl font-semibold text-sm shadow-sm">
                        <i data-lucide="user-plus" class="w-4 h-4"></i> Tambah User Baru
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Notifikasi -->
                <?php if ($message): ?>
                <div class="px-4 py-3 rounded-xl flex items-center gap-2 text-sm <?= $msgType==='error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-emerald-50 border border-emerald-200 text-emerald-700' ?>">
                    <i data-lucide="<?= $msgType==='error' ? 'alert-circle' : 'check-circle' ?>" class="w-5 h-5 flex-shrink-0"></i>
                    <span><?= $message ?></span>
                </div>
                <?php endif; ?>

                <!-- Info Role -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-700">
                    <p class="font-bold mb-1">Panduan Role untuk KPI:</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        <div><span class="font-semibold">Super Admin / Admin HRD / HRD</span> — bisa nilai semua karyawan</div>
                        <div><span class="font-semibold">Supervisor / Kepala Unit / Kepala Ruangan / Atasan</span> — hanya nilai karyawan bawahannya</div>
                        <div><span class="font-semibold">Role lain</span> — hanya lihat data diri sendiri (jika ada akun karyawan)</div>
                    </div>
                </div>

                <!-- ══ TAB ══════════════════════════════════════════════════════ -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 inline-flex gap-1">
                    <button onclick="switchTab('tabUser')" id="btnUser"
                        class="px-5 py-2 rounded-xl font-bold text-sm bg-teal-600 text-white shadow-sm">Daftar User</button>
                    <button onclick="switchTab('tabAssign')" id="btnAssign"
                        class="px-5 py-2 rounded-xl font-bold text-sm text-gray-500 hover:bg-gray-50">Penugasan Supervisor</button>
                </div>

                <!-- ══ TAB: DAFTAR USER ══════════════════════════════════════════ -->
                <div id="tabUser">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h2 class="font-bold text-gray-800">Daftar User (<?= count($users) ?>)</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-10">No</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Role</th>
                                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                                        <?php if ($isSuperAdmin): ?>
                                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-24">Aksi</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($users)): ?>
                                    <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">Belum ada user.</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($users as $i => $u):
                                        $isSup = in_array($u['nama_role'], $ROLE_SUPERVISOR);
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 text-gray-400 text-xs"><?= $i+1 ?></td>
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                    <?= mb_strtoupper(mb_substr($u['nama'],0,1)) ?>
                                                </div>
                                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($u['nama']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($u['email']) ?></td>
                                        <td class="px-5 py-3">
                                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $isSup ? 'bg-teal-100 text-teal-800' : 'bg-gray-100 text-gray-700' ?>">
                                                <?= htmlspecialchars($u['nama_role'] ?? 'Tanpa Role') ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-center">
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?= $u['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>">
                                                <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                            </span>
                                        </td>
                                        <?php if ($isSuperAdmin): ?>
                                        <td class="px-5 py-3 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button onclick='openEditUser(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'
                                                    class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg" title="Edit">
                                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Hapus user <?= htmlspecialchars($u['nama'], ENT_QUOTES) ?>?')">
                                                    <input type="hidden" name="action"  value="hapus_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg" title="Hapus">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ══ TAB: PENUGASAN SUPERVISOR ════════════════════════════════ -->
                <div id="tabAssign" class="hidden">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                            <h2 class="font-bold text-gray-800">Penugasan Supervisor per Karyawan</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Tentukan siapa yang bertanggung jawab menilai setiap karyawan, dan akun login karyawan</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Karyawan</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Unit / Jabatan</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Akun Login Karyawan</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Supervisor / Penilai</th>
                                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-24">Simpan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($karyawanList)): ?>
                                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">Belum ada data karyawan. <a href="sop_kpi_karyawan.php" class="text-teal-600 underline">Tambah Karyawan</a></td></tr>
                                    <?php else: ?>
                                    <?php foreach ($karyawanList as $k): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3">
                                            <div class="font-semibold text-gray-800"><?= htmlspecialchars($k['nama']) ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($k['nik']) ?></div>
                                        </td>
                                        <td class="px-5 py-3 text-xs text-gray-500">
                                            <div><?= htmlspecialchars($k['unit']) ?></div>
                                            <div class="text-gray-400"><?= htmlspecialchars($k['jabatan']) ?></div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <form method="POST" class="assign-form">
                                                <input type="hidden" name="action"       value="assign_supervisor">
                                                <input type="hidden" name="karyawan_id"  value="<?= $k['id'] ?>">
                                                <!-- Akun login karyawan -->
                                                <select name="user_id_karyawan"
                                                    class="w-full border border-gray-200 rounded-lg py-1.5 px-2 text-xs bg-white focus:ring-2 focus:ring-teal-400 outline-none">
                                                    <option value="">-- Belum ada akun --</option>
                                                    <?php foreach ($users as $u): ?>
                                                    <option value="<?= $u['id'] ?>" <?= $k['user_id']==$u['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($u['nama']) ?> (<?= htmlspecialchars($u['nama_role']) ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                        </td>
                                        <td class="px-5 py-3">
                                                <!-- Supervisor -->
                                                <select name="atasan_id"
                                                    class="w-full border border-gray-200 rounded-lg py-1.5 px-2 text-xs bg-white focus:ring-2 focus:ring-teal-400 outline-none">
                                                    <option value="">-- Tidak ada supervisor --</option>
                                                    <?php foreach ($supervisorList as $s): ?>
                                                    <option value="<?= $s['id'] ?>" <?= $k['atasan_id']==$s['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($s['nama']) ?> (<?= htmlspecialchars($s['nama_role']) ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                        </td>
                                        <td class="px-5 py-3 text-center">
                                                <button type="submit"
                                                    class="px-3 py-1.5 bg-teal-600 text-white rounded-lg text-xs font-semibold hover:bg-teal-700">
                                                    Simpan
                                                </button>
                                            </form>
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
        </div>
    </main>

    <!-- ══ Modal Tambah User ════════════════════════════════════════════════ -->
    <div id="modalTambahUser" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-lg font-bold text-gray-900" id="modalUserTitle">Tambah User Baru</h2>
                <button onclick="closeModal('modalTambahUser')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" id="userAction" value="tambah_user">
                <input type="hidden" name="user_id" id="editUserId" value="">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" id="uNama" required placeholder="Contoh: Dr. Budi Santoso"
                        class="w-full border border-gray-200 py-2.5 px-3 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="uEmail" required placeholder="email@rsthb.id"
                        class="w-full border border-gray-200 py-2.5 px-3 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Password <span class="text-red-500" id="passRequired">*</span></label>
                    <input type="password" name="password" id="uPassword" placeholder="Kosongkan jika tidak ingin ubah"
                        class="w-full border border-gray-200 py-2.5 px-3 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Role</label>
                    <select name="role_id" id="uRole"
                        class="w-full border border-gray-200 py-2.5 px-3 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 outline-none">
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nama_role']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                    <select name="is_active" id="uStatus"
                        class="w-full border border-gray-200 py-2.5 px-3 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 outline-none">
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                    <button type="button" onclick="closeModal('modalTambahUser')"
                        class="px-4 py-2 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-50">Batal</button>
                    <button type="submit"
                        class="px-5 py-2 bg-teal-600 text-white rounded-xl text-sm font-semibold hover:bg-teal-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // ── Tab ───────────────────────────────────────────────────────────────
        function switchTab(id) {
            ['tabUser','tabAssign'].forEach(t => document.getElementById(t).classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            ['btnUser','btnAssign'].forEach(b => {
                document.getElementById(b).className = 'px-5 py-2 rounded-xl font-bold text-sm text-gray-500 hover:bg-gray-50';
            });
            const map = {tabUser:'btnUser', tabAssign:'btnAssign'};
            document.getElementById(map[id]).className = 'px-5 py-2 rounded-xl font-bold text-sm bg-teal-600 text-white shadow-sm';
        }

        // ── Modal ─────────────────────────────────────────────────────────────
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.getElementById(id).classList.add('flex');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }

        // ── Buka modal Tambah ─────────────────────────────────────────────────
        document.querySelector('[onclick="openModal(\'modalTambahUser\')"]')?.addEventListener('click', function() {
            document.getElementById('userAction').value   = 'tambah_user';
            document.getElementById('modalUserTitle').textContent = 'Tambah User Baru';
            document.getElementById('editUserId').value   = '';
            document.getElementById('uNama').value        = '';
            document.getElementById('uEmail').value       = '';
            document.getElementById('uPassword').value    = '';
            document.getElementById('uStatus').value      = '1';
            document.getElementById('passRequired').style.display = '';
        });

        // ── Buka modal Edit ───────────────────────────────────────────────────
        function openEditUser(u) {
            document.getElementById('userAction').value         = 'edit_user';
            document.getElementById('modalUserTitle').textContent = 'Edit User';
            document.getElementById('editUserId').value         = u.id;
            document.getElementById('uNama').value              = u.nama  || '';
            document.getElementById('uEmail').value             = u.email || '';
            document.getElementById('uPassword').value          = '';
            document.getElementById('uStatus').value            = u.is_active ? '1' : '0';
            document.getElementById('passRequired').style.display = 'none';

            // Set role dropdown
            const sel = document.getElementById('uRole');
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value == u.role_id) { sel.selectedIndex = i; break; }
            }
            openModal('modalTambahUser');
        }
    </script>
</body>
</html>
