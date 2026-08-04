<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }

$agenda_id    = (int)($_GET['id'] ?? 0);
$jumlah_baris = max(10, (int)($_GET['baris'] ?? 15));
$agenda       = null;

if ($agenda_id) {
    try {
        $st = $pdo->prepare("SELECT * FROM sekretariat_agenda WHERE id = ?");
        $st->execute([$agenda_id]);
        $agenda = $st->fetch();
    } catch (Exception $e) { $agenda = null; }
}

function namaHariID(string $tgl): string {
    $map = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    return $map[(int)date('w', strtotime($tgl))];
}
function fmtTgl(string $tgl): string {
    $m=['','Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'];
    $dt = new DateTime($tgl);
    return $dt->format('d').' '.$m[(int)$dt->format('n')].' '.$dt->format('Y');
}

$nama_meeting = $agenda['judul_agenda'] ?? '';
$hari_tanggal = $agenda ? namaHariID($agenda['tanggal']).', '.fmtTgl($agenda['tanggal']) : '';
$waktu_mulai  = $agenda && $agenda['waktu'] ? substr($agenda['waktu'],0,5) : '';
$lokasi       = $agenda['lokasi'] ?? '';
$peserta_arr  = [];
if ($agenda && !empty($agenda['peserta'])) {
    try { $peserta_arr = json_decode($agenda['peserta'], true) ?: []; } catch(Exception $e){}
}
$peserta_str = implode(', ', $peserta_arr);

$logo_path = __DIR__ . '/assets/logo.png';
$logo_b64  = file_exists($logo_path) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo_path)) : '';

$today_str = date('d').' '.['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][(int)date('n')].' '.date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Notulen Meeting - <?= htmlspecialchars($nama_meeting ?: 'Kosong') ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: Arial, sans-serif;
    font-size: 10.5pt;
    color: #1a1a1a;
    background: #fff;
}

.page {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 10mm 14mm 14mm 14mm;
    background: #fff;
}

/* ── KOP ── */
.kop {
    display: flex;
    align-items: center;
    border-bottom: 3px solid #1a5f6e;
    padding-bottom: 7px;
    margin-bottom: 3px;
    position: relative;
    overflow: hidden;
}
.kop-bg-accent {
    position: absolute;
    right: -10px; top: -10px;
    width: 80px; height: 80px;
    background: linear-gradient(135deg, #1a5f6e 0%, #c8a84b 100%);
    border-radius: 50%;
    opacity: .18;
}
.kop-logo { width: 58px; flex-shrink:0; margin-right: 10px; }
.kop-logo img { width: 100%; }
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
    font-size: 7pt;
    color: #444;
    text-align: right;
    line-height: 1.8;
    flex-shrink: 0;
    max-width: 180px;
}
.kop-ornament {
    position: absolute; right: 8px; top: 2px;
    width: 22px; height: 22px;
    background: #c8a84b; border-radius: 50%; opacity: .65;
}
.kop-ornament2 {
    position: absolute; right: 26px; top: -4px;
    width: 12px; height: 12px;
    background: #1a5f6e; border-radius: 50%; opacity: .45;
}
.kop-line2 {
    border-bottom: 1.5px solid #1a5f6e;
    margin-bottom: 16px;
}

/* ── JUDUL ── */
.doc-title {
    text-align: center;
    font-size: 12.5pt;
    font-weight: bold;
    text-decoration: underline;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin: 8px 0 14px 0;
}

/* ── INFO ── */
.info-block { margin-bottom: 16px; }
.info-row {
    display: flex;
    align-items: flex-start;
    margin-bottom: 4px;
    font-size: 10pt;
}
.info-label { width: 110px; flex-shrink:0; font-weight: normal; }
.info-sep   { width: 12px; flex-shrink:0; }
.info-val   {
    flex:1;
    border-bottom: 1px solid #333;
    min-height: 16px;
    padding-bottom: 1px;
    line-height: 1.3;
}
.info-val-inline {
    display: flex;
    align-items: center;
    gap: 4px;
    flex:1;
}
.info-val-inline .seg {
    flex:1;
    border-bottom: 1px solid #333;
    min-height: 16px;
}
.info-val-inline .mid-label {
    font-size: 9pt;
    flex-shrink:0;
}

