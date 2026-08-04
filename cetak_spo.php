<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }

// Ambil data dari GET (bisa diisi manual atau dari modul SOP)
$judul        = $_GET['judul']        ?? '';
$no_dokumen   = $_GET['no_dokumen']   ?? '';
$no_revisi    = $_GET['no_revisi']    ?? '';
$halaman      = $_GET['halaman']      ?? '1/1';
$tgl_terbit   = $_GET['tgl_terbit']   ?? '';
$pengertian   = $_GET['pengertian']   ?? '';
$tujuan       = $_GET['tujuan']       ?? '';
$kebijakan    = $_GET['kebijakan']    ?? '';
$prosedur     = $_GET['prosedur']     ?? '';
$unit_terkait = $_GET['unit_terkait'] ?? '';
$unit_pembuat = $_GET['unit_pembuat'] ?? '';
$direktur     = $_GET['direktur']     ?? 'Dr. Andara Dwike, MARS, M.H., FISQua';
$jabatan_dir  = $_GET['jabatan_dir']  ?? 'Direktur Utama';

// Format tanggal
function fmtTglSPO(string $tgl): string {
    if (!$tgl) return 'xx xxx xxxxx';
    $m=['','Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'];
    try {
        $dt = new DateTime($tgl);
        return $dt->format('d').' '.$m[(int)$dt->format('n')].' '.$dt->format('Y');
    } catch(Exception $e){ return $tgl; }
}
$tgl_tampil = fmtTglSPO($tgl_terbit);

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
<title>Form SPO - <?= htmlspecialchars($judul ?: 'Template') ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: Arial, sans-serif;
    font-size: 10pt;
    color: #1a1a1a;
    background: #fff;
}

/* ─── Halaman A4 ─── */
.page {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 10mm 12mm 14mm 12mm;
    background: #fff;
}

/* ─── Tabel utama SPO ─── */
.spo-table {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #2563a8;
}
.spo-table td, .spo-table th {
    border: 1.5px solid #2563a8;
    padding: 5px 7px;
    vertical-align: middle;
    font-size: 10pt;
}

/* ─── Baris 1: Logo + Judul ─── */
.td-logo {
    width: 38mm;
    text-align: center;
    vertical-align: middle;
    padding: 6px 8px;
    border-right: 1.5px solid #2563a8;
}
.td-logo img { width: 52px; margin-bottom: 4px; display:block; margin-left:auto; margin-right:auto; }
.td-logo .rs-name {
    font-size: 8.5pt;
    font-weight: 800;
    color: #1a2e3b;
    line-height: 1.3;
    text-transform: uppercase;
    letter-spacing: .3px;
}
.td-judul {
    text-align: center;
    font-size: 11.5pt;
    font-weight: bold;
    color: #1a2e3b;
    padding: 10px 12px;
    letter-spacing: .5px;
}

/* ─── Baris 2: No Dokumen / No Revisi / Halaman ─── */
.td-label {
    text-align: center;
    font-size: 9pt;
    font-weight: bold;
    border-right: 1.5px solid #2563a8;
    vertical-align: top;
    padding: 4px 6px;
    width: 38mm;
}
.td-meta {
    text-align: center;
    font-size: 9pt;
    border-right: 1.5px solid #2563a8;
    padding: 4px 6px;
    width: 36mm;
    vertical-align: top;
}
.td-meta .meta-label { font-weight: bold; display:block; }
.td-meta .meta-val   { display:block; margin-top:2px; }

