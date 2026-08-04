<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
if (!hasPermission('sekretariat_view') && !hasPermission('corsec_view')) {
    header("Location: dashboard.php"); exit;
}

// ── Kategori surat masuk & keluar ────────────────────────
$kategoriMasuk = [
    'Surat Masuk','Internal Memo','Surat Tugas','Surat Keterangan',
    'Surat Pernyataan','Surat Undangan','Surat Edaran','Surat Kuasa',
    'Sertifikat','Surat Perintah Kerja (SPK)','Berita Acara',
    'Perjanjian Kerjasama (PKS)','SPO','Peraturan Direktur (Perdir)'
];
$kategoriKeluar = [
    'Internal Memo','Disposisi','Surat Keluar','Surat Tugas','Surat Keterangan',
    'Surat Pernyataan','Surat Undangan','Surat Edaran','Surat Kuasa',
    'Sertifikat','Surat Perintah Kerja (SPK)','Berita Acara',
    'Perjanjian Kerjasama (PKS)','SPO','Peraturan Direktur (Perdir)'
];

// ── Statistik ────────────────────────────────────────────
$stats = [];
try {
    // Total surat masuk
    $masukIn = implode(',', array_fill(0, count($kategoriMasuk), '?'));
    $st = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($masukIn)");
    $st->execute($kategoriMasuk);
    $stats['total_masuk'] = (int)$st->fetchColumn();

    // Total surat keluar
    $keluarIn = implode(',', array_fill(0, count($kategoriKeluar), '?'));
    $st = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($keluarIn)");
    $st->execute($kategoriKeluar);
    $stats['total_keluar'] = (int)$st->fetchColumn();

    // Pending tindak lanjut
    $allKat = array_unique(array_merge($kategoriMasuk, $kategoriKeluar));
    $allIn  = implode(',', array_fill(0, count($allKat), '?'));
    $params = array_merge($allKat, ['Pending','Dalam Proses']);
    $st = $pdo->prepare("SELECT COUNT(*) FROM manajemen_surat WHERE kategori IN ($allIn) AND status_tindak_lanjut IN (?,?)");
    $st->execute($params);
    $stats['pending'] = (int)$st->fetchColumn();

    // Total arsip
    $st = $pdo->query("SELECT COUNT(*) FROM sekretariat_arsip");
    $stats['total_arsip'] = (int)$st->fetchColumn();
} catch (Exception $e) {
    $stats = ['total_masuk'=>0,'total_keluar'=>0,'pending'=>0,'total_arsip'=>0];
}

// ── Agenda mendatang ─────────────────────────────────────
$agendaMendatang = [];
try {
    $st = $pdo->prepare("SELECT * FROM sekretariat_agenda WHERE tanggal >= CURDATE() ORDER BY tanggal ASC, waktu ASC LIMIT 5");
    $st->execute();
    $agendaMendatang = $st->fetchAll();
} catch (Exception $e) { $agendaMendatang = []; }

// ── Surat masuk terbaru ──────────────────────────────────
$suratTerbaru = [];
try {
    $masukIn = implode(',', array_fill(0, count($kategoriMasuk), '?'));
    $st = $pdo->prepare("SELECT * FROM manajemen_surat WHERE kategori IN ($masukIn) ORDER BY created_at DESC LIMIT 8");
    $st->execute($kategoriMasuk);
    $suratTerbaru = $st->fetchAll();
} catch (Exception $e) { $suratTerbaru = []; }

// ── Per-kategori masuk ───────────────────────────────────
$perKategori = [];
try {
    $masukIn = implode(',', array_fill(0, count($kategoriMasuk), '?'));
    $st = $pdo->prepare("SELECT kategori, COUNT(*) as total FROM manajemen_surat WHERE kategori IN ($masukIn) GROUP BY kategori ORDER BY total DESC");
    $st->execute($kategoriMasuk);
    foreach ($st->fetchAll() as $r) $perKategori[$r['kategori']] = $r['total'];
} catch (Exception $e) { $perKategori = []; }

