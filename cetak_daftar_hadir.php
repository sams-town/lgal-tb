<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }

// Ambil data agenda jika ada id
$agenda_id  = (int)($_GET['id'] ?? 0);
$agenda     = null;
$jumlah_baris = max(20, (int)($_GET['baris'] ?? 25));

if ($agenda_id) {
    try {
        $st = $pdo->prepare("SELECT * FROM sekretariat_agenda WHERE id = ?");
        $st->execute([$agenda_id]);
        $agenda = $st->fetch();
    } catch (Exception $e) { $agenda = null; }
}

// Nama hari Indonesia
function namaHariID(string $tgl): string {
    $map = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    return $map[(int)date('w', strtotime($tgl))];
}
function fmtTgl(string $tgl): string {
    $m=['','Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'];
    $dt = new DateTime($tgl);
    return $dt->format('d') . ' ' . $m[(int)$dt->format('n')] . ' ' . $dt->format('Y');
}

$hari_tanggal = $agenda ? namaHariID($agenda['tanggal']) . ', ' . fmtTgl($agenda['tanggal']) : '';
$waktu        = $agenda && $agenda['waktu'] ? substr($agenda['waktu'], 0, 5) . ' WIB' : '';
$lokasi       = $agenda['lokasi'] ?? '';
$judul        = $agenda['judul_agenda'] ?? '';