/* ── TABEL NOTULEN ── */
.notulen-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
    table-layout: fixed;
}
.notulen-table th {
    background: #fff;
    border: 1.5px solid #222;
    padding: 5px 6px;
    font-size: 9.5pt;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
    vertical-align: middle;
}
.notulen-table td {
    border: 1px solid #444;
    height: 28px;
    padding: 0;
    vertical-align: top;
}
.col-peserta { width: 28%; }
.col-topik   { width: 36%; }
.col-tanggap { width: 36%; }

/* ── TANDA TANGAN ── */
.footer-sign {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    padding: 0 10px;
}
.sign-col { text-align: center; font-size: 9.5pt; }
.sign-space { height: 44px; }
.sign-line {
    border-top: 1px solid #333;
    min-width: 130px;
    display: inline-block;
    padding-top: 2px;
}

/* ── PRINT ── */
@media print {
    .no-print { display:none !important; }
    body { background:#fff; }
    .page { margin:0; padding:9mm 13mm 12mm 13mm; box-shadow:none; }
    @page { size: A4 portrait; margin: 0; }
}
@media screen {
    body { background: #e5e7eb; }
    .page { box-shadow: 0 4px 28px rgba(0,0,0,.16); margin: 20px auto; }
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
  <a href="sekretariat_agenda.php"
    style="background:#e5e7eb;color:#374151;padding:8px 20px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center">
    ← Kembali
  </a>
  <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#374151;font-family:sans-serif">
    Baris tabel:
    <select onchange="location='?id=<?= $agenda_id ?>&baris='+this.value"
      style="border:1px solid #d1d5db;border-radius:6px;padding:4px 8px;font-size:12px">
      <?php foreach ([10,15,20,25,30] as $n): ?>
      <option value="<?= $n ?>" <?= $jumlah_baris==$n?'selected':'' ?>><?= $n ?> baris</option>
      <?php endforeach; ?>
    </select>
  </label>
</div>

<div class="page">

  <!-- KOP SURAT -->
  <div class="kop">
    <div class="kop-bg-accent"></div>
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

  <!-- JUDUL -->
  <div class="doc-title">Notulen Meeting</div>

  <!-- INFO MEETING -->
  <div class="info-block">
    <div class="info-row">
      <span class="info-label">Nama Meeting</span>
      <span class="info-sep">:</span>
      <span class="info-val"><?= htmlspecialchars($nama_meeting) ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Hari, Tanggal</span>
      <span class="info-sep">:</span>
      <span class="info-val"><?= htmlspecialchars($hari_tanggal) ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Pukul</span>
      <span class="info-sep">:</span>
      <div class="info-val-inline">
        <span class="seg"><?= htmlspecialchars($waktu_mulai) ?></span>
        <span class="mid-label">s.d</span>
        <span class="seg"></span>
        <span class="mid-label" style="font-size:8pt;color:#666">WIB</span>
      </div>
    </div>
    <div class="info-row">
      <span class="info-label">Peserta Meeting</span>
      <span class="info-sep">:</span>
      <span class="info-val"><?= htmlspecialchars($peserta_str) ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Pimpinan Rapat</span>
      <span class="info-sep">:</span>
      <span class="info-val"></span>
    </div>
    <div class="info-row">
      <span class="info-label">Notulensi</span>
      <span class="info-sep">:</span>
      <span class="info-val"></span>
    </div>
  </div>

  <!-- TABEL NOTULEN -->
  <table class="notulen-table">
    <thead>
      <tr>
        <th class="col-peserta">PESERTA / UNIT/<br>DEPARTEMEN</th>
        <th class="col-topik">TOPIK</th>
        <th class="col-tanggap">TANGGAPAN</th>
      </tr>
    </thead>
    <tbody>
      <?php for ($i = 0; $i < $jumlah_baris; $i++): ?>
      <tr>
        <td class="col-peserta"></td>
        <td class="col-topik"></td>
        <td class="col-tanggap"></td>
      </tr>
      <?php endfor; ?>
    </tbody>
  </table>

  <!-- TANDA TANGAN -->
  <div class="footer-sign">
    <div class="sign-col">
      <div>Notulensi,</div>
      <div class="sign-space"></div>
      <div class="sign-line">( ______________________ )</div>
    </div>
    <div class="sign-col">
      <div>Bekasi, <?= $today_str ?></div>
      <div>Pimpinan Rapat,</div>
      <div class="sign-space"></div>
      <div class="sign-line">( ______________________ )</div>
    </div>
  </div>

</div><!-- /page -->
</body>
</html>
