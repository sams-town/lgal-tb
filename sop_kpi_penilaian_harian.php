<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
if (!hasPermission('sop_view')) { header("Location: dashboard.php"); exit; }

$user     = $_SESSION['user'];
$userRole = $user['nama_role'] ?? $user['role'] ?? '';
$userId   = (int)($user['id'] ?? 0);

$isAdminHRD = in_array($userRole, ['Super Admin','Admin HRD','HRD']);
$isAtasan   = in_array($userRole, ['Atasan','Supervisor','Kepala Unit','Kepala Ruangan']);
$isKaryawan = !$isAdminHRD && !$isAtasan;
$canEdit    = $isAdminHRD || $isAtasan;

// ── Simpan ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save' && $canEdit) {
    $kid   = (int)$_POST['karyawan_id'];
    $bln   = (int)$_POST['bulan'];
    $thn   = (int)$_POST['tahun'];
    $nilai = $_POST['nilai'] ?? [];
    if ($isAtasan) {
        $chk = $pdo->prepare("SELECT id FROM kpi_karyawan WHERE id=? AND atasan_id=?");
        $chk->execute([$kid,$userId]);
        if (!$chk->fetch()) { $error="Tidak memiliki izin menilai karyawan ini."; goto render; }
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM kpi_penilaian_harian WHERE karyawan_id=? AND bulan=? AND tahun=?")
            ->execute([$kid,$bln,$thn]);
        $ins = $pdo->prepare("INSERT INTO kpi_penilaian_harian (karyawan_id,kriteria_id,hari,bulan,tahun,nilai,created_by) VALUES(?,?,?,?,?,?,?)");
        foreach ($nilai as $kr_id => $hari_arr) {
            foreach ($hari_arr as $hari => $val) {
                $val = trim($val);
                if ($val!=='') $ins->execute([$kid,(int)$kr_id,(int)$hari,$bln,$thn,$val,$user['nama']??'Admin']);
            }
        }
        $pdo->commit();
        header("Location: sop_kpi_penilaian_harian.php?karyawan_id={$kid}&bulan={$bln}&tahun={$thn}&success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Kesalahan: ".$e->getMessage();
    }
}
render:

// ── Parameter ───────────────────────────────────────────
$bulan_sel   = (int)($_GET['bulan']       ?? date('m'));
$tahun_sel   = (int)($_GET['tahun']       ?? date('Y'));
$karyawan_id = (int)($_GET['karyawan_id'] ?? 0);
$jumlah_hari = (int)date('t', mktime(0,0,0,$bulan_sel,1,$tahun_sel));

function namaHari(int $h, int $b, int $y): string {
    return ['M','S','S','R','K','J','S'][(int)date('w',mktime(0,0,0,$b,$h,$y))];
}
function isWeekend(int $h, int $b, int $y): bool {
    $d = (int)date('w',mktime(0,0,0,$b,$h,$y));
    return $d===0||$d===6;
}

// ── Karyawan list ────────────────────────────────────────
if ($isAdminHRD) {
    $stK = $pdo->query("SELECT id,nama,jabatan,unit FROM kpi_karyawan WHERE status='Aktif' ORDER BY nama ASC");
} elseif ($isAtasan) {
    $stK = $pdo->prepare("SELECT id,nama,jabatan,unit FROM kpi_karyawan WHERE status='Aktif' AND atasan_id=? ORDER BY nama ASC");
    $stK->execute([$userId]);
} else {
    $stK = $pdo->prepare("SELECT id,nama,jabatan,unit FROM kpi_karyawan WHERE status='Aktif' AND user_id=? LIMIT 1");
    $stK->execute([$userId]);
}
$karyawanList = $stK->fetchAll();
if (!$karyawan_id && $isKaryawan && count($karyawanList)===1) $karyawan_id = $karyawanList[0]['id'];

// ── Kriteria ─────────────────────────────────────────────
$kriteriaRaw = $pdo->query("SELECT * FROM kpi_kriteria ORDER BY kategori ASC, id ASC")->fetchAll();
$byKategori  = [];
foreach ($kriteriaRaw as $kr) $byKategori[$kr['kategori']][] = $kr;

