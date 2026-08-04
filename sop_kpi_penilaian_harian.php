<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
if (!hasPermission('sop_view')) {
    header("Location: dashboard.php");
    exit;
}

$user       = $_SESSION['user'];
$userRole   = $user['nama_role'] ?? $user['role'] ?? '';
$userId     = (int)($user['id'] ?? 0);

// Tentukan level akses KPI
// Admin HRD & Super Admin : bisa pilih semua karyawan, input nilai
// Atasan                  : bisa pilih karyawan di bawahnya, input nilai
// Lainnya (Karyawan)      : hanya lihat nilai sendiri (read-only)
$isAdminHRD  = in_array($userRole, ['Super Admin', 'Admin HRD', 'HRD']);
$isAtasan    = in_array($userRole, ['Atasan', 'Supervisor', 'Kepala Unit', 'Kepala Ruangan']);
$isKaryawan  = !$isAdminHRD && !$isAtasan;
$canEdit     = $isAdminHRD || $isAtasan;

// ─────────────────────────────────────────────
// Simpan Penilaian Harian (POST)
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save' && $canEdit) {
    $karyawan_id = (int)$_POST['karyawan_id'];
    $bulan       = (int)$_POST['bulan'];
    $tahun       = (int)$_POST['tahun'];
    $nilai_arr   = $_POST['nilai'] ?? [];

    // Validasi: Atasan hanya boleh simpan data bawahannya
    if ($isAtasan) {
        $chk = $pdo->prepare("SELECT id FROM kpi_karyawan WHERE id = ? AND atasan_id = ?");
        $chk->execute([$karyawan_id, $userId]);
        if (!$chk->fetch()) {
            $error = "Anda tidak memiliki izin menilai karyawan ini.";
            goto render;
        }
    }

    try {
        $pdo->beginTransaction();

        // Hapus nilai lama periode ini
        $pdo->prepare("DELETE FROM kpi_penilaian_harian WHERE karyawan_id=? AND bulan=? AND tahun=?")
            ->execute([$karyawan_id, $bulan, $tahun]);

        // Insert nilai baru
        $stmtIns = $pdo->prepare("
            INSERT INTO kpi_penilaian_harian (karyawan_id, kriteria_id, hari, bulan, tahun, nilai, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($nilai_arr as $kriteria_id => $hari_arr) {
            foreach ($hari_arr as $hari => $nilai) {
                $nilai = trim($nilai);
                if ($nilai !== '') {
                    $stmtIns->execute([$karyawan_id, (int)$kriteria_id, (int)$hari, $bulan, $tahun, $nilai, $user['nama'] ?? 'Admin']);
                }
            }
        }

        $pdo->commit();
        header("Location: sop_kpi_penilaian_harian.php?karyawan_id={$karyawan_id}&bulan={$bulan}&tahun={$tahun}&success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

render:

// ─────────────────────────────────────────────
// Parameter filter
// ─────────────────────────────────────────────
$bulan_sel   = (int)($_GET['bulan']       ?? date('m'));
$tahun_sel   = (int)($_GET['tahun']       ?? date('Y'));
$karyawan_id = (int)($_GET['karyawan_id'] ?? 0);

// Jumlah hari dalam bulan
$jumlah_hari = (int)date('t', mktime(0, 0, 0, $bulan_sel, 1, $tahun_sel));

// Nama hari singkat
function getNamaHari(int $hari, int $bulan, int $tahun): string {
    return ['M','S','S','R','K','J','S'][(int)date('w', mktime(0,0,0,$bulan,$hari,$tahun))];
}

// ─────────────────────────────────────────────
// Daftar karyawan sesuai role
// ─────────────────────────────────────────────
if ($isAdminHRD) {
    // Semua karyawan aktif
    $stmtK = $pdo->query("SELECT id, nama, jabatan, unit FROM kpi_karyawan WHERE status='Aktif' ORDER BY nama ASC");
    $karyawanList = $stmtK->fetchAll();
} elseif ($isAtasan) {
    // Karyawan yang user_id-nya terhubung ke atasan ini
    $stmtK = $pdo->prepare("SELECT id, nama, jabatan, unit FROM kpi_karyawan WHERE status='Aktif' AND atasan_id=? ORDER BY nama ASC");
    $stmtK->execute([$userId]);
    $karyawanList = $stmtK->fetchAll();
} else {
    // Karyawan hanya lihat diri sendiri
    $stmtK = $pdo->prepare("SELECT id, nama, jabatan, unit FROM kpi_karyawan WHERE status='Aktif' AND user_id=? LIMIT 1");
    $stmtK->execute([$userId]);
    $karyawanList = $stmtK->fetchAll();
    // Auto-set karyawan_id jika karyawan hanya punya 1 data
    if (!$karyawan_id && count($karyawanList) === 1) {
        $karyawan_id = $karyawanList[0]['id'];
    }
}

// ─────────────────────────────────────────────
// Kriteria dikelompokkan per kategori
// ─────────────────────────────────────────────
$stmtKr = $pdo->query("SELECT * FROM kpi_kriteria ORDER BY kategori ASC, id ASC");
$kriteriaRaw = $stmtKr->fetchAll();
$kriteriaByKategori = [];
foreach ($kriteriaRaw as $kr) {
    $kriteriaByKategori[$kr['kategori']][] = $kr;
}

// ─────────────────────────────────────────────
// Nilai yang sudah tersimpan
// ─────────────────────────────────────────────
$nilaiExisting = [];
if ($karyawan_id) {
    try {
        $stmtN = $pdo->prepare("SELECT kriteria_id, hari, nilai FROM kpi_penilaian_harian WHERE karyawan_id=? AND bulan=? AND tahun=?");
        $stmtN->execute([$karyawan_id, $bulan_sel, $tahun_sel]);
        foreach ($stmtN->fetchAll() as $row) {
            $nilaiExisting[$row['kriteria_id']][$row['hari']] = $row['nilai'];
        }
    } catch (PDOException $e) {
        $nilaiExisting = [];
    }
}

// ─────────────────────────────────────────────
// Info karyawan terpilih
// ─────────────────────────────────────────────
$karyawanTerpilih = null;
foreach ($karyawanList as $k) {
    if ($k['id'] == $karyawan_id) { $karyawanTerpilih = $k; break; }
}

$bulanList = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
    5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
    9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penilaian Harian KPI - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ── Tabel penilaian ── */
        .tbl-penilaian { border-collapse: collapse; }
        .tbl-penilaian th,
        .tbl-penilaian td {
            padding: 5px 3px !important;
            font-size: 11px !important;
            text-align: center !important;
            border: 1px solid #e2e8f0 !important;
            white-space: nowrap;
            background: #fff;
        }
        .tbl-penilaian thead th {
            background: #f8fafc !important;
            font-weight: 700 !important;
            color: #475569 !important;
            position: sticky;
            top: 0;
            z-index: 3;
        }
        /* Freeze 3 kolom pertama */
        .tbl-penilaian th:nth-child(1),
        .tbl-penilaian td:nth-child(1) { position: sticky; left: 0;     z-index: 2; background: #f8fafc !important; width: 32px; }
        .tbl-penilaian th:nth-child(2),
        .tbl-penilaian td:nth-child(2) { position: sticky; left: 32px;  z-index: 2; background: #f8fafc !important; width: 150px; text-align:left!important; }
        .tbl-penilaian th:nth-child(3),
        .tbl-penilaian td:nth-child(3) { position: sticky; left: 182px; z-index: 2; background: #f8fafc !important; width: 52px; }
        .tbl-penilaian thead th:nth-child(1),
        .tbl-penilaian thead th:nth-child(2),
        .tbl-penilaian thead th:nth-child(3) { z-index: 4 !important; }

        /* Kategori header row */
        .row-kategori td {
            background: #dde8e8 !important;
            font-weight: 700 !important;
            font-size: 11px !important;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #1e4040 !important;
            text-align: left !important;
            padding: 7px 10px !important;
        }

        /* Input kotak nilai */
        .tbl-penilaian input.nilai-input {
            width: 30px !important;
            height: 26px !important;
            text-align: center !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 4px !important;
            padding: 1px !important;
            font-size: 11px !important;
            background: #fff !important;
            transition: border-color .15s;
        }
        .tbl-penilaian input.nilai-input:focus {
            border-color: #0d9488 !important;
            box-shadow: 0 0 0 2px rgba(13,148,136,.15) !important;
            outline: none !important;
        }
        /* Read-only kotak */
        .tbl-penilaian .nilai-readonly {
            display: inline-block;
            width: 30px;
            height: 26px;
            line-height: 26px;
            text-align: center;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            background: #f1f5f9;
            font-size: 11px;
            color: #334155;
        }
        /* Hari akhir pekan */
        .col-weekend { background: #fafafa !important; }

        /* Scroll wrapper */
        .scroll-tbl { overflow-x: auto; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex">
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php include 'includes/header.php'; ?>

    <div class="flex-1 p-6 overflow-y-auto">

        <!-- ── Page header ── -->
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="clipboard-check" class="w-6 h-6 text-teal-600"></i>
                    Penilaian Harian KPI
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    <?php if ($isAdminHRD): ?>Admin HRD — dapat menilai semua karyawan
                    <?php elseif ($isAtasan): ?>Atasan — dapat menilai karyawan di bawah Anda
                    <?php else: ?>Karyawan — melihat nilai kinerja Anda
                    <?php endif; ?>
                </p>
            </div>
            <!-- Badge role -->
            <span class="px-3 py-1 rounded-full text-xs font-bold
                <?php echo $isAdminHRD ? 'bg-purple-100 text-purple-700' : ($isAtasan ? 'bg-blue-100 text-blue-700' : 'bg-teal-100 text-teal-700'); ?>">
                <?= htmlspecialchars($userRole) ?>
            </span>
        </div>

        <!-- ── Alert ── -->
        <?php if (isset($_GET['success'])): ?>
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2 text-sm">
            <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
            Penilaian harian berhasil disimpan!
        </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2 text-sm">
            <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- ── Filter ── -->
        <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
            <div class="flex flex-wrap items-end gap-3">
                <?php if (!$isKaryawan): ?>
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Karyawan</label>
                    <select name="karyawan_id" class="w-full border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="">-- Pilih Karyawan --</option>
                        <?php foreach ($karyawanList as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $karyawan_id==$k['id']?'selected':'' ?>>
                            <?= htmlspecialchars($k['nama'].' ('.$k['unit'].')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                    <?php if ($karyawanTerpilih): ?>
                    <input type="hidden" name="karyawan_id" value="<?= $karyawan_id ?>">
                    <?php endif; ?>
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
                    <select name="bulan" class="border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 focus:ring-2 focus:ring-teal-500 outline-none">
                        <?php foreach ($bulanList as $b => $nm): ?>
                        <option value="<?= $b ?>" <?= $bulan_sel==$b?'selected':'' ?>><?= $nm ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
                    <select name="tahun" class="border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 focus:ring-2 focus:ring-teal-500 outline-none">
                        <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?>
                        <option value="<?= $y ?>" <?= $tahun_sel==$y?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="px-5 py-2 bg-teal-600 text-white rounded-xl font-semibold text-sm hover:bg-teal-700 transition-colors flex items-center gap-1.5">
                    <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
                </button>
            </div>
        </form>

        <?php if ($karyawan_id && $karyawanTerpilih && !empty($kriteriaRaw)): ?>

        <!-- ── Info karyawan ── -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 mb-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-teal-500 to-emerald-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                <?= mb_strtoupper(mb_substr($karyawanTerpilih['nama'], 0, 1)) ?>
            </div>
            <div class="flex-1">
                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($karyawanTerpilih['nama']) ?></p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($karyawanTerpilih['jabatan'].' · '.$karyawanTerpilih['unit']) ?></p>
            </div>
            <div class="text-right">
                <p class="text-sm font-semibold text-gray-700"><?= $bulanList[$bulan_sel].' '.$tahun_sel ?></p>
                <p class="text-xs text-gray-400"><?= $jumlah_hari ?> hari</p>
            </div>
            <?php if ($isKaryawan): ?>
            <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-lg text-xs font-medium">
                <i data-lucide="eye" class="w-3 h-3 inline mr-1"></i>Mode Lihat
            </span>
            <?php endif; ?>
        </div>

        <!-- ── Tabel penilaian ── -->
        <?php if ($canEdit): ?>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="karyawan_id" value="<?= $karyawan_id ?>">
            <input type="hidden" name="bulan" value="<?= $bulan_sel ?>">
            <input type="hidden" name="tahun" value="<?= $tahun_sel ?>">
        <?php endif; ?>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-4">
            <div class="scroll-tbl">
                <table class="tbl-penilaian w-full">
                    <thead>
                        <!-- Baris nomor hari -->
                        <tr>
                            <th>NO</th>
                            <th style="text-align:left!important">KATEGORI / INDIKATOR</th>
                            <th>BOBOT</th>
                            <?php for ($h=1; $h<=$jumlah_hari; $h++):
                                $dow = (int)date('w', mktime(0,0,0,$bulan_sel,$h,$tahun_sel));
                                $isWE = ($dow===0||$dow===6);
                            ?>
                            <th class="<?= $isWE?'col-weekend':'' ?>" style="width:34px"><?= $h ?></th>
                            <?php endfor; ?>
                        </tr>
                        <!-- Baris nama hari -->
                        <tr>
                            <th></th><th></th><th></th>
                            <?php for ($h=1; $h<=$jumlah_hari; $h++):
                                $dow = (int)date('w', mktime(0,0,0,$bulan_sel,$h,$tahun_sel));
                                $isWE = ($dow===0||$dow===6);
                            ?>
                            <th class="<?= $isWE?'col-weekend':'' ?>"
                                style="font-size:10px!important;font-weight:500!important;color:<?= $isWE?'#ef4444':'#64748b' ?>!important">
                                <?= getNamaHari($h,$bulan_sel,$tahun_sel) ?>
                            </th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $noUrut = 1; ?>
                    <?php foreach ($kriteriaByKategori as $kategori => $items): ?>
                        <!-- Header kategori -->
                        <tr class="row-kategori">
                            <td colspan="<?= 3+$jumlah_hari ?>">
                                <?= htmlspecialchars($kategori) ?>
                            </td>
                        </tr>
                        <!-- Baris indikator -->
                        <?php foreach ($items as $kr): ?>
                        <tr class="hover:bg-slate-50/50">
                            <td class="text-gray-400 font-medium"><?= $noUrut++ ?></td>
                            <td style="text-align:left!important;padding-left:12px!important;font-size:11.5px!important;color:#1e293b!important">
                                <?= htmlspecialchars($kr['nama_indikator']) ?>
                            </td>
                            <td class="text-gray-500 font-medium"><?= number_format($kr['bobot'],1) ?>%</td>
                            <?php for ($h=1; $h<=$jumlah_hari; $h++):
                                $dow = (int)date('w', mktime(0,0,0,$bulan_sel,$h,$tahun_sel));
                                $isWE = ($dow===0||$dow===6);
                                $val  = $nilaiExisting[$kr['id']][$h] ?? '';
                            ?>
                            <td class="<?= $isWE?'col-weekend':'' ?>">
                                <?php if ($canEdit): ?>
                                <input type="text"
                                       class="nilai-input"
                                       name="nilai[<?= $kr['id'] ?>][<?= $h ?>]"
                                       value="<?= htmlspecialchars($val) ?>"
                                       maxlength="3"
                                       inputmode="numeric"
                                       title="<?= htmlspecialchars($kr['nama_indikator']) ?> - Hari <?= $h ?>">
                                <?php else: ?>
                                <span class="nilai-readonly"><?= htmlspecialchars($val) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Tombol Simpan (hanya jika bisa edit) ── -->
        <?php if ($canEdit): ?>
        <div class="flex justify-end gap-3 mb-6">
            <a href="sop_kpi_penilaian_harian.php?karyawan_id=<?= $karyawan_id ?>&bulan=<?= $bulan_sel ?>&tahun=<?= $tahun_sel ?>"
               class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-xl hover:bg-gray-50 font-medium transition-colors text-sm flex items-center gap-1.5">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Reset
            </a>
            <button type="submit"
                    class="px-6 py-2.5 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-semibold shadow-md transition-colors flex items-center gap-2 text-sm">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Penilaian
            </button>
        </div>
        </form>
        <?php endif; ?>

        <?php elseif ($karyawan_id && empty($kriteriaRaw)): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-4 rounded-xl text-sm flex items-start gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
            <span>Belum ada kriteria penilaian. Silakan tambahkan di menu
                <a href="sop_kpi_kriteria.php" class="underline font-semibold">Kriteria Penilaian</a>.
            </span>
        </div>

        <?php elseif ($isKaryawan && empty($karyawanList)): ?>
        <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-4 rounded-xl text-sm flex items-start gap-2">
            <i data-lucide="info" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
            <span>Akun Anda belum terhubung ke data karyawan. Hubungi Admin HRD untuk menghubungkan akun Anda.</span>
        </div>

        <?php else: ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-14 text-center text-gray-400">
            <i data-lucide="clipboard-list" class="w-14 h-14 mx-auto mb-3 opacity-20"></i>
            <p class="text-sm font-medium">Pilih karyawan dan periode untuk menampilkan tabel penilaian.</p>
        </div>
        <?php endif; ?>

    </div><!-- /p-6 -->
</main>

<script>
lucide.createIcons();

// Navigasi keyboard antar sel input (Tab = kanan, Shift+Tab = kiri)
document.addEventListener('DOMContentLoaded', function () {
    const inputs = Array.from(document.querySelectorAll('.nilai-input'));
    inputs.forEach(function (inp, idx) {
        // Auto-select on focus
        inp.addEventListener('focus', function () { this.select(); });
        // Enter = pindah ke bawah (kriteria berikut, hari sama)
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Hitung jumlah kolom hari
                const cols = <?= $jumlah_hari ?>;
                const nextIdx = idx + cols;
                if (inputs[nextIdx]) inputs[nextIdx].focus();
            }
        });
    });
});
</script>
</body>
</html>
