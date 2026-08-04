<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
if (!hasPermission('sekretariat_view')) { header("Location: dashboard.php"); exit; }

$user = $_SESSION['user'];

$kategoriArsip = [
    'Surat Masuk','Internal Memo','Surat Tugas','Surat Keterangan',
    'Surat Pernyataan','Surat Undangan','Surat Edaran','Surat Kuasa',
    'Sertifikat','Surat Perintah Kerja (SPK)','Berita Acara',
    'Perjanjian Kerjasama (PKS)','SPO','Peraturan Direktur (Perdir)',
    'Surat Keluar','Disposisi','Dokumen Lainnya'
];

// ── Tambah ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' && canUserEditOrDelete('sekretariat')) {
        $tanggal       = $_POST['tanggal'] ?? date('Y-m-d');
        $nama_dokumen  = $_POST['nama_dokumen'] ?? '';
        $dari_asal     = $_POST['dari_asal'] ?? '';
        $nomor_surat   = $_POST['nomor_surat'] ?? '';
        $kategori      = $_POST['kategori'] ?? 'Dokumen Lainnya';
        $keterangan    = $_POST['keterangan'] ?? '';
        $file_path     = null;

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $dir = 'uploads/sekretariat/arsip/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $fname = uniqid() . '_' . basename($_FILES['file']['name']);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dir . $fname)) {
                $file_path = $dir . $fname;
            }
        }

        try {
            $pdo->prepare("INSERT INTO sekretariat_arsip (tanggal, nama_dokumen, dari_asal, nomor_surat, kategori, keterangan, file_path, created_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$tanggal, $nama_dokumen, $dari_asal, $nomor_surat, $kategori, $keterangan, $file_path, $user['nama'] ?? 'Admin']);
            $_SESSION['arsip_success'] = "Arsip berhasil ditambahkan!";
        } catch (PDOException $e) {
            $_SESSION['arsip_error'] = "Gagal: " . $e->getMessage();
        }
        header("Location: sekretariat_arsip.php"); exit;
    }

    if ($action === 'edit' && canUserEditOrDelete('sekretariat')) {
        $id            = (int)$_POST['id'];
        $tanggal       = $_POST['tanggal'] ?? date('Y-m-d');
        $nama_dokumen  = $_POST['nama_dokumen'] ?? '';
        $dari_asal     = $_POST['dari_asal'] ?? '';
        $nomor_surat   = $_POST['nomor_surat'] ?? '';
        $kategori      = $_POST['kategori'] ?? 'Dokumen Lainnya';
        $keterangan    = $_POST['keterangan'] ?? '';

        $stmt = $pdo->prepare("SELECT file_path FROM sekretariat_arsip WHERE id=?");
        $stmt->execute([$id]);
        $file_path = $stmt->fetchColumn();

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $dir = 'uploads/sekretariat/arsip/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $fname = uniqid() . '_' . basename($_FILES['file']['name']);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dir . $fname)) {
                if ($file_path && file_exists($file_path)) unlink($file_path);
                $file_path = $dir . $fname;
            }
        }

        try {
            $pdo->prepare("UPDATE sekretariat_arsip SET tanggal=?,nama_dokumen=?,dari_asal=?,nomor_surat=?,kategori=?,keterangan=?,file_path=? WHERE id=?")
                ->execute([$tanggal, $nama_dokumen, $dari_asal, $nomor_surat, $kategori, $keterangan, $file_path, $id]);
            $_SESSION['arsip_success'] = "Arsip berhasil diperbarui!";
        } catch (PDOException $e) {
            $_SESSION['arsip_error'] = "Gagal: " . $e->getMessage();
        }
        header("Location: sekretariat_arsip.php"); exit;
    }

    if ($action === 'delete' && canUserEditOrDelete('sekretariat')) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT file_path FROM sekretariat_arsip WHERE id=?");
        $stmt->execute([$id]);
        $fp = $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM sekretariat_arsip WHERE id=?")->execute([$id]);
        if ($fp && file_exists($fp)) unlink($fp);
        $_SESSION['arsip_success'] = "Arsip berhasil dihapus.";
        header("Location: sekretariat_arsip.php"); exit;
    }
}