$bulanList=[1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
            7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard Sekretariat - RS Taman Harapan Baru</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
<?php include 'includes/sidebar.php'; ?>
<main class="flex-1 flex flex-col h-screen overflow-hidden">
<?php include 'includes/header.php'; ?>
<div class="flex-1 p-6 overflow-y-auto">

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
      <i data-lucide="layout-dashboard" class="w-6 h-6 text-teal-600"></i>
      Dashboard Sekretariat
    </h1>
    <p class="text-sm text-gray-500 mt-0.5">Ringkasan semua aktivitas kesekretariatan & corporate secretary</p>
  </div>
  <span class="text-xs text-gray-400"><?= date('l, d F Y') ?></span>
</div>

<!-- Stat cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $cards = [
    ['label'=>'Total Surat Masuk',  'val'=>$stats['total_masuk'],  'icon'=>'mail',          'from'=>'from-blue-500',   'to'=>'to-blue-600',   'href'=>'surat-masuk.php'],
    ['label'=>'Total Surat Keluar', 'val'=>$stats['total_keluar'], 'icon'=>'send',          'from'=>'from-purple-500', 'to'=>'to-purple-600', 'href'=>'surat-keluar.php'],
    ['label'=>'Perlu Tindak Lanjut','val'=>$stats['pending'],      'icon'=>'alert-triangle','from'=>'from-amber-500',  'to'=>'to-orange-500', 'href'=>'surat-masuk.php'],
    ['label'=>'Total Arsip',        'val'=>$stats['total_arsip'],  'icon'=>'archive',       'from'=>'from-teal-500',   'to'=>'to-emerald-600','href'=>'sekretariat_arsip.php'],
  ];
  foreach ($cards as $c): ?>
  <a href="<?= $c['href'] ?>" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4 hover:shadow-md transition-shadow group">
    <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $c['from'].' '.$c['to'] ?> flex items-center justify-center shadow-sm flex-shrink-0">
      <i data-lucide="<?= $c['icon'] ?>" class="w-6 h-6 text-white"></i>
    </div>
    <div>
      <p class="text-xs text-gray-500 font-medium"><?= $c['label'] ?></p>
      <p class="text-2xl font-bold text-gray-900 group-hover:text-teal-600 transition-colors"><?= number_format($c['val']) ?></p>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Quick links -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
  <?php
  $links = [
    ['label'=>'Surat Masuk',   'icon'=>'mail',          'href'=>'surat-masuk.php',         'color'=>'text-blue-600   bg-blue-50   border-blue-100'],
    ['label'=>'Surat Keluar',  'icon'=>'send',          'href'=>'surat-keluar.php',        'color'=>'text-purple-600 bg-purple-50 border-purple-100'],
    ['label'=>'Arsip Dokumen', 'icon'=>'archive',       'href'=>'sekretariat_arsip.php',   'color'=>'text-teal-600   bg-teal-50   border-teal-100'],
    ['label'=>'Agenda Rapat',  'icon'=>'calendar',      'href'=>'sekretariat_agenda.php',  'color'=>'text-emerald-600 bg-emerald-50 border-emerald-100'],
    ['label'=>'Corp. Secretary','icon'=>'building-2',   'href'=>'corsec.php',              'color'=>'text-indigo-600 bg-indigo-50 border-indigo-100'],
    ['label'=>'Tambah Surat',  'icon'=>'plus-circle',   'href'=>'surat-masuk.php',         'color'=>'text-orange-600 bg-orange-50 border-orange-100'],
  ];
  foreach ($links as $l): ?>
  <a href="<?= $l['href'] ?>" class="flex flex-col items-center gap-2 p-4 bg-white rounded-2xl border <?= $l['color'] ?> hover:shadow-md transition-all text-center">
    <i data-lucide="<?= $l['icon'] ?>" class="w-6 h-6"></i>
    <span class="text-xs font-semibold"><?= $l['label'] ?></span>
  </a>
  <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

  <!-- Surat terbaru -->
  <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-semibold text-gray-800 flex items-center gap-2">
        <i data-lucide="clock" class="w-4 h-4 text-teal-600"></i> Surat Masuk Terbaru
      </h3>
      <a href="surat-masuk.php" class="text-xs text-teal-600 hover:underline font-medium">Lihat semua →</a>
    </div>
    <div class="divide-y divide-gray-50">
      <?php if (empty($suratTerbaru)): ?>
      <div class="px-5 py-10 text-center text-gray-400 text-sm">Belum ada surat masuk.</div>
      <?php else: ?>
      <?php foreach ($suratTerbaru as $s): ?>
      <div class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50">
        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5">
          <i data-lucide="mail" class="w-4 h-4 text-blue-600"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($s['perihal']) ?></p>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($s['asal_pengirim']) ?> · <?= date('d M Y', strtotime($s['tanggal_surat'])) ?></p>
        </div>
        <span class="text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0
          <?= $s['status_tindak_lanjut']==='Selesai'?'bg-emerald-100 text-emerald-700':($s['status_tindak_lanjut']==='Pending'?'bg-amber-100 text-amber-700':'bg-blue-100 text-blue-700') ?>">
          <?= htmlspecialchars($s['status_tindak_lanjut']) ?>
        </span>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Agenda mendatang -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-semibold text-gray-800 flex items-center gap-2">
        <i data-lucide="calendar-check" class="w-4 h-4 text-emerald-600"></i> Agenda Mendatang
      </h3>
      <a href="sekretariat_agenda.php" class="text-xs text-teal-600 hover:underline font-medium">Semua →</a>
    </div>
    <div class="divide-y divide-gray-50">
      <?php if (empty($agendaMendatang)): ?>
      <div class="px-5 py-10 text-center text-gray-400 text-sm">Tidak ada agenda mendatang.</div>
      <?php else: ?>
      <?php foreach ($agendaMendatang as $ag): ?>
      <div class="px-5 py-3">
        <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($ag['judul_agenda']) ?></p>
        <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-1">
          <i data-lucide="calendar" class="w-3 h-3"></i>
          <?= date('d M Y', strtotime($ag['tanggal'])) ?>
          <?php if (!empty($ag['waktu'])): ?> · <?= substr($ag['waktu'],0,5) ?><?php endif; ?>
        </p>
        <?php if (!empty($ag['lokasi'])): ?>
        <p class="text-xs text-gray-400 flex items-center gap-1 mt-0.5">
          <i data-lucide="map-pin" class="w-3 h-3"></i> <?= htmlspecialchars($ag['lokasi']) ?>
        </p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /grid -->

<!-- Distribusi per kategori -->
<?php if (!empty($perKategori)): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mt-5">
  <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
    <i data-lucide="bar-chart-2" class="w-4 h-4 text-teal-600"></i> Distribusi Surat Masuk per Kategori
  </h3>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
    <?php $maxVal = max($perKategori) ?: 1; ?>
    <?php foreach ($perKategori as $kat => $total): ?>
    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
      <p class="text-xs text-gray-500 font-medium mb-1 truncate" title="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></p>
      <p class="text-xl font-bold text-teal-700"><?= $total ?></p>
      <div class="mt-2 h-1.5 bg-gray-200 rounded-full overflow-hidden">
        <div class="h-full bg-teal-500 rounded-full" style="width:<?= round($total/$maxVal*100) ?>%"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</div><!-- /p-6 -->
</main>
<script>lucide.createIcons();</script>
</body>
</html>