/* ─── Baris 3: SPO label + Tanggal + Ditetapkan ─── */
.td-spo-label {
    text-align: center;
    font-weight: bold;
    font-size: 9.5pt;
    color: #1a2e3b;
    line-height: 1.4;
    width: 38mm;
    padding: 6px 6px;
    vertical-align: middle;
}
.td-tgl {
    text-align: center;
    font-size: 9pt;
    width: 36mm;
    padding: 6px 6px;
    vertical-align: middle;
    border-right: 1.5px solid #2563a8;
}
.td-tgl .tgl-label { font-weight:bold; display:block; }
.td-tgl .tgl-val   { display:block; margin-top:3px; }
.td-ditetapkan {
    text-align: center;
    font-size: 9pt;
    padding: 6px 8px;
    vertical-align: top;
}
.td-ditetapkan .dit-label  { font-weight: normal; display:block; margin-bottom:30px; }
.td-ditetapkan .dit-name   { font-weight: bold; font-size:9pt; display:block; color:#1a2e3b; }
.td-ditetapkan .dit-jabatan{ font-size:8.5pt; display:block; color:#444; }

/* ─── Baris konten: PENGERTIAN dst ─── */
.td-section-label {
    font-weight: bold;
    font-size: 9.5pt;
    text-transform: uppercase;
    vertical-align: top;
    padding: 6px 8px;
    width: 38mm;
    white-space: nowrap;
    border-right: 1.5px solid #2563a8;
    background: #fff;
}
.td-section-content {
    font-size: 10pt;
    vertical-align: top;
    padding: 6px 8px;
    min-height: 30px;
    line-height: 1.5;
}

/* ─── Print / Screen ─── */
@media print {
    .no-print { display:none!important; }
    body { background:#fff; }
    .page { margin:0; padding:8mm 11mm 12mm 11mm; box-shadow:none; }
    @page { size: A4 portrait; margin:0; }
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
    style="background:#2563a8;color:#fff;border:none;padding:8px 24px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:700">
    🖨 Cetak / Simpan PDF
  </button>
  <a href="javascript:history.back()"
    style="background:#e5e7eb;color:#374151;padding:8px 20px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:600">
    ← Kembali
  </a>
</div>

<div class="page">
<table class="spo-table">

  <!-- BARIS 1: Logo + Judul -->
  <tr>
    <td class="td-logo" rowspan="3">
      <?php if ($logo_b64): ?>
      <img src="<?= $logo_b64 ?>" alt="Logo RS THB">
      <?php else: ?>
      <div style="width:52px;height:52px;background:#2563a8;border-radius:8px;margin:0 auto"></div>
      <?php endif; ?>
      <div class="rs-name">Rumah Sakit<br>Taman Harapan Baru</div>
    </td>
    <td class="td-judul" colspan="3">
      <?= htmlspecialchars($judul ?: 'XXXXXXXXXX (NAMA/JUDUL SPO)') ?>
    </td>
  </tr>

  <!-- BARIS 2: No Dokumen / No Revisi / Halaman -->
  <tr>
    <td class="td-meta" style="border-left:1.5px solid #2563a8">
      <span class="meta-label">No. Dokumen</span>
      <span class="meta-val"><?= htmlspecialchars($no_dokumen ?: 'XXXXXXX') ?></span>
    </td>
    <td class="td-meta">
      <span class="meta-label">No. Revisi</span>
      <span class="meta-val"><?= htmlspecialchars($no_revisi ?: 'XXXXXXXX') ?></span>
    </td>
    <td class="td-meta" style="border-right:0">
      <span class="meta-label">Halaman</span>
      <span class="meta-val"><?= htmlspecialchars($halaman ?: 'x/x') ?></span>
    </td>
  </tr>

  <!-- BARIS 3: SPO label + Tanggal Terbit + Ditetapkan -->
  <tr>
    <td class="td-tgl" style="border-left:1.5px solid #2563a8">
      <span class="tgl-label">Tanggal Terbit</span>
      <span class="tgl-val"><?= htmlspecialchars($tgl_tampil) ?></span>
      <span style="font-size:8pt;color:#666;display:block;margin-top:2px">(tanggal, bulan, tahun)</span>
    </td>
    <td class="td-ditetapkan" colspan="2" style="border-right:0">
      <span class="dit-label">Ditetapkan Oleh,</span>
      <span class="dit-name"><?= htmlspecialchars($direktur) ?></span>
      <span class="dit-jabatan"><?= htmlspecialchars($jabatan_dir) ?></span>
    </td>
  </tr>

  <!-- BARIS 4: Baris khusus label SPO (kolom kiri) -->
  <tr>
    <td class="td-spo-label" style="text-align:center;letter-spacing:.5px;line-height:1.6">
      STANDAR<br>PROSEDUR<br>OPERASIONAL
    </td>
    <td colspan="3" style="padding:0">
      <!-- Kosong — bagian kanan baris 3 sudah terisi "Ditetapkan Oleh" -->
    </td>
  </tr>

  <!-- ─────────────── KONTEN SEKSI ─────────────── -->
  <tr>
    <td class="td-section-label">PENGERTIAN</td>
    <td class="td-section-content" colspan="3">
      <?= nl2br(htmlspecialchars($pengertian ?: 'Xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')) ?>
    </td>
  </tr>
  <tr>
    <td class="td-section-label">TUJUAN</td>
    <td class="td-section-content" colspan="3">
      <?= nl2br(htmlspecialchars($tujuan ?: 'Xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')) ?>
    </td>
  </tr>
  <tr>
    <td class="td-section-label">KEBIJAKAN</td>
    <td class="td-section-content" colspan="3">
      <?= nl2br(htmlspecialchars($kebijakan ?: 'Xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')) ?>
    </td>
  </tr>
  <tr>
    <td class="td-section-label">PROSEDUR</td>
    <td class="td-section-content" colspan="3">
      <?= nl2br(htmlspecialchars($prosedur ?: 'Xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')) ?>
    </td>
  </tr>
  <tr>
    <td class="td-section-label">UNIT TERKAIT</td>
    <td class="td-section-content" colspan="3">
      <?= nl2br(htmlspecialchars($unit_terkait ?: 'Xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')) ?>
    </td>
  </tr>
  <tr>
    <td class="td-section-label">UNIT PEMBUAT</td>
    <td class="td-section-content" colspan="3">
      <?= nl2br(htmlspecialchars($unit_pembuat ?: 'Xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')) ?>
    </td>
  </tr>

</table>
</div><!-- /page -->

</body>
</html>
