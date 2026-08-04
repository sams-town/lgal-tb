<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
if (!hasPermission('sekretariat_view')) { header("Location: dashboard.php"); exit; }

$user = $_SESSION['user'];

$reminderOptions = [
    '30 menit sebelumnya',
    '1 jam sebelumnya',
    'H-1 (sehari sebelumnya)',
];

// ── CRUD ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' && canUserEditOrDelete('sekretariat')) {
        $tanggal       = $_POST['tanggal'] ?? '';
        $waktu         = $_POST['waktu'] ?? null;
        $judul_agenda  = $_POST['judul_agenda'] ?? '';
        $lokasi        = $_POST['lokasi'] ?? '';
        $peserta       = !empty($_POST['peserta']) ? json_encode(array_filter(array_map('trim', explode("\n", $_POST['peserta'])))) : null;
        $reminder      = isset($_POST['reminder']) ? json_encode($_POST['reminder']) : null;
        $catatan       = $_POST['catatan'] ?? '';

        try {
            $pdo->prepare("INSERT INTO sekretariat_agenda (tanggal,waktu,judul_agenda,lokasi,peserta,reminder,catatan,created_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$tanggal, $waktu ?: null, $judul_agenda, $lokasi, $peserta, $reminder, $catatan, $user['nama'] ?? 'Admin']);
            $_SESSION['agenda_success'] = "Agenda berhasil ditambahkan!";
        } catch (PDOException $e) {
            $_SESSION['agenda_error'] = "Gagal: " . $e->getMessage();
        }
        header("Location: sekretariat_agenda.php"); exit;
    }

    if ($action === 'edit' && canUserEditOrDelete('sekretariat')) {
        $id            = (int)$_POST['id'];
        $tanggal       = $_POST['tanggal'] ?? '';
        $waktu         = $_POST['waktu'] ?? null;
        $judul_agenda  = $_POST['judul_agenda'] ?? '';
        $lokasi        = $_POST['lokasi'] ?? '';
        $peserta       = !empty($_POST['peserta']) ? json_encode(array_filter(array_map('trim', explode("\n", $_POST['peserta'])))) : null;
        $reminder      = isset($_POST['reminder']) ? json_encode($_POST['reminder']) : null;
        $catatan       = $_POST['catatan'] ?? '';

        try {
            $pdo->prepare("UPDATE sekretariat_agenda SET tanggal=?,waktu=?,judul_agenda=?,lokasi=?,peserta=?,reminder=?,catatan=? WHERE id=?")
                ->execute([$tanggal, $waktu ?: null, $judul_agenda, $lokasi, $peserta, $reminder, $catatan, $id]);
            $_SESSION['agenda_success'] = "Agenda berhasil diperbarui!";
        } catch (PDOException $e) {
            $_SESSION['agenda_error'] = "Gagal: " . $e->getMessage();
        }
        header("Location: sekretariat_agenda.php"); exit;
    }

    if ($action === 'delete' && canUserEditOrDelete('sekretariat')) {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM sekretariat_agenda WHERE id=?")->execute([$id]);
        $_SESSION['agenda_success'] = "Agenda berhasil dihapus.";
        header("Location: sekretariat_agenda.php"); exit;
    }
}

// ── Filter ────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'mendatang'; // mendatang | semua | lampau
$today  = date('Y-m-d');

$whereSQL = match($filter) {
    'mendatang' => "WHERE tanggal >= '$today'",
    'lampau'    => "WHERE tanggal < '$today'",
    default     => ''
};

try {
    $stmt = $pdo->query("SELECT * FROM sekretariat_agenda $whereSQL ORDER BY tanggal ASC, waktu ASC");
    $agendaList = $stmt->fetchAll();
} catch (PDOException $e) {
    $agendaList = [];
}