// ── Nilai tersimpan ──────────────────────────────────────
$nilaiDB = [];
if ($karyawan_id) {
    try {
        $st = $pdo->prepare("SELECT kriteria_id,hari,nilai FROM kpi_penilaian_harian WHERE karyawan_id=? AND bulan=? AND tahun=?");
        $st->execute([$karyawan_id,$bulan_sel,$tahun_sel]);
        foreach ($st->fetchAll() as $r) $nilaiDB[$r['kriteria_id']][$r['hari']] = $r['nilai'];
    } catch (PDOException $e) { $nilaiDB=[]; }
}

// ── Rata-rata RKK bulan ini ──────────────────────────────
// Hitung dari kpi_rkk_log: (jumlah tugas yang dilakukan / total tugas) * 5, rata-rata per hari
$rataRKK = null;
$rkkPerHari = []; // [hari => nilai 0-5]
if ($karyawan_id) {
    try {
        // Total tugas RKK karyawan ini
        $stTotal = $pdo->prepare("SELECT COUNT(*) FROM kpi_rkk_karyawan WHERE karyawan_id=?");
        $stTotal->execute([$karyawan_id]);
        $totalTugas = (int)$stTotal->fetchColumn();

        if ($totalTugas > 0) {
            // Log per hari bulan ini
            $stLog = $pdo->prepare("
                SELECT hari, COUNT(DISTINCT rkk_id) as jumlah_dikerjakan
                FROM kpi_rkk_log
                WHERE karyawan_id=? AND bulan=? AND tahun=?
                GROUP BY hari
            ");
            $stLog->execute([$karyawan_id, $bulan_sel, $tahun_sel]);
            $logRows = $stLog->fetchAll();

            foreach ($logRows as $row) {
                // Nilai = (tugas dikerjakan / total tugas) * 5, dibulatkan 1 desimal
                $nilaiHari = round(($row['jumlah_dikerjakan'] / $totalTugas) * 5, 1);
                $rkkPerHari[(int)$row['hari']] = $nilaiHari;
            }

            if (!empty($rkkPerHari)) {
                $rataRKK = round(array_sum($rkkPerHari) / count($rkkPerHari), 1);
            }
        }
    } catch (Exception $e) { $rataRKK = null; $rkkPerHari = []; }
}

// ── Info karyawan terpilih ───────────────────────────────
$karyawanTerpilih = null;
foreach ($karyawanList as $k) { if ($k['id']==$karyawan_id) { $karyawanTerpilih=$k; break; } }

$bulanList=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
            7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$noUrut = 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Penilaian Harian KPI - RS Taman Harapan Baru</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
/* ── Tabel utama ── */
.tbl { border-collapse:collapse; min-width:100%; }
.tbl th, .tbl td {
    padding:0 !important;
    border:1px solid #e2e8f0 !important;
    font-size:12px !important;
    white-space:nowrap;
    background:#fff;
}
/* Header baris */
.tbl thead th {
    background:#f8fafc !important;
    font-weight:700 !important;
    color:#475569 !important;
    text-align:center !important;
    padding:8px 4px !important;
    position:sticky; top:0; z-index:3;
}
/* Freeze kolom NO, INDIKATOR, BOBOT */
.tbl th:nth-child(1),.tbl td:nth-child(1){position:sticky;left:0;    z-index:2;width:36px; text-align:center!important;}
.tbl th:nth-child(2),.tbl td:nth-child(2){position:sticky;left:36px; z-index:2;width:160px;text-align:left!important;}
.tbl th:nth-child(3),.tbl td:nth-child(3){position:sticky;left:196px;z-index:2;width:60px; text-align:center!important;}
.tbl thead th:nth-child(1),.tbl thead th:nth-child(2),.tbl thead th:nth-child(3){z-index:4!important;}
/* Cell isi */
.tbl td:nth-child(1){color:#64748b;font-weight:600;padding:10px 4px!important;}
.tbl td:nth-child(2){color:#1e293b;font-size:12px!important;padding:8px 10px!important;white-space:normal;}
.tbl td:nth-child(3){color:#475569;font-weight:600;padding:8px 4px!important;}
/* Kategori header */
.row-kat td {
    background:#d9e8e6 !important;
    font-weight:800 !important;
    font-size:11px !important;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:#1a4040 !important;
    text-align:left !important;
    padding:7px 12px !important;
}
/* Kolom hari */
.col-day { width:38px; text-align:center!important; }
/* Input kotak */
input.nbox {
    width:32px!important; height:30px!important;
    text-align:center!important;
    border:1px solid #cbd5e1!important;
    border-radius:6px!important;
    padding:0!important;
    font-size:12px!important;
    background:#fff!important;
    display:block; margin:3px auto;
    transition:border-color .15s,box-shadow .15s;
}
input.nbox:focus {
    border-color:#0d9488!important;
    box-shadow:0 0 0 2px rgba(13,148,136,.18)!important;
    outline:none!important;
}
.nbox-ro {
    display:block; margin:3px auto;
    width:32px; height:30px; line-height:30px;
    text-align:center;
    border:1px solid #e2e8f0; border-radius:6px;
    background:#f1f5f9; font-size:12px; color:#334155;
}
/* Weekend */
.we-col { background:#fafafa!important; }
.we-hd  { color:#ef4444!important; }
/* Baris zebra */
.tbl tbody tr:nth-child(even) td { background:#fcfcfc; }
.tbl tbody tr:hover td { background:#f0fdfa!important; }
/* RKK special row */
.rkk-row td { background:#f0fdf4!important; color:#166534!important; }
</style>
</head>
<body class="min-h-screen bg-gray-50 flex">
<?php include 'includes/sidebar.php'; ?>
<main class="flex-1 flex flex-col h-screen overflow-hidden">
<?php include 'includes/header.php'; ?>
<div class="flex-1 p-5 overflow-y-auto">

<!-- ── Page header ── -->
<div class="flex items-center justify-between mb-4">
  <div>
    <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
      <i data-lucide="clipboard-check" class="w-5 h-5 text-teal-600"></i>
      Penilaian Harian KPI
    </h1>
    <p class="text-xs text-gray-500 mt-0.5">
      <?php if($isAdminHRD): ?>Admin HRD — semua karyawan
      <?php elseif($isAtasan): ?>Atasan — karyawan Anda
      <?php else: ?>Mode lihat — nilai kinerja Anda
      <?php endif; ?>
    </p>
  </div>
  <span class="px-3 py-1 rounded-full text-xs font-bold
    <?= $isAdminHRD?'bg-purple-100 text-purple-700':($isAtasan?'bg-blue-100 text-blue-700':'bg-teal-100 text-teal-700') ?>">
    <?= htmlspecialchars($userRole) ?>
  </span>
</div>

<?php if(isset($_GET['success'])): ?>
<div class="mb-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl flex items-center gap-2 text-sm">
  <i data-lucide="check-circle" class="w-4 h-4"></i> Penilaian harian berhasil disimpan!
</div>
<?php endif; ?>
<?php if(!empty($error)): ?>
<div class="mb-3 bg-red-50 border border-red-200 text-red-700 px-4 py-2.5 rounded-xl flex items-center gap-2 text-sm">
  <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- ── Filter ── -->
<form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 mb-4">
  <div class="flex flex-wrap items-end gap-3">
    <?php if(!$isKaryawan): ?>
    <div class="flex-1 min-w-[200px]">
      <label class="block text-xs font-semibold text-gray-600 mb-1">Karyawan</label>
      <select name="karyawan_id" class="w-full border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-teal-400">
        <option value="">-- Pilih Karyawan --</option>
        <?php foreach($karyawanList as $k): ?>
        <option value="<?=$k['id']?>" <?=$karyawan_id==$k['id']?'selected':''?>>
          <?=htmlspecialchars($k['nama'].' ('.$k['unit'].')')?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php elseif($karyawanTerpilih): ?>
    <input type="hidden" name="karyawan_id" value="<?=$karyawan_id?>">
    <?php endif; ?>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
      <select name="bulan" class="border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-teal-400">
        <?php foreach($bulanList as $b=>$nm): ?>
        <option value="<?=$b?>" <?=$bulan_sel==$b?'selected':''?>><?=$nm?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
      <select name="tahun" class="border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-teal-400">
        <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
        <option value="<?=$y?>" <?=$tahun_sel==$y?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </div>
    <button type="submit" class="px-5 py-2 bg-teal-600 text-white rounded-xl font-semibold text-sm hover:bg-teal-700 transition-colors flex items-center gap-1.5">
      <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
    </button>
  </div>
</form>

<?php if($karyawan_id && $karyawanTerpilih && !empty($kriteriaRaw)): ?>

<!-- ── Info karyawan ── -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-3 mb-3 flex items-center gap-4">
  <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-teal-500 to-emerald-600 flex items-center justify-center text-white font-bold text-base shadow-sm">
    <?=mb_strtoupper(mb_substr($karyawanTerpilih['nama'],0,1))?>
  </div>
  <div class="flex-1">
    <p class="font-bold text-gray-800 text-sm"><?=htmlspecialchars($karyawanTerpilih['nama'])?></p>
    <p class="text-xs text-gray-500"><?=htmlspecialchars($karyawanTerpilih['jabatan'].' · '.$karyawanTerpilih['unit'])?></p>
  </div>
  <div class="text-right">
    <p class="text-sm font-semibold text-teal-700"><?=$bulanList[$bulan_sel].' '.$tahun_sel?></p>
    <p class="text-xs text-gray-400"><?=$jumlah_hari?> hari kerja</p>
  </div>
  <?php if($isKaryawan): ?>
  <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-lg text-xs font-medium flex items-center gap-1">
    <i data-lucide="eye" class="w-3 h-3"></i>Mode Lihat
  </span>
  <?php endif; ?>
</div>

<?php if($canEdit): ?>
<form method="POST">
<input type="hidden" name="action" value="save">
<input type="hidden" name="karyawan_id" value="<?=$karyawan_id?>">
<input type="hidden" name="bulan" value="<?=$bulan_sel?>">
<input type="hidden" name="tahun" value="<?=$tahun_sel?>">
<?php endif; ?>

<!-- ── Tabel penilaian ── -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-3">
  <div class="overflow-x-auto" style="max-height:calc(100vh - 340px)">
    <table class="tbl">
      <thead>
        <!-- Baris 1: Nomor hari -->
        <tr>
          <th style="min-width:36px">NO</th>
          <th style="min-width:160px;text-align:left!important;padding-left:12px!important">KATEGORI / INDIKATOR</th>
          <th style="min-width:60px">BOBOT</th>
          <?php for($h=1;$h<=$jumlah_hari;$h++):
            $we=isWeekend($h,$bulan_sel,$tahun_sel); ?>
          <th class="col-day <?=$we?'we-col':''?>" style="font-weight:700"><?=$h?></th>
          <?php endfor; ?>
        </tr>
        <!-- Baris 2: Nama hari -->
        <tr>
          <th></th><th></th><th></th>
          <?php for($h=1;$h<=$jumlah_hari;$h++):
            $we=isWeekend($h,$bulan_sel,$tahun_sel); ?>
          <th class="col-day <?=$we?'we-col':''?>" style="font-size:10px!important;font-weight:500!important;color:<?=$we?'#ef4444':'#94a3b8'?>!important">
            <?=namaHari($h,$bulan_sel,$tahun_sel)?>
          </th>
          <?php endfor; ?>
        </tr>
      </thead>
      <tbody>
      <?php $noUrut=1; ?>
      <?php foreach($byKategori as $kategori => $items): ?>
        <!-- Header kategori -->
        <tr class="row-kat">
          <td colspan="<?=3+$jumlah_hari?>"><?=htmlspecialchars($kategori)?></td>
        </tr>
        <?php foreach($items as $kr): ?>
        <?php $isRKK = (stripos($kr['nama_indikator'],'RKK')!==false || stripos($kr['nama_indikator'],'Job Des')!==false); ?>
        <tr class="<?=$isRKK?'rkk-row':''?>">
          <td><?=$noUrut++?></td>
          <td style="white-space:normal;min-width:160px">
            <?=htmlspecialchars($kr['nama_indikator'])?>
            <?php if($isRKK): ?>
            <br><span style="font-size:10px;color:#16a34a;font-weight:400">(otomatis dari Log RKK/Job Des)</span>
            <?php if($rataRKK!==null): ?>
            <br><span style="font-size:10px;color:#166534;font-weight:600">Rata-rata: <?=number_format($rataRKK,1)?></span>
            <?php endif; ?>
            <?php endif; ?>
          </td>
          <td><?=number_format($kr['bobot'],1)?>%</td>
          <?php if($isRKK): ?>
          <!-- RKK: tidak ada input, tampilkan nilai per hari dari log -->
          <?php for($h=1;$h<=$jumlah_hari;$h++):
            $we=isWeekend($h,$bulan_sel,$tahun_sel);
            $vRkk = $rkkPerHari[$h] ?? null; ?>
          <td class="col-day <?=$we?'we-col':''?>" style="padding:3px!important;background:#f0fdf4!important">
            <span class="nbox-ro" style="background:<?=$vRkk!==null?'#dcfce7':'#f0fdf4'?>;color:<?=$vRkk!==null?'#166534':'#86efac'?>;border-color:<?=$vRkk!==null?'#86efac':'#e2e8f0'?>">
              <?=$vRkk!==null ? $vRkk : ''?>
            </span>
          </td>
          <?php endfor; ?>
          <?php else: ?>
          <?php for($h=1;$h<=$jumlah_hari;$h++):
            $we=isWeekend($h,$bulan_sel,$tahun_sel);
            $val=$nilaiDB[$kr['id']][$h]??''; ?>
          <td class="col-day <?=$we?'we-col':''?>" style="padding:3px!important">
            <?php if($canEdit): ?>
            <input type="text" class="nbox" name="nilai[<?=$kr['id']?>][<?=$h?>]"
                   value="<?=htmlspecialchars($val)?>" maxlength="2" inputmode="numeric"
                   title="<?=htmlspecialchars($kr['nama_indikator'])?> - Hari <?=$h?>">
            <?php else: ?>
            <span class="nbox-ro"><?=htmlspecialchars($val)?></span>
            <?php endif; ?>
          </td>
          <?php endfor; ?>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Keterangan + Tombol simpan -->
<div class="flex items-center justify-between mb-6">
  <p class="text-xs text-gray-400 italic">Isi nilai (0–5) untuk setiap indikator pada tanggal yang relevan di bulan berjalan.</p>
  <?php if($canEdit): ?>
  <div class="flex gap-2">
    <a href="sop_kpi_penilaian_harian.php?karyawan_id=<?=$karyawan_id?>&bulan=<?=$bulan_sel?>&tahun=<?=$tahun_sel?>"
       class="px-4 py-2 border border-gray-300 text-gray-600 rounded-xl hover:bg-gray-50 font-medium text-sm flex items-center gap-1.5">
      <i data-lucide="rotate-ccw" class="w-4 h-4"></i>Reset
    </a>
    <button type="submit" class="px-5 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-semibold shadow text-sm flex items-center gap-1.5">
      <i data-lucide="save" class="w-4 h-4"></i>Simpan Penilaian
    </button>
  </div>
  <?php endif; ?>
</div>

<?php if($canEdit): ?></form><?php endif; ?>

<?php elseif($karyawan_id && empty($kriteriaRaw)): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-4 rounded-xl text-sm flex items-start gap-2">
  <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
  Belum ada indikator. <a href="migrate_kpi_indikator.php" class="underline font-semibold ml-1">Klik di sini untuk import indikator.</a>
</div>
<?php elseif($isKaryawan && empty($karyawanList)): ?>
<div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-4 rounded-xl text-sm flex items-start gap-2">
  <i data-lucide="info" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
  Akun Anda belum terhubung ke data karyawan. Hubungi Admin HRD.
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-14 text-center text-gray-400">
  <i data-lucide="clipboard-list" class="w-14 h-14 mx-auto mb-3 opacity-20"></i>
  <p class="text-sm font-medium">Pilih karyawan dan periode untuk menampilkan tabel penilaian.</p>
</div>
<?php endif; ?>

</div><!-- /p-5 -->
</main>
<script>
lucide.createIcons();
document.addEventListener('DOMContentLoaded',function(){
    const inputs=Array.from(document.querySelectorAll('.nbox'));
    const cols=<?=$jumlah_hari?>;
    inputs.forEach(function(inp,idx){
        inp.addEventListener('focus',function(){this.select();});
        inp.addEventListener('keydown',function(e){
            if(e.key==='Enter'){e.preventDefault();if(inputs[idx+cols])inputs[idx+cols].focus();}
            if(e.key==='ArrowRight'){e.preventDefault();if(inputs[idx+1])inputs[idx+1].focus();}
            if(e.key==='ArrowLeft'){e.preventDefault();if(inputs[idx-1])inputs[idx-1].focus();}
            if(e.key==='ArrowDown'){e.preventDefault();if(inputs[idx+cols])inputs[idx+cols].focus();}
            if(e.key==='ArrowUp'){e.preventDefault();if(inputs[idx-cols])inputs[idx-cols].focus();}
        });
    });
});
</script>
</body>
</html>