// Base64 logo agar bisa dicetak tanpa path server
$logo_path = __DIR__ . '/assets/logo.png';
$logo_b64  = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Daftar Hadir - <?= htmlspecialchars($judul ?: 'Kosong') ?></title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 11pt;
    color: #1a1a1a;
    background: #fff;
  }

  /* ── Wrapper kertas A4 ── */
  .page {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 12mm 14mm 14mm 14mm;
    background: #fff;
  }

  /* ── Kop surat ── */
  .kop {
    display: flex;
    align-items: center;
    border-bottom: 3px solid #1a5f6e;
    padding-bottom: 8px;
    margin-bottom: 4px;
    position: relative;
  }
  .kop-logo {
    width: 62px;
    flex-shrink: 0;
    margin-right: 12px;
  }
  .kop-logo img { width: 100%; }
  .kop-text { flex: 1; }
  .kop-text .rs-label {
    font-size: 8pt;
    font-family: Arial, sans-serif;
    color: #1a5f6e;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 600;
  }
  .kop-text .rs-name {
    font-size: 18pt;
    font-family: Arial, sans-serif;
    font-weight: 900;
    color: #1a2e3b;
    line-height: 1.1;
    letter-spacing: -0.3px;
  }
  .kop-contact {
    font-size: 7.5pt;
    font-family: Arial, sans-serif;
    color: #444;
    text-align: right;
    line-height: 1.7;
    flex-shrink: 0;
  }
  .kop-contact span { display: block; }
  .kop-ornament {
    position: absolute;
    right: 0;
    top: 0;
    width: 28px;
    height: 28px;
    background: #c8a84b;
    border-radius: 50%;
    opacity: .7;
  }
  .kop-ornament2 {
    position: absolute;
    right: 20px;
    top: -4px;
    width: 14px;
    height: 14px;
    background: #1a5f6e;
    border-radius: 50%;
    opacity: .5;
  }
  .kop-line2 {
    border-bottom: 1.5px solid #1a5f6e;
    margin-bottom: 18px;
  }

  /* ── Judul ── */
  .doc-title {
    text-align: center;
    font-size: 13pt;
    font-weight: bold;
    letter-spacing: 2px;
    text-decoration: underline;
    text-transform: uppercase;
    margin: 10px 0 16px 0;
    font-family: Arial, sans-serif;
  }

  /* ── Info acara ── */
  .info-table {
    width: 100%;
    margin-bottom: 18px;
    border-collapse: collapse;
  }
  .info-table td {
    padding: 2px 0;
    vertical-align: top;
    font-size: 10.5pt;
  }
  .info-table td.label {
    width: 80px;
    font-weight: normal;
  }
  .info-table td.sep {
    width: 14px;
    text-align: center;
  }
  .info-table td.val {
    border-bottom: 1px solid #333;
    padding-bottom: 1px;
    min-width: 260px;
  }
  .info-table td.val-empty {
    border-bottom: 1px solid #333;
    padding-bottom: 1px;
    min-width: 260px;
    color: transparent; /* baris kosong */
  }

  /* ── Tabel daftar hadir ── */
  .hadir-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
  }
  .hadir-table th {
    background: #fff;
    border: 1px solid #333;
    padding: 5px 6px;
    font-size: 10pt;
    font-weight: bold;
    text-align: center;
    font-family: Arial, sans-serif;
    text-transform: uppercase;
  }
  .hadir-table td {
    border: 1px solid #333;
    padding: 0;
    height: 22px;
  }
  .hadir-table td.no {
    width: 28px;
    text-align: center;
    font-size: 9.5pt;
    vertical-align: middle;
    padding: 2px;
  }
  .hadir-table td.nim  { width: 90px; }
  .hadir-table td.nama { width: auto; }
  .hadir-table td.unit { width: 100px; }

  /* ── Footer ── */
  .footer-sign {
    margin-top: 24px;
    display: flex;
    justify-content: flex-end;
  }
  .sign-block {
    text-align: center;
    font-size: 10pt;
    line-height: 1.5;
  }
  .sign-block .sign-space {
    height: 48px;
  }
  .sign-block .sign-name {
    border-top: 1px solid #333;
    padding-top: 2px;
    min-width: 160px;
    display: inline-block;
  }

  /* ── Print ── */
  @media print {
    body { background: #fff; }
    .page { margin: 0; padding: 10mm 13mm 13mm 13mm; }
    .no-print { display: none !important; }
    @page { size: A4 portrait; margin: 0; }
  }
  @media screen {
    body { background: #e5e7eb; }
    .page { box-shadow: 0 0 24px rgba(0,0,0,.18); margin: 24px auto; }
  }
</style>
</head>
<body>

<!-- Tombol aksi (tidak ikut cetak) -->
<div class="no-print" style="text-align:center;padding:12px 0;display:flex;justify-content:center;gap:12px">
  <button onclick="window.print()"
    style="background:#1a5f6e;color:#fff;border:none;padding:8px 24px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:600">
    🖨 Cetak / Simpan PDF
  </button>
  <a href="sekretariat_agenda.php"
    style="background:#e5e7eb;color:#374151;padding:8px 20px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center">
    ← Kembali
  </a>
  <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#374151;font-family:sans-serif">
    Jumlah baris:
    <select onchange="location='?id=<?= $agenda_id ?>&baris='+this.value"
      style="border:1px solid #d1d5db;border-radius:6px;padding:4px 8px;font-size:12px">
      <?php foreach ([15,20,25,30,35,40] as $n): ?>
      <option value="<?= $n ?>" <?= $jumlah_baris==$n?'selected':'' ?>><?= $n ?> baris</option>
      <?php endforeach; ?>
    </select>
  </label>
</div>

<div class="page">

  <!-- KOP SURAT -->
  <div class="kop">
    <div class="kop-logo">
      <?php if ($logo_b64): ?>
      <img src="<?= $logo_b64 ?>" alt="Logo RS THB">
      <?php else: ?>
      <div style="width:62px;height:62px;background:#1a5f6e;border-radius:8px"></div>
      <?php endif; ?>
    </div>
    <div class="kop-text">
      <div class="rs-label">Rumah Sakit</div>
      <div class="rs-name">TAMAN<br>HARAPAN BARU</div>
    </div>
    <div class="kop-contact">
      <span>📍 Jl. Kalabang Tengah Nomor 2, RT.004/RW.023</span>
      <span>Pejuang, Medan Satria, Kota Bekasi 17181</span>
      <span>📞 Telp : (021) 8898 1055</span>
      <span>✉ Email : info@rsthb.id</span>
    </div>
    <div class="kop-ornament"></div>
    <div class="kop-ornament2"></div>
  </div>
  <div class="kop-line2"></div>

  <!-- JUDUL -->
  <div class="doc-title">Daftar Hadir</div>

  <!-- INFO ACARA -->
  <table class="info-table">
    <tr>
      <td class="label">Hari / Tanggal</td>
      <td class="sep">:</td>
      <td class="val"><?= htmlspecialchars($hari_tanggal) ?></td>
    </tr>
    <tr>
      <td class="label">Waktu</td>
      <td class="sep">:</td>
      <td class="val"><?= htmlspecialchars($waktu) ?></td>
    </tr>
    <tr>
      <td class="label">Tempat</td>
      <td class="sep">:</td>
      <td class="val"><?= htmlspecialchars($lokasi) ?></td>
    </tr>
    <tr>
      <td class="label">Agenda</td>
      <td class="sep">:</td>
      <td class="val"><?= htmlspecialchars($judul) ?></td>
    </tr>
  </table>

  <!-- TABEL DAFTAR HADIR -->
  <table class="hadir-table">
    <thead>
      <tr>
        <th style="width:32px">No</th>
        <th style="width:88px">NIM</th>
        <th>NAMA</th>
        <th style="width:110px">UNIT/BAGIAN</th>
        <th style="width:70px">TTD</th>
      </tr>
    </thead>
    <tbody>
      <?php for ($i = 1; $i <= $jumlah_baris; $i++): ?>
      <tr>
        <td class="no"><?= $i ?></td>
        <td class="nim"></td>
        <td class="nama"></td>
        <td class="unit"></td>
        <td></td>
      </tr>
      <?php endfor; ?>
    </tbody>
  </table>

  <!-- Tanda Tangan -->
  <div class="footer-sign">
    <div class="sign-block">
      <div>Bekasi, <?= date('d') . ' ' . ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][(int)date('n')] . ' ' . date('Y') ?></div>
      <div>Mengetahui,</div>
      <div class="sign-space"></div>
      <div class="sign-name">( _________________________ )</div>
    </div>
  </div>

</div><!-- /page -->
</body>
</html>