function fmtDate($d) {
    if (!$d) return '-';
    $m=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $dt = new DateTime($d); return $dt->format('d').' '.$m[$dt->format('n')].' '.$dt->format('Y');
}
function namaHariAgenda($d) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    return $hari[(int)date('w', strtotime($d))];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Agenda / Jadwal Rapat - RS Taman Harapan Baru</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
<?php include 'includes/sidebar.php'; ?>
<main class="flex-1 flex flex-col h-screen overflow-hidden">
<?php include 'includes/header.php'; ?>
<div class="flex-1 p-6 overflow-y-auto">

<!-- Header -->
<div class="flex items-center justify-between mb-5">
  <div>
    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
      <i data-lucide="calendar-check" class="w-6 h-6 text-teal-600"></i> Agenda / Jadwal Rapat
    </h1>
    <p class="text-sm text-gray-500 mt-0.5">Penjadwalan rapat, agenda, dan reminder acara</p>
  </div>
  <?php if (canUserEditOrDelete('sekretariat')): ?>
  <button onclick="openModalAdd()" class="flex items-center gap-2 bg-teal-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-teal-700 transition-colors shadow-sm text-sm">
    <i data-lucide="plus" class="w-4 h-4"></i> Tambah Agenda
  </button>
  <a href="cetak_daftar_hadir.php" target="_blank"
     class="flex items-center gap-2 bg-white border border-teal-300 text-teal-700 px-4 py-2 rounded-xl font-medium hover:bg-teal-50 transition-colors shadow-sm text-sm">
    <i data-lucide="printer" class="w-4 h-4"></i> Cetak Daftar Hadir Kosong
  </a>
  <a href="cetak_notulen.php" target="_blank"
     class="flex items-center gap-2 bg-white border border-indigo-300 text-indigo-700 px-4 py-2 rounded-xl font-medium hover:bg-indigo-50 transition-colors shadow-sm text-sm">
    <i data-lucide="file-text" class="w-4 h-4"></i> Cetak Notulen Kosong
  </a>
  <?php endif; ?>
</div>

<!-- Flash -->
<?php if (isset($_SESSION['agenda_success'])): ?>
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-sm flex items-center gap-2">
  <i data-lucide="check-circle" class="w-4 h-4"></i> <?= htmlspecialchars($_SESSION['agenda_success']) ?>
</div>
<?php unset($_SESSION['agenda_success']); endif; ?>
<?php if (isset($_SESSION['agenda_error'])): ?>
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-2.5 rounded-xl text-sm flex items-center gap-2">
  <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= htmlspecialchars($_SESSION['agenda_error']) ?>
</div>
<?php unset($_SESSION['agenda_error']); endif; ?>

<!-- Filter tabs -->
<div class="flex gap-2 mb-5">
  <?php foreach (['mendatang'=>'Mendatang','semua'=>'Semua','lampau'=>'Lampau'] as $val=>$label): ?>
  <a href="?filter=<?= $val ?>"
     class="px-4 py-2 rounded-xl text-sm font-semibold transition-all
     <?= $filter===$val ? 'bg-teal-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:border-teal-300 hover:text-teal-600' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Agenda cards -->
<?php if (empty($agendaList)): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-14 text-center text-gray-400">
  <i data-lucide="calendar-off" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
  <p class="text-sm font-medium">Tidak ada agenda untuk ditampilkan.</p>
</div>
<?php else: ?>
<div class="space-y-3">
<?php foreach ($agendaList as $ag):
    $isPast    = $ag['tanggal'] < $today;
    $isToday   = $ag['tanggal'] === $today;
    $reminders = !empty($ag['reminder']) ? json_decode($ag['reminder'], true) : [];
    $peserta   = !empty($ag['peserta'])  ? json_decode($ag['peserta'],  true) : [];
