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

$no_undangan    = $doc['nomor_surat']     ?? $_GET['no_undangan']    ?? '';
$tanggal        = $doc['tanggal_surat']   ?? $_GET['tanggal']         ?? '';
$perihal        = $doc['perihal']         ?? $_GET['perihal']         ?? 'UNDANGAN';
$lampiran       = $doc['lampiran']        ?? $_GET['lampiran']        ?? '';
$tujuan_nama    = $doc['asal_pengirim']   ?? $_GET['tujuan_nama']     ?? '';
$tujuan_alamat  = $doc['tujuan_alamat']   ?? $_GET['tujuan_alamat']   ?? '';
$up_nama        = $doc['up_nama']         ?? $_GET['up_nama']         ?? '';
$diundang       = $doc['untuk_kuasa']     ?? $_GET['diundang']        ?? '';
$acara          = $doc['perihal']         ?? $_GET['acara']           ?? '';
$hari_tanggal   = $doc['hari_tanggal']    ?? $_GET['hari_tanggal']    ?? '';
$waktu_mulai    = $doc['waktu_acara']     ?? $_GET['waktu_mulai']     ?? '';
$waktu_selesai  = $doc['waktu_selesai']   ?? $_GET['waktu_selesai']   ?? '';
$tempat         = $doc['tujuan_alamat']   ?? $_GET['tempat']          ?? '';
$agenda         = $doc['isi_surat']       ?? $_GET['agenda']          ?? '';
$nama_ttd       = $doc['penanda_tangan']  ?? $_GET['nama_ttd']        ?? '';
$jabatan_ttd    = $doc['jabatan_ttd']     ?? $_GET['jabatan_ttd']     ?? '';

function fmtTglUnd($tgl): string {
    if (!$tgl) return 'xx xxx xxxx';
    $m=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    try { $dt=new DateTime($tgl); return $dt->format('d').' '.$m[(int)$dt->format('n')].' '.$dt->format('Y'); }
    catch(Exception $e){ return $tgl; }
}
$tgl_tampil = fmtTglUnd($tanggal);