// ── Filter & Query ────────────────────────────────────────
$filter_kat   = $_GET['kategori']  ?? '';
$filter_cari  = $_GET['cari']      ?? '';

$where = []; $params = [];
if ($filter_kat) { $where[] = "kategori = ?"; $params[] = $filter_kat; }
if ($filter_cari) { $where[] = "(nama_dokumen LIKE ? OR nomor_surat LIKE ? OR dari_asal LIKE ?)"; $params = array_merge($params, ["%$filter_cari%","%$filter_cari%","%$filter_cari%"]); }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $stmt = $pdo->prepare("SELECT * FROM sekretariat_arsip $whereSQL ORDER BY tanggal DESC, id DESC");
    $stmt->execute($params);
    $arsipList = $stmt->fetchAll();
    $total = count($arsipList);
} catch (PDOException $e) {
    $arsipList = []; $total = 0;
}

function fmtDate($d) {
    if (!$d) return '-';
    $m=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $dt = new DateTime($d); return $dt->format('d').' '.$m[$dt->format('n')].' '.$dt->format('Y');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Arsip Dokumen - RS Taman Harapan Baru</title>
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
      <i data-lucide="archive" class="w-6 h-6 text-teal-600"></i> Arsip Dokumen
    </h1>
    <p class="text-sm text-gray-500 mt-0.5">Penyimpanan dan pencarian arsip dokumen sekretariat</p>
  </div>
  <?php if (canUserEditOrDelete('sekretariat')): ?>
  <button onclick="openModal('modalAdd')" class="flex items-center gap-2 bg-teal-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-teal-700 transition-colors shadow-sm text-sm">
    <i data-lucide="plus" class="w-4 h-4"></i> Tambah Arsip
  </button>
  <?php endif; ?>
</div>

<!-- Flash -->
<?php if (isset($_SESSION['arsip_success'])): ?>
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-sm flex items-center gap-2">
  <i data-lucide="check-circle" class="w-4 h-4"></i> <?= htmlspecialchars($_SESSION['arsip_success']) ?>
</div>
<?php unset($_SESSION['arsip_success']); endif; ?>
<?php if (isset($_SESSION['arsip_error'])): ?>
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-2.5 rounded-xl text-sm flex items-center gap-2">
  <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= htmlspecialchars($_SESSION['arsip_error']) ?>
</div>
<?php unset($_SESSION['arsip_error']); endif; ?>

<!-- Filter -->
<form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 mb-4">
  <div class="flex flex-wrap items-end gap-3">
    <div class="flex-1 min-w-[200px]">
      <label class="block text-xs font-semibold text-gray-600 mb-1">Cari</label>
      <input type="text" name="cari" value="<?= htmlspecialchars($filter_cari) ?>"
             placeholder="Nama dokumen / nomor surat / asal..."
             class="w-full border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-teal-400">
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Kategori</label>
      <select name="kategori" class="border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-teal-400">
        <option value="">Semua Kategori</option>
        <?php foreach ($kategoriArsip as $kat): ?>
        <option value="<?= htmlspecialchars($kat) ?>" <?= $filter_kat===$kat?'selected':'' ?>><?= htmlspecialchars($kat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="px-5 py-2 bg-teal-600 text-white rounded-xl font-semibold text-sm hover:bg-teal-700 flex items-center gap-1.5">
      <i data-lucide="search" class="w-4 h-4"></i> Cari
    </button>
    <?php if ($filter_kat || $filter_cari): ?>
    <a href="sekretariat_arsip.php" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-50">Reset</a>
    <?php endif; ?>
  </div>
</form>

<!-- Tabel -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
  <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
    <span class="text-sm font-semibold text-gray-700"><?= number_format($total) ?> Dokumen Arsip</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-b">No</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-b">Tanggal</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-b">Nama Dokumen</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-b">Dari / Asal</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-b">Nomor Surat</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-b">Kategori</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-b">File</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-b">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($arsipList)): ?>
        <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">Belum ada data arsip.</td></tr>
        <?php else: ?>
        <?php foreach ($arsipList as $i => $a): ?>
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-3 text-sm text-gray-500"><?= $i+1 ?></td>
          <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap"><?= fmtDate($a['tanggal']) ?></td>
          <td class="px-4 py-3 text-sm font-medium text-gray-800 max-w-[200px]">
            <p class="truncate" title="<?= htmlspecialchars($a['nama_dokumen']) ?>"><?= htmlspecialchars($a['nama_dokumen']) ?></p>
            <?php if (!empty($a['keterangan'])): ?>
            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($a['keterangan']) ?></p>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($a['dari_asal'] ?: '-') ?></td>
          <td class="px-4 py-3 text-sm text-gray-600 font-mono"><?= htmlspecialchars($a['nomor_surat'] ?: '-') ?></td>
          <td class="px-4 py-3">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-teal-50 text-teal-700 border border-teal-100">
              <?= htmlspecialchars($a['kategori']) ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <?php if (!empty($a['file_path'])): ?>
            <a href="<?= htmlspecialchars($a['file_path']) ?>" target="_blank"
               class="inline-flex items-center gap-1 text-xs text-emerald-600 hover:text-emerald-700 font-medium">
              <i data-lucide="download" class="w-3.5 h-3.5"></i> Unduh
            </a>
            <?php else: ?>
            <span class="text-gray-400 text-xs">–</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1.5">
              <?php if (canUserEditOrDelete('sekretariat')): ?>
              <button onclick='editArsip(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)'
                      class="px-2.5 py-1 text-xs bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors font-medium">Edit</button>
              <form method="POST" class="inline" onsubmit="return confirm('Hapus arsip ini?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="submit" class="px-2.5 py-1 text-xs bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition-colors font-medium">Hapus</button>
              </form>
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

</div><!-- /p-6 -->
</main>

<!-- Modal Tambah -->
<div id="modalAdd" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <h2 id="modalAddTitle" class="text-lg font-bold text-gray-900">Tambah Arsip Dokumen</h2>
      <button onclick="closeModal('modalAdd')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
      <input type="hidden" name="action" id="formAction" value="add">
      <input type="hidden" name="id" id="arsipId" value="">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal <span class="text-red-500">*</span></label>
          <input type="date" name="tanggal" id="arsipTanggal" required
                 class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kategori Surat <span class="text-red-500">*</span></label>
          <select name="kategori" id="arsipKategori" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none bg-white">
            <?php foreach ($kategoriArsip as $kat): ?>
            <option value="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama Dokumen <span class="text-red-500">*</span></label>
          <input type="text" name="nama_dokumen" id="arsipNama" required placeholder="Judul / nama dokumen"
                 class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Dari / Asal</label>
          <input type="text" name="dari_asal" id="arsipAsal" placeholder="Instansi atau unit pengirim"
                 class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Surat</label>
          <input type="text" name="nomor_surat" id="arsipNomor" placeholder="Nomor surat / dokumen"
                 class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
          <textarea name="keterangan" id="arsipKeterangan" rows="2" placeholder="Keterangan tambahan..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 outline-none"></textarea>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Upload Dokumen</label>
          <input type="file" name="file" id="arsipFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                 class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
          <p class="text-xs text-gray-400 mt-1">Format: PDF, DOC, DOCX, JPG, PNG. Biarkan kosong saat edit jika tidak ingin mengganti file.</p>
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('modalAdd')"
                class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 text-sm">Batal</button>
        <button type="submit"
                class="flex-1 px-4 py-2 bg-teal-600 text-white rounded-xl font-medium hover:bg-teal-700 text-sm">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
lucide.createIcons();
function openModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
}
function editArsip(data) {
    document.getElementById('formAction').value      = 'edit';
    document.getElementById('arsipId').value         = data.id;
    document.getElementById('arsipTanggal').value    = data.tanggal || '';
    document.getElementById('arsipKategori').value   = data.kategori || '';
    document.getElementById('arsipNama').value       = data.nama_dokumen || '';
    document.getElementById('arsipAsal').value       = data.dari_asal || '';
    document.getElementById('arsipNomor').value      = data.nomor_surat || '';
    document.getElementById('arsipKeterangan').value = data.keterangan || '';
    document.getElementById('arsipFile').value       = '';
    document.getElementById('modalAddTitle').textContent = 'Edit Arsip Dokumen';
    openModal('modalAdd');
}
</script>
</body>
</html>