?>
<div class="bg-white rounded-2xl border <?= $isToday?'border-teal-300 shadow-md':'border-gray-100 shadow-sm' ?> overflow-hidden">
  <div class="flex items-stretch">
    <!-- Date block -->
    <div class="flex-shrink-0 w-20 flex flex-col items-center justify-center
        <?= $isToday?'bg-teal-600 text-white':($isPast?'bg-gray-100 text-gray-400':'bg-teal-50 text-teal-700') ?> p-3">
      <span class="text-xs font-semibold uppercase"><?= namaHariAgenda($ag['tanggal']) ?></span>
      <span class="text-2xl font-bold leading-tight"><?= date('d', strtotime($ag['tanggal'])) ?></span>
      <span class="text-xs font-medium"><?= date('M Y', strtotime($ag['tanggal'])) ?></span>
    </div>
    <!-- Content -->
    <div class="flex-1 px-5 py-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h3 class="font-bold text-gray-900 text-base"><?= htmlspecialchars($ag['judul_agenda']) ?></h3>
          <div class="flex flex-wrap items-center gap-3 mt-1.5 text-sm text-gray-500">
            <?php if (!empty($ag['waktu'])): ?>
            <span class="flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i><?= substr($ag['waktu'],0,5) ?> WIB</span>
            <?php endif; ?>
            <?php if (!empty($ag['lokasi'])): ?>
            <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i><?= htmlspecialchars($ag['lokasi']) ?></span>
            <?php endif; ?>
          </div>
          <?php if (!empty($peserta)): ?>
          <div class="mt-2 flex flex-wrap gap-1">
            <?php foreach (array_slice($peserta,0,5) as $p): ?>
            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-xs rounded-full border border-blue-100"><?= htmlspecialchars($p) ?></span>
            <?php endforeach; ?>
            <?php if (count($peserta)>5): ?>
            <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs rounded-full">+<?= count($peserta)-5 ?> lainnya</span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($reminders)): ?>
          <div class="mt-2 flex flex-wrap gap-1">
            <?php foreach ($reminders as $r): ?>
            <span class="px-2 py-0.5 bg-amber-50 text-amber-700 text-xs rounded-full border border-amber-100 flex items-center gap-1">
              <i data-lucide="bell" class="w-3 h-3"></i><?= htmlspecialchars($r) ?>
            </span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($ag['catatan'])): ?>
          <p class="mt-2 text-xs text-gray-400 italic"><?= htmlspecialchars($ag['catatan']) ?></p>
          <?php endif; ?>
        </div>
        <!-- Aksi -->
        <?php if (canUserEditOrDelete('sekretariat')): ?>
        <div class="flex items-center gap-2 flex-shrink-0">
          <a href="cetak_daftar_hadir.php?id=<?= $ag['id'] ?>" target="_blank"
             class="px-3 py-1.5 text-xs bg-teal-50 text-teal-700 rounded-lg hover:bg-teal-100 font-medium transition-colors flex items-center gap-1">
            <i data-lucide="printer" class="w-3 h-3"></i> Daftar Hadir
          </a>
          <a href="cetak_notulen.php?id=<?= $ag['id'] ?>" target="_blank"
             class="px-3 py-1.5 text-xs bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 font-medium transition-colors flex items-center gap-1">
            <i data-lucide="file-text" class="w-3 h-3"></i> Notulen
          </a>
          <button onclick='editAgenda(<?= htmlspecialchars(json_encode($ag), ENT_QUOTES) ?>)'
                  class="px-3 py-1.5 text-xs bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 font-medium transition-colors flex items-center gap-1">
            <i data-lucide="edit-3" class="w-3 h-3"></i> Edit
          </button>
          <form method="POST" class="inline" onsubmit="return confirm('Hapus agenda ini?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $ag['id'] ?>">
            <button type="submit" class="px-3 py-1.5 text-xs bg-red-50 text-red-700 rounded-lg hover:bg-red-100 font-medium transition-colors flex items-center gap-1">
              <i data-lucide="trash-2" class="w-3 h-3"></i> Hapus
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</div><!-- /p-6 -->
</main>

