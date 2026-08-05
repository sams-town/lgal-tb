<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$doc = null;
if ($id) {
    try { $st=$pdo->prepare("SELECT * FROM manajemen_surat WHERE id=?"); $st->execute([$id]); $doc=$st->fetch(); }
    catch (Exception $e) {}
}

$no_st          = $doc['nomor_surat']     ?? $_GET['no_st']           ?? '';
$tanggal        = $doc['tanggal_surat']   ?? $_GET['tanggal']          ?? '';
// Pemberi tugas
$pemberi_nama   = $doc['penanda_tangan']  ?? $_GET['pemberi_nama']     ?? 'dr. Andara Dwike, MARS, M.H., FISQua';
$pemberi_jabatan= $doc['jabatan_ttd']     ?? $_GET['pemberi_jabatan']  ?? 'Direktur Utama Rumah Sakit Taman Harapan Baru';
// Penerima tugas
$penerima_nama  = $doc['penerima_nama']   ?? $_GET['penerima_nama']    ?? '';
$penerima_nik   = $doc['penerima_ktp']    ?? $_GET['penerima_nik']     ?? '';
$penerima_jabatan=$doc['jabatan_kiri']    ?? $_GET['penerima_jabatan'] ?? '';
// Acara / kegiatan
$undangan_dari  = $doc['untuk_kuasa']     ?? $_GET['undangan_dari']    ?? '';
$hari_tanggal   = $doc['hari_tanggal']    ?? $_GET['hari_tanggal']     ?? '';
$waktu_acara    = $doc['waktu_acara']     ?? $_GET['waktu_acara']      ?? '';
$tempat         = $doc['tujuan_alamat']   ?? $_GET['tempat']           ?? '';
$nama_kegiatan  = $doc['perihal']         ?? $_GET['nama_kegiatan']    ?? '';
// TTD kanan (sama dengan pemberi tugas)
$nama_ttd       = $pemberi_nama;
$jabatan_ttd_cetak = 'Direktur Utama Rumah Sakit';

function fmtTglST($tgl): string {
    if (!$tgl) return 'xx xxxxxxxx xxxx (tanggal, bulan, tahun)';
    $m=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    try { $dt=new DateTime($tgl); return $dt->format('d').' '.$m[(int)$dt->format('n')].' '.$dt->format('Y'); }
    catch(Exception $e){ return $tgl; }
}
$tgl_tampil = fmtTglST($tanggal);

