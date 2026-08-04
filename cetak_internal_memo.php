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

$no_memo       = $doc['nomor_surat']     ?? $_GET['no_memo']       ?? '';
$kepada        = $doc['asal_pengirim']   ?? $_GET['kepada']        ?? '';
$dari          = $doc['dari']            ?? $_GET['dari']          ?? '';
$perihal       = $doc['perihal']         ?? $_GET['perihal']       ?? '';
$tanggal       = $doc['tanggal_surat']   ?? $_GET['tanggal']       ?? '';
$isi           = $doc['isi_surat']       ?? $_GET['isi']           ?? '';
$nama_ttd      = $doc['penanda_tangan']  ?? $_GET['nama_ttd']      ?? '';
$jabatan_ttd   = $doc['jabatan_ttd']     ?? $_GET['jabatan_ttd']   ?? '';
$tembusan_raw  = $doc['tembusan']        ?? $_GET['tembusan']      ?? '';
$tembusan_arr  = [];
if ($tembusan_raw) {
    $decoded = json_decode($tembusan_raw, true);
    $tembusan_arr = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode("\n", $tembusan_raw)));
}

function fmtTglMemo($tgl): string {
    if (!$tgl) return 'xx Bulan Tahun';
    $m=['','Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'];
    try {
        $dt = new DateTime($tgl);
        return $dt->format('d').' '.$m[(int)$dt->format('n')].' '.$dt->format('Y');
    } catch(Exception $e){ return $tgl; }
}
$tgl_tampil = fmtTglMemo($tanggal);

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
<title>Internal Memo - <?= htmlspecialchars($no_memo ?: 'Preview') ?></title>
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
    padding: 10mm 20mm 16mm 20mm;
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
.kop-line2 { border-bottom:1.5px solid #1a5f6e; margin-bottom:22px; }

/* ── Judul ── */
.memo-title {
    text-align: center;
    margin-bottom: 4px;
}
.memo-title h1 {
    font-size: 13pt;
    font-weight: bold;
    text-decoration: underline;
    letter-spacing: 2px;
    text-transform: uppercase;
}
.memo-title .memo-no {
    font-size: 10pt;
    color: #1a2e3b;
    margin-top: 2px;
}

/* ── Header info ── */
.memo-header {
    margin: 16px 0 14px 0;
    border-bottom: 1px solid #333;
    padding-bottom: 8px;
}
.memo-header table { border-collapse: collapse; width: 100%; }
.memo-header td { padding: 2px 0; vertical-align: top; font-size: 10.5pt; }
.memo-header td.lbl { width: 68px; }
.memo-header td.sep { width: 14px; }
.memo-header td.val { font-weight: normal; }

/* ── Isi ── */
.salam  { margin-bottom: 8px; font-size: 10.5pt; font-style: italic; color: #1a5f6e; font-weight: bold; }
.isi    { margin-bottom: 14px; font-size: 10.5pt; text-align: justify; white-space: pre-wrap; color: #1a5f6e; }
.penutup { margin-bottom: 20px; font-size: 10.5pt; }

/* ── TTD ── */
.ttd-block { font-size: 10.5pt; margin-bottom: 20px; }
.ttd-block .instansi { margin-bottom: 40px; }

/* ── Tembusan ── */
.tembusan { font-size: 10pt; }
.tembusan .label { font-weight: normal; margin-bottom: 2px; }
.tembusan ul { list-style: none; padding: 0; }
.tembusan ul li { padding: 0; line-height: 1.7; }
.tembusan ul li::before { content: "– "; }

/* ── Print ── */
@media print {
    .no-print { display:none!important; }
    body { background:#fff; }
    .page { margin:0; padding:9mm 19mm 14mm 19mm; box-shadow:none; }
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
      <img src="<?= $logo_b64 ?>" alt="Logo">
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

  <!-- JUDUL -->
  <div class="memo-title">
    <h1>Internal Memo</h1>
    <div class="memo-no">No.: <?= htmlspecialchars($no_memo ?: 'XX/MEMO-UNIT/DEPARTEMEN/ RSTHB/BULAN/TAHUN') ?></div>
  </div>

  <!-- HEADER INFO -->
  <div class="memo-header">
    <table>
      <tr>
        <td class="lbl">Kepada Yth</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($kepada ?: '') ?></td>
      </tr>
      <tr>
        <td class="lbl">Dari</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($dari ?: '') ?></td>
      </tr>
      <tr>
        <td class="lbl">Perihal</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($perihal ?: '') ?></td>
      </tr>
      <tr>
        <td class="lbl">Tanggal</td>
        <td class="sep">:</td>
        <td class="val"><?= htmlspecialchars($tgl_tampil) ?></td>
      </tr>
    </table>
  </div>

  <!-- ISI -->
  <div class="salam">Dengan hormat,</div>
  <div class="isi"><?= nl2br(htmlspecialchars($isi ?: "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\nxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.")) ?></div>
  <div class="penutup">Demikian internal memo ini kami sampaikan. Atas perhatian, pengertian, dan kerja samanya kami ucapkan terima kasih.</div>

  <!-- TTD -->
  <div class="ttd-block">
    <div class="instansi">RS Taman Harapan Baru,</div>
    <div class="nama"><?= htmlspecialchars($nama_ttd ?: 'Nama') ?></div>
    <div class="jabatan" style="font-size:10pt"><?= htmlspecialchars($jabatan_ttd ?: 'Jabatan') ?></div>
  </div>

  <!-- TEMBUSAN -->
  <?php if (!empty($tembusan_arr)): ?>
  <div class="tembusan">
    <div class="label">Tembusan :</div>
    <ul>
      <?php foreach ($tembusan_arr as $t): ?>
      <li><?= htmlspecialchars($t) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php else: ?>
  <div class="tembusan">
    <div class="label">Tembusan :</div>
    <ul>
      <li>&nbsp;</li>
      <li>&nbsp;</li>
    </ul>
  </div>
  <?php endif; ?>

</div><!-- /page -->
</body>
</html>