$logo_path = __DIR__.'/assets/logo.png';
$logo_b64  = file_exists($logo_path) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Surat Undangan - <?= htmlspecialchars($no_undangan ?: 'Preview') ?></title>
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
.kop-line2 { border-bottom:1.5px solid #1a5f6e; margin-bottom:20px; }

/* Tanggal */
.tgl-line { margin-bottom:14px; font-size:11pt; }

/* Header surat */
.surat-header { margin-bottom:14px; }
.surat-header table { border-collapse:collapse; }
.surat-header td { padding:2px 0; font-size:10.5pt; vertical-align:top; }
.surat-header td.lbl { width:60px; }
.surat-header td.sep { width:14px; }
.surat-header td.val { color:#1a5f6e; font-weight:bold; }

/* Kepada */
.kepada-block { margin-bottom:14px; font-size:10.5pt; }
.kepada-block .nama { font-weight:bold; font-size:11pt; }
.kepada-block .adr  { font-weight:bold; font-size:11pt; }
.kepada-block .up   { margin-top:2px; font-style:italic; font-size:10pt; color:#1a5f6e; }

/* Salam & isi */
.salam { margin-bottom:10px; font-size:10.5pt; }
.opening { margin-bottom:12px; font-size:10.5pt; color:#1a5f6e; font-style:italic; }

/* Detail acara */
.acara-detail { margin-bottom:14px; }
.acara-detail table { border-collapse:collapse; }
.acara-detail td { padding:2px 0; font-size:10.5pt; vertical-align:top; }
.acara-detail td.lbl { width:90px; }
.acara-detail td.sep { width:14px; }
.acara-detail td.val { color:#1a5f6e; font-weight:bold; }

/* Harapan & penutup */
.harapan  { margin-bottom:10px; font-size:10.5pt; color:#1a5f6e; font-style:italic; }
.penutup  { margin-bottom:22px; font-size:10.5pt; text-align:justify; }

/* TTD */
.ttd-block { font-size:10.5pt; }
.ttd-block .hormat   { margin-bottom:2px; }
.ttd-block .instansi { font-weight:bold; margin-bottom:44px; }
.ttd-block .nama-ttd { font-weight:bold; text-decoration:underline; }
.ttd-block .jabatan  { font-size:10pt; }

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

  <!-- TANGGAL -->
  <div class="tgl-line">Bekasi, <?= htmlspecialchars($tgl_tampil) ?></div>

  <!-- HEADER SURAT -->
  <div class="surat-header">
    <table>
      <tr>
        <td class="lbl">Nomor</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($no_undangan ?: 'xx/DIR-UND/RS.THB/BULAN/TAHUN') ?></td>
      </tr>
      <tr>
        <td class="lbl">Perihal</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($perihal ?: 'UNDANGAN') ?></td>
      </tr>
      <tr>
        <td class="lbl">Lamp</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($lampiran ?: 'xxxx') ?></td>
      </tr>
    </table>
  </div>

  <!-- KEPADA YTH -->
  <div class="kepada-block">
    <div>Kepada Yth.</div>
    <div class="nama"><?= htmlspecialchars($tujuan_nama ?: 'XXXX (Nama Perusahaan/Nama Bapak/Ibu)') ?></div>
    <div class="adr"><?= htmlspecialchars($tujuan_alamat ?: 'XXXX (Alamat)') ?></div>
    <div>di Tempat</div>
    <?php if ($up_nama): ?>
    <div class="up">Up.: <?= htmlspecialchars($up_nama) ?> (Nama &amp; Jabatan)</div>
    <?php else: ?>
    <div class="up">Up.: xxxxxxx – xxxxxxx (Nama &amp; Jabatan) (jika ada)</div>
    <?php endif; ?>
  </div>

  <!-- SALAM -->
  <div class="salam">Dengan hormat,</div>

  <!-- OPENING -->
  <div class="opening">
    Bersama ini kami mengundang <?= htmlspecialchars($diundang ?: 'xxxxxx') ?> untuk dapat menghadiri <?= htmlspecialchars($acara ?: 'xxxxxxx') ?> yang akan dilaksanakan pada :
  </div>

  <!-- DETAIL ACARA -->
  <div class="acara-detail">
    <table>
      <tr>
        <td class="lbl">Hari, Tanggal</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($hari_tanggal ?: 'xxxx, xx xxxx xxxx') ?></td>
      </tr>
      <tr>
        <td class="lbl">Pukul</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($waktu_mulai ?: 'xxxx') ?><?= $waktu_selesai ? ' s.d '.htmlspecialchars($waktu_selesai) : ' s.d xxxx' ?></td>
      </tr>
      <tr>
        <td class="lbl">Tempat</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($tempat ?: 'xxxxxxxx') ?></td>
      </tr>
      <tr>
        <td class="lbl">Agenda</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($agenda ?: 'xxxxxxxxxxxxxxxx') ?></td>
      </tr>
    </table>
  </div>

  <!-- HARAPAN -->
  <div class="harapan">Diharapkan kehadiran <?= htmlspecialchars($diundang ?: 'xxxxxx') ?> dapat datang tepat pada waktunya.</div>

  <!-- PENUTUP -->
  <div class="penutup">Demikian undangan ini kami sampaikan. Atas perhatian dan kerjasama yang baik, kami ucapkan terima kasih.</div>

  <!-- TTD -->
  <div class="ttd-block">
    <div class="hormat">Hormat kami,</div>
    <div class="instansi">Rumah Sakit Taman Harapan Baru</div>
    <div class="nama-ttd"><?= htmlspecialchars($nama_ttd ?: 'Nama') ?></div>
    <div class="jabatan"><?= htmlspecialchars($jabatan_ttd ?: 'Jabatan') ?></div>
  </div>

</div><!-- /page -->
</body>
</html>