$logo_path = __DIR__.'/assets/logo.png';
$logo_b64  = file_exists($logo_path) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Surat Tugas - <?= htmlspecialchars($no_st ?: 'Preview') ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:Arial,sans-serif; font-size:11pt; color:#1a1a1a; background:#fff; line-height:1.65; }
.page { width:210mm; min-height:297mm; margin:0 auto; padding:10mm 22mm 18mm 22mm; background:#fff; }

/* KOP */
.kop { display:flex; align-items:center; border-bottom:3px solid #1a5f6e; padding-bottom:8px; margin-bottom:3px; position:relative; overflow:hidden; }
.kop-logo { width:58px; flex-shrink:0; margin-right:10px; }
.kop-logo img { width:100%; display:block; }
.kop-text { flex:1; }
.kop-text .rs-label { font-size:7.5pt; color:#1a5f6e; letter-spacing:1.5px; text-transform:uppercase; font-weight:700; }
.kop-text .rs-name  { font-size:17pt; font-weight:900; color:#1a2e3b; line-height:1.1; }
.kop-contact { font-size:7.5pt; color:#444; text-align:right; line-height:1.8; flex-shrink:0; max-width:165px; }
.kop-ornament  { position:absolute;right:4px;top:0;width:26px;height:26px;background:#c8a84b;border-radius:50%;opacity:.65; }
.kop-ornament2 { position:absolute;right:24px;top:-5px;width:14px;height:14px;background:#1a5f6e;border-radius:50%;opacity:.45; }
.kop-line2 { border-bottom:1.5px solid #1a5f6e; margin-bottom:24px; }

/* Judul */
.st-title { text-align:center; margin-bottom:22px; }
.st-title h1 { font-size:13pt; font-weight:bold; text-decoration:underline; letter-spacing:3px; text-transform:uppercase; margin-bottom:4px; }
.st-title .st-no { font-size:11pt; color:#1a2e3b; }

/* Blok identitas */
.st-section { margin-bottom:14px; font-size:10.5pt; }
.st-section .intro { margin-bottom:5px; }
.st-section table { border-collapse:collapse; }
.st-section td { padding:2px 0; font-size:10.5pt; vertical-align:top; }
.st-section td.lbl { width:76px; }
.st-section td.sep { width:16px; }
.st-section td.val { color:#1a5f6e; font-weight:bold; }

/* Untuk / acara */
.st-untuk { margin-bottom:12px; font-size:10.5pt; }
.st-untuk .untuk-intro { margin-bottom:5px; }

/* Penutup */
.st-penutup { margin-bottom:26px; font-size:10.5pt; text-align:justify; }

/* TTD kanan */
.st-ttd-wrap { display:flex; justify-content:flex-end; }
.st-ttd-block { text-align:center; font-size:10.5pt; min-width:220px; }
.st-ttd-block .ttd-kota { margin-bottom:2px; color:#1a5f6e; }
.st-ttd-block .ttd-inst { margin-bottom:44px; }
.st-ttd-block .ttd-nama { font-weight:bold; }
.st-ttd-block .ttd-jab  { font-size:10pt; }

@media print {
    .no-print { display:none!important; }
    body { background:#fff; }
    .page { margin:0; padding:9mm 20mm 16mm 20mm; box-shadow:none; }
    @page { size:A4 portrait; margin:0; }
}
@media screen {
    body { background:#e5e7eb; }
    .page { box-shadow:0 4px 28px rgba(0,0,0,.15); margin:20px auto; }
}
</style>
</head>
<body>

<div class="no-print" style="text-align:center;padding:12px 0;display:flex;justify-content:center;gap:12px;flex-wrap:wrap">
  <button onclick="window.print()" style="background:#1a5f6e;color:#fff;border:none;padding:8px 24px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:700">
    🖨 Cetak / Simpan PDF
  </button>
  <a href="javascript:history.back()" style="background:#e5e7eb;color:#374151;padding:8px 20px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:600">
    ← Kembali
  </a>
</div>

<div class="page">

  <!-- KOP -->
  <div class="kop">
    <div class="kop-logo">
      <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Logo"><?php else: ?>
      <div style="width:58px;height:58px;background:#1a5f6e;border-radius:8px"></div><?php endif; ?>
    </div>
    <div class="kop-text" style="flex:1;margin-right:10px">
      <div class="rs-label">Rumah Sakit</div>
      <div class="rs-name">TAMAN<br>HARAPAN BARU</div>
    </div>
    <div class="kop-contact">
      📍 Jl. Kalabang Tengah Nomor 2,<br>RT.004/RW.023, Pejuang,<br>
      Medan Satria, Kota Bekasi 17181<br>
      📞 Telp : (021) 8898 1055<br>
      ✉ Email : info@rsthb.id
    </div>
    <div class="kop-ornament"></div>
    <div class="kop-ornament2"></div>
  </div>
  <div class="kop-line2"></div>

  <!-- JUDUL -->
  <div class="st-title">
    <h1>Surat Tugas</h1>
    <div class="st-no">No.: <?= htmlspecialchars($no_st ?: 'XX/DIR-ST/RS.THB/BULAN/TAHUN') ?></div>
  </div>

  <!-- PEMBERI TUGAS -->
  <div class="st-section">
    <div class="intro">Yang bertanda tangan di bawah ini:</div>
    <table>
      <tr>
        <td class="lbl">Nama</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($pemberi_nama) ?></td>
      </tr>
      <tr>
        <td class="lbl">Jabatan</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($pemberi_jabatan) ?></td>
      </tr>
    </table>
  </div>

  <!-- PENERIMA TUGAS -->
  <div class="st-section">
    <div class="intro">Dengan ini memberikan tugas kepada:</div>
    <table>
      <tr>
        <td class="lbl">Nama</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($penerima_nama ?: 'xxxxxxxx') ?></td>
      </tr>
      <tr>
        <td class="lbl">NIK</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($penerima_nik ?: 'xxxxxxxx') ?></td>
      </tr>
      <tr>
        <td class="lbl">Jabatan</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($penerima_jabatan ?: 'xxxxxxxx') ?></td>
      </tr>
    </table>
  </div>

  <!-- ACARA -->
  <div class="st-untuk">
    <div class="untuk-intro">Untuk menghadiri undangan dari <strong><?= htmlspecialchars($undangan_dari ?: 'xxxxxxxxx') ?></strong>, yang akan dilaksanakan pada:</div>
    <table style="border-collapse:collapse">
      <tr>
        <td style="width:100px;font-size:10.5pt">Hari, Tanggal</td>
        <td style="width:16px;font-size:10.5pt">:</td>
        <td style="color:#1a5f6e;font-weight:bold;font-size:10.5pt"><?= htmlspecialchars($hari_tanggal ?: 'xxxxxxxx') ?></td>
      </tr>
      <tr>
        <td style="font-size:10.5pt">Waktu</td>
        <td style="font-size:10.5pt">:</td>
        <td style="color:#1a5f6e;font-weight:bold;font-size:10.5pt"><?= htmlspecialchars($waktu_acara ?: 'xxxxxxxx') ?></td>
      </tr>
      <tr>
        <td style="font-size:10.5pt">Tempat</td>
        <td style="font-size:10.5pt">:</td>
        <td style="color:#1a5f6e;font-weight:bold;font-size:10.5pt"><?= htmlspecialchars($tempat ?: 'xxxxxxxx') ?></td>
      </tr>
      <tr>
        <td style="font-size:10.5pt">Nama Kegiatan</td>
        <td style="font-size:10.5pt">:</td>
        <td style="color:#1a5f6e;font-weight:bold;font-size:10.5pt"><?= htmlspecialchars($nama_kegiatan ?: 'xxxxxxxx') ?></td>
      </tr>
    </table>
  </div>

  <!-- PENUTUP -->
  <div class="st-penutup">Demikian surat tugas ini diberikan untuk dapat dilaksanakan dengan penuh tanggung jawab. Terima kasih atas perhatian dan kerjasamanya.</div>

  <!-- TTD -->
  <div class="st-ttd-wrap">
    <div class="st-ttd-block">
      <div class="ttd-kota">Bekasi, <?= htmlspecialchars($tgl_tampil) ?></div>
      <div class="ttd-inst">RS Taman Harapan Baru,</div>
      <div class="ttd-nama"><?= htmlspecialchars($nama_ttd ?: 'Dr. Andara Dwike, MARS, M.H., FISQua') ?></div>
      <div class="ttd-jab"><?= htmlspecialchars($jabatan_ttd_cetak) ?></div>
    </div>
  </div>

</div><!-- /page -->
</body>
</html>
