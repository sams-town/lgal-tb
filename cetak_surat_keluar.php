<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }

// Ambil dari GET atau dari database jika ada id
$id           = (int)($_GET['id'] ?? 0);
$doc          = null;

if ($id) {
    try {
        $st = $pdo->prepare("SELECT * FROM manajemen_surat WHERE id = ?");
        $st->execute([$id]);
        $doc = $st->fetch();
    } catch (Exception $e) {}
}

// Field-field surat — dari DB atau dari GET param (untuk preview langsung)
$tanggal_surat  = $doc['tanggal_surat']   ?? $_GET['tanggal_surat']  ?? '';
$nomor_surat    = $doc['nomor_surat']     ?? $_GET['nomor_surat']    ?? '';
$perihal        = $doc['perihal']         ?? $_GET['perihal']        ?? '';
$lampiran       = $doc['lampiran']        ?? $_GET['lampiran']       ?? '';
$tujuan_nama    = $doc['asal_pengirim']   ?? $_GET['tujuan_nama']    ?? '';
$tujuan_alamat  = $doc['tujuan_alamat']   ?? $_GET['tujuan_alamat']  ?? '';
$up_nama        = $doc['up_nama']         ?? $_GET['up_nama']        ?? '';
$ucapan_mitra   = $doc['ucapan_mitra']    ?? $_GET['ucapan_mitra']   ?? '';
$isi_surat      = $doc['isi_surat']       ?? $_GET['isi_surat']      ?? '';
$penanda_tangan = $doc['penanda_tangan']  ?? $_GET['penanda_tangan'] ?? '';
$jabatan_ttd    = $doc['jabatan_ttd']     ?? $_GET['jabatan_ttd']    ?? '';

// Format tanggal Indonesia
function fmtTglSurat($tgl): string {
    if (!$tgl) return 'xx xxx xxxx';
    $m=['','Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'];
    try {
        $dt = new DateTime($tgl);
        return $dt->format('d').' '.$m[(int)$dt->format('n')].' '.$dt->format('Y');
    } catch(Exception $e){ return $tgl; }
}
$tgl_tampil = fmtTglSurat($tanggal_surat);

$logo_path = __DIR__.'/assets/logo.png';
$logo_b64  = file_exists($logo_path)
    ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo_path))
    : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Surat Keluar - <?= htmlspecialchars($nomor_surat ?: 'Preview') ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: Arial, sans-serif;
    font-size: 11pt;
    color: #1a1a1a;
    background: #fff;
    line-height: 1.6;
}
.page {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 10mm 20mm 16mm 25mm;
    background: #fff;
    position: relative;
}