<!-- Modal Tambah/Edit -->
<div id="modalAgenda" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <h2 id="agendaModalTitle" class="text-lg font-bold text-gray-900">Tambah Agenda / Jadwal Rapat</h2>
      <button onclick="closeModal('modalAgenda')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
    </div>
    <form method="POST" class="p-5 space-y-4">
      <input type="hidden" name="action" id="agendaAction" value="add">
      <input type="hidden" name="id" id="agendaId" value="">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal <span class="text-red-500">*</span></label>
          <input type="date" name="tanggal" id="agendaTanggal" required
                 class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Waktu</label>
          <input type="time" name="waktu" id="agendaWaktu"
                 class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Judul Agenda <span class="text-red-500">*</span></label>
          <input type="text" name="judul_agenda" id="agendaJudul" required placeholder="Judul rapat / agenda"
                 class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
          <input type="text" name="lokasi" id="agendaLokasi" placeholder="Ruang rapat, Zoom, dll"
                 class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reminder Acara</label>
          <div class="space-y-1.5 mt-1">
            <?php foreach ($reminderOptions as $ro): ?>
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
              <input type="checkbox" name="reminder[]" value="<?= htmlspecialchars($ro) ?>"
                     class="reminder-cb rounded border-gray-300 text-teal-600 focus:ring-teal-400">
              <?= htmlspecialchars($ro) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Peserta / Email Peserta</label>
          <textarea name="peserta" id="agendaPeserta" rows="3"
                    placeholder="Tulis satu nama / email per baris&#10;Contoh:&#10;dr. Ahmad&#10;nurse.siti@rsthb.id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none"></textarea>
          <p class="text-xs text-gray-400 mt-0.5">Tulis satu peserta / email per baris.</p>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
          <textarea name="catatan" id="agendaCatatan" rows="2" placeholder="Agenda detail, notulen singkat, dll"
                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none"></textarea>
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('modalAgenda')"
                class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 text-sm">Batal</button>
        <button type="submit"
                class="flex-1 px-4 py-2 bg-teal-600 text-white rounded-xl font-medium hover:bg-teal-700 text-sm">Simpan Agenda</button>
      </div>
    </form>
  </div>
</div>

<script>
lucide.createIcons();
function openModal(id) { const e=document.getElementById(id); if(e){e.classList.remove('hidden');e.classList.add('flex');} }
function closeModal(id){ const e=document.getElementById(id); if(e){e.classList.add('hidden');e.classList.remove('flex');} }
function openModalAdd() {
    document.getElementById('agendaAction').value = 'add';
    document.getElementById('agendaId').value     = '';
    document.getElementById('agendaTanggal').value = '';
    document.getElementById('agendaWaktu').value   = '';
    document.getElementById('agendaJudul').value   = '';
    document.getElementById('agendaLokasi').value  = '';
    document.getElementById('agendaPeserta').value = '';
    document.getElementById('agendaCatatan').value = '';
    document.querySelectorAll('.reminder-cb').forEach(cb => cb.checked = false);
    document.getElementById('agendaModalTitle').textContent = 'Tambah Agenda / Jadwal Rapat';
    openModal('modalAgenda');
}
function editAgenda(data) {
    document.getElementById('agendaAction').value = 'edit';
    document.getElementById('agendaId').value     = data.id;
    document.getElementById('agendaTanggal').value = data.tanggal || '';
    document.getElementById('agendaWaktu').value   = data.waktu   || '';
    document.getElementById('agendaJudul').value   = data.judul_agenda || '';
    document.getElementById('agendaLokasi').value  = data.lokasi  || '';
    document.getElementById('agendaCatatan').value = data.catatan || '';
    // Peserta
    let pesertaArr = [];
    try { pesertaArr = data.peserta ? JSON.parse(data.peserta) : []; } catch(e){}
    document.getElementById('agendaPeserta').value = pesertaArr.join('\n');
    // Reminder
    let remArr = [];
    try { remArr = data.reminder ? JSON.parse(data.reminder) : []; } catch(e){}
    document.querySelectorAll('.reminder-cb').forEach(cb => {
        cb.checked = remArr.includes(cb.value);
    });
    document.getElementById('agendaModalTitle').textContent = 'Edit Agenda';
    openModal('modalAgenda');
}
</script>
</body>
</html>
