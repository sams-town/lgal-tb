<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$doc = null;
if ($id) {
    try {
        $st = $pdo->prepare("SELECT * FROM manajemen_surat WHERE id = ?");
        $st->execute([$id]);
        $doc = $st->fetch();
    } catch (Exception $e) {}
}

$no_sp        = $doc['nomor_surat']    ?? $_GET['no_sp']        ?? '';
$tanggal      = $doc['tanggal_surat']  ?? $_GET['tanggal']       ?? '';
$nama_ttd     = $doc['penanda_tangan'] ?? $_GET['nama_ttd']      ?? 'dr. Andara Dwike, MARS, M.H., FISQua';
$jabatan_ttd  = $doc['jabatan_ttd']    ?? $_GET['jabatan_ttd']   ?? 'Direktur Utama Rumah Sakit Taman Harapan Baru';
$isi          = $doc['isi_surat']      ?? $_GET['isi']           ?? '';

function fmtTglSP($tgl): string {
    if (!$tgl) return 'xx xx xxxx (tanggal, bulan, tahun)';
    $m=['','Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'];
    try {
        $dt = new DateTime($tgl);
        return $dt->format('d').' '.$m[(int)$dt->format('n')].' '.$dt->format('Y');
    } catch(Exception $e){ return $tgl; }
}
$tgl_tampil = fmtTglSP($tanggal);

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
<title>Surat Pernyataan - <?= htmlspecialchars($no_sp ?: 'Preview') ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: Arial, sans-serif;
    font-size: 11pt;
    color: #1a1a1a;
    background: #fff;
    line-height: 1.65;
}
.page {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 10mm 22mm 18mm 22mm;
    background: #fff;
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
    font-size: 7.5pt; color:#1a5f6e;
    letter-spacing:1.5px; text-transform:uppercase; font-weight:700;
}
.kop-text .rs-name {
    font-size: 17pt; font-weight:900; color:#1a2e3b; line-height:1.1;
}
.kop-contact {
    font-size: 7.5pt; color:#444; text-align:right;
    line-height:1.8; flex-shrink:0; max-width:165px;
}
.kop-ornament  { position:absolute;right:4px;top:0;width:26px;height:26px;background:#c8a84b;border-radius:50%;opacity:.65; }
.kop-ornament2 { position:absolute;right:24px;top:-5px;width:14px;height:14px;background:#1a5f6e;border-radius:50%;opacity:.45; }
.kop-line2 { border-bottom:1.5px solid #1a5f6e; margin-bottom:24px; }

/* ── Judul ── */
.sp-title {
    text-align: center;
    margin-bottom: 22px;
}
.sp-title h1 {
    font-size: 13pt;
    font-weight: bold;
    text-decoration: underline;
    letter-spacing: 3px;
    text-transform: uppercase;
    margin-bottom: 4px;
}
.sp-title .sp-no {
    font-size: 11pt;
    color: #1a2e3b;
    font-weight: normal;
}

/* ── Yang bertanda tangan ── */
.sp-ttd-intro {
    font-weight: bold;
    font-size: 10.5pt;
    margin-bottom: 8px;
    color: #1a5f6e;
}
.sp-identitas {
    margin-bottom: 18px;
}
.sp-identitas table { border-collapse: collapse; }
.sp-identitas td { padding: 2px 0; font-size: 10.5pt; vertical-align: top; }
.sp-identitas td.lbl  { width: 70px; }
.sp-identitas td.sep  { width: 16px; }
.sp-identitas td.val  { font-weight: normal; }

/* ── Isi / pernyataan ── */
.sp-menyatakan {
    margin-bottom: 12px;
    font-size: 10.5pt;
    text-align: justify;
}
.sp-isi {
    margin-bottom: 18px;
    font-size: 10.5pt;
    text-align: justify;
    white-space: pre-wrap;
    line-height: 1.7;
}
.sp-penutup {
    margin-bottom: 26px;
    font-size: 10.5pt;
    text-align: justify;
}

/* ── TTD ── */
.sp-ttd-wrap {
    display: flex;
    justify-content: flex-end;
}
.sp-ttd-block {
    text-align: center;
    font-size: 10.5pt;
    min-width: 220px;
}
.sp-ttd-block .ttd-kota  { margin-bottom: 2px; }
.sp-ttd-block .ttd-inst  { margin-bottom: 44px; }
.sp-ttd-block .ttd-nama  { font-weight: bold; }
.sp-ttd-block .ttd-jab   { font-size: 10pt; }

/* ── Print ── */
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

<!-- Toolbar -->
<div class="no-print" style="text-align:center;padding:12px 0;display:flex;justify-content:center;gap:12px;flex-wrap:wrap">
  <button onclick="window.print()"
    style="background:#1a5f6e;color:#fff;border:none;padding:8px 24px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:700">
    🖨 Cetak / Simpan PDF
  </button>
  <a href="javascript:history.back()"
    style="background:#e5e7eb;color:#374151;padding:8px 20px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:600">
    ← Kembali
  </a>
</div>

<div class="page">

  <!-- KOP -->
  <div class="kop">
    <div class="kop-logo">
      <?php if ($logo_b64): ?>
      <img src="<?= $logo_b64 ?>" alt="Logo RS THB">
      <?php else: ?>
      <div style="width:58px;height:58px;background:#1a5f6e;border-radius:8px"></div>
      <?php endif; ?>
    </div>
    <div class="kop-text" style="flex:1;margin-right:10px">
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

  <!-- JUDUL -->
  <div class="sp-title">
    <h1>Surat Pernyataan</h1>
    <div class="sp-no">NO : <?= htmlspecialchars($no_sp ?: 'XX/SP-DIR/RS.THB/BULAN/TAHUN') ?></div>
  </div>

  <!-- YANG BERTANDA TANGAN -->
  <div class="sp-ttd-intro">Yang bertanda tangan di bawah ini:</div>
  <div class="sp-identitas">
    <table>
      <tr>
        <td class="lbl">Nama</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($nama_ttd ?: 'dr. Andara Dwike, MARS, M.H., FISQua') ?></td>
      </tr>
      <tr>
        <td class="lbl">Jabatan</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($jabatan_ttd ?: 'Direktur Utama Rumah Sakit Taman Harapan Baru') ?></td>
      </tr>
    </table>
  </div>

  <!-- ISI PERNYATAAN -->
  <div class="sp-menyatakan">
    Dengan ini menyatakan bahwa <?= nl2br(htmlspecialchars($isi ?: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.')) ?>
  </div>

  <!-- PENUTUP -->
  <div class="sp-penutup">Demikian surat pernyataan ini diberikan untuk dapat dilaksanakan dengan penuh tanggung jawab. Terima kasih atas perhatian dan kerjasamanya.</div>

  <!-- TTD -->
  <div class="sp-ttd-wrap">
    <div class="sp-ttd-block">
      <div class="ttd-kota">Bekasi, <?= htmlspecialchars($tgl_tampil) ?></div>
      <div class="ttd-inst">RS Taman Harapan Baru,</div>
      <div class="ttd-nama"><?= htmlspecialchars($nama_ttd ?: 'dr. Andara Dwike, MARS, M.H., FISQua') ?></div>
      <div class="ttd-jab"><?= htmlspecialchars($jabatan_ttd ?: 'Direktur Utama Rumah Sakit') ?></div>
    </div>
  </div>

</div><!-- /page -->
</body>
</html>