/* ── KOP ── */
.kop {
    display: flex;
    align-items: center;
    border-bottom: 3px solid #1a5f6e;
    padding-bottom: 8px;
    margin-bottom: 3px;
    position: relative;
    overflow: hidden;
}
.kop-logo { width: 58px; flex-shrink:0; margin-right:10px; }
.kop-logo img { width:100%; display:block; }
.kop-text { flex:1; }
.kop-text .rs-label {
    font-size: 7.5pt;
    color: #1a5f6e;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    font-weight: 700;
}
.kop-text .rs-name {
    font-size: 17pt;
    font-weight: 900;
    color: #1a2e3b;
    line-height: 1.1;
}
.kop-contact {
    font-size: 7.5pt;
    color: #444;
    text-align: right;
    line-height: 1.8;
    flex-shrink: 0;
    max-width: 165px;
}
.kop-ornament  { position:absolute;right:4px;top:0;width:26px;height:26px;background:#c8a84b;border-radius:50%;opacity:.65; }
.kop-ornament2 { position:absolute;right:24px;top:-5px;width:14px;height:14px;background:#1a5f6e;border-radius:50%;opacity:.45; }
.kop-line2 { border-bottom:1.5px solid #1a5f6e; margin-bottom:20px; }

/* ── Tanggal ── */
.tgl-line { text-align:left; margin-bottom:14px; font-size:11pt; }

/* ── Header surat ── */
.surat-header { margin-bottom:14px; }
.surat-header table { border-collapse:collapse; }
.surat-header td { padding: 1px 0; vertical-align:top; font-size:10.5pt; }
.surat-header td.lbl { width: 72px; }
.surat-header td.sep { width: 16px; }
.surat-header td.val { }

/* ── Kepada ── */
.kepada-block { margin-bottom:14px; font-size:10.5pt; }
.kepada-block .yth  { font-weight:normal; }
.kepada-block .nama { font-weight:bold; font-size:11pt; }
.kepada-block .adr  { font-weight:bold; font-size:11pt; }
.kepada-block .di   { }
.kepada-block .up   { margin-top:2px; font-style:italic; font-size:10pt; color:#555; }

/* ── Isi surat ── */
.salam       { margin-bottom:10px; font-size:10.5pt; }
.ucapan      { margin-bottom:10px; font-size:10.5pt; color:#2563a8; font-style:italic; }
.isi-surat   { margin-bottom:14px; font-size:10.5pt; text-align:justify; white-space:pre-wrap; }
.penutup     { margin-bottom:20px; font-size:10.5pt; }

/* ── Tanda tangan ── */
.ttd-block   { font-size:10.5pt; }
.ttd-block .hormat { margin-bottom:2px; }
.ttd-block .instansi { font-weight:bold; margin-bottom:40px; }
.ttd-block .nama-ttd { font-weight:bold; text-decoration:underline; }
.ttd-block .jabatan  { font-size:10pt; }

/* ── Print ── */
@media print {
    .no-print { display:none!important; }
    body { background:#fff; }
    .page { margin:0; padding:9mm 19mm 14mm 24mm; box-shadow:none; }
    @page { size:A4 portrait; margin:0; }
}
@media screen {
    body { background:#e5e7eb; }
    .page { box-shadow:0 4px 28px rgba(0,0,0,.15); margin:20px auto; }
}
</style>
</head>
<body>

<!-- Toolbar -->
<div class="no-print" style="text-align:center;padding:12px 0;display:flex;justify-content:center;gap:12px;flex-wrap:wrap">
  <button onclick="window.print()"
    style="background:#1a5f6e;color:#fff;border:none;padding:8px 24px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:700">
    🖨 Cetak / Simpan PDF
  </button>
  <a href="surat-keluar.php"
    style="background:#e5e7eb;color:#374151;padding:8px 20px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:600">
    ← Kembali
  </a>
</div>

<div class="page">

  <!-- KOP SURAT -->
  <div class="kop">
    <div class="kop-logo">
      <?php if ($logo_b64): ?>
      <img src="<?= $logo_b64 ?>" alt="Logo RS THB">
      <?php else: ?>
      <div style="width:58px;height:58px;background:#1a5f6e;border-radius:8px"></div>
      <?php endif; ?>
    </div>
    <div class="kop-text">
      <div class="rs-label">Rumah Sakit</div>
      <div class="rs-name">TAMAN<br>HARAPAN BARU</div>
    </div>
    <div class="kop-contact">
      📍 Jl. Kalabang Tengah Nomor 2,<br>
      RT.004/RW.023, Pejuang,<br>
      Medan Satria, Kota Bekasi 17181<br>
      📞 Telp : (021) 8898 1055<br>
      ✉ Email : info@rsthb.id
    </div>
    <div class="kop-ornament"></div>
    <div class="kop-ornament2"></div>
  </div>
  <div class="kop-line2"></div>

  <!-- Tanggal -->
  <div class="tgl-line">Bekasi, <?= htmlspecialchars($tgl_tampil) ?></div>

  <!-- Header surat -->
  <div class="surat-header">
    <table>
      <tr>
        <td class="lbl">Nomor</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($nomor_surat ?: 'xx/DIR-EXT/RS.THB/BULAN/TAHUN') ?></td>
      </tr>
      <tr>
        <td class="lbl">Perihal</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($perihal ?: 'xxxxxxxxxx') ?></td>
      </tr>
      <tr>
        <td class="lbl">Lamp</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($lampiran ?: 'xxxx') ?></td>
      </tr>
    </table>
  </div>

  <!-- Kepada Yth -->
  <div class="kepada-block">
    <div class="yth">Kepada Yth.</div>
    <div class="nama"><?= htmlspecialchars($tujuan_nama ?: 'XXXX (Nama Perusahaan/Nama Bapak/Ibu)') ?></div>
    <div class="adr"><?= htmlspecialchars($tujuan_alamat ?: 'XXXX (Alamat)') ?></div>
    <div class="di">di Tempat</div>
    <?php if ($up_nama): ?>
    <div class="up">Up.: <?= htmlspecialchars($up_nama) ?> (Nama &amp; Jabatan)</div>
    <?php else: ?>
    <div class="up" style="color:#aaa">Up.: xxxxxx – xxxxxx (Nama &amp; Jabatan) (jika ada)</div>
    <?php endif; ?>
  </div>

  <!-- Salam pembuka -->
  <div class="salam">Dengan hormat,</div>

  <!-- Ucapan terima kasih (optional) -->
  <?php if ($ucapan_mitra): ?>
  <div class="ucapan">
    Sebelumnya kami ucapkan terima kasih atas <?= htmlspecialchars($ucapan_mitra) ?> selama ini antara <?= htmlspecialchars($tujuan_nama) ?> dengan Rumah Sakit Taman Harapan Baru.
  </div>
  <?php else: ?>
  <div class="ucapan" style="color:#aaa;font-size:9.5pt">
    Sebelumnya kami ucapkan terima kasih atas xxxxxxxx selama ini antara xxxxxx dengan Rumah Sakit Taman Harapan Baru. (optional)
  </div>
  <?php endif; ?>

  <!-- Isi surat -->
  <div class="isi-surat"><?= nl2br(htmlspecialchars($isi_surat ?: "Bersama ini / Sehubungan dengan xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\nXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.\nXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.\nXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.")) ?></div>

  <!-- Penutup -->
  <div class="penutup">Demikian surat ini kami sampaikan. Atas perhatian dan kerjasama yang baik, kami ucapkan terima kasih.</div>

  <!-- Tanda tangan -->
  <div class="ttd-block">
    <div class="hormat">Hormat kami,</div>
    <div class="instansi">Rumah Sakit Taman Harapan Baru</div>
    <div class="nama-ttd"><?= htmlspecialchars($penanda_tangan ?: 'Nama') ?></div>
    <div class="jabatan"><?= htmlspecialchars($jabatan_ttd ?: 'Jabatan') ?></div>
  </div>

</div><!-- /page -->
</body>
</html>
