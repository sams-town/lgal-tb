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

function syncRKKtoKomite($pdo, $karyawan_id) {
    // Cek apakah karyawan ini terhubung dengan tenaga medis komite
    $stmtCheck = $pdo->prepare("SELECT tenaga_medis_id FROM kpi_karyawan WHERE id = ?");
    $stmtCheck->execute([$karyawan_id]);
    $k = $stmtCheck->fetch();
    if ($k && $k['tenaga_medis_id']) {
        // Ambil semua tugas untuk dijadikan rincian
        $stmtTugas = $pdo->prepare("SELECT tugas, deskripsi FROM kpi_rkk_karyawan WHERE karyawan_id = ? ORDER BY jenis DESC, id ASC");
        $stmtTugas->execute([$karyawan_id]);
        $tugasList = $stmtTugas->fetchAll();
        
        $rincian_text = "";
        $no = 1;
        foreach($tugasList as $t) {
            $rincian_text .= $no . ". " . $t['tugas'] . "\n";
            $no++;
        }
        
        // Update ke tenaga_medis
        $stmtUpdate = $pdo->prepare("UPDATE tenaga_medis SET rincian_kewenangan_klinis = ? WHERE id = ?");
        $stmtUpdate->execute([$rincian_text, $k['tenaga_medis_id']]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO kpi_rkk_karyawan (karyawan_id, tugas, deskripsi, jenis) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['karyawan_id'], $_POST['tugas'], $_POST['deskripsi'], $_POST['jenis']]);
        syncRKKtoKomite($pdo, $_POST['karyawan_id']);
        header("Location: sop_kpi_rkk.php?success=add&karyawan_id=".$_POST['karyawan_id']);
        exit;
    } elseif ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE kpi_rkk_karyawan SET tugas=?, deskripsi=?, jenis=? WHERE id=?");
        $stmt->execute([$_POST['tugas'], $_POST['deskripsi'], $_POST['jenis'], $_POST['id']]);
        syncRKKtoKomite($pdo, $_POST['karyawan_id']);
        header("Location: sop_kpi_rkk.php?success=edit&karyawan_id=".$_POST['karyawan_id']);
        exit;
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM kpi_rkk_karyawan WHERE id=?");
        $stmt->execute([$_POST['id']]);
        syncRKKtoKomite($pdo, $_POST['karyawan_id']);
        header("Location: sop_kpi_rkk.php?success=delete&karyawan_id=".$_POST['karyawan_id']);
        exit;
    } elseif ($action === 'copy_template') {
        $karyawan_id = $_POST['karyawan_id'];
        $template_id = $_POST['template_id'];
        
        $stmtTugas = $pdo->prepare("SELECT * FROM kpi_rkk_tugas WHERE template_id = ?");
        $stmtTugas->execute([$template_id]);
        $tugasList = $stmtTugas->fetchAll();
        
        $stmtInsert = $pdo->prepare("INSERT INTO kpi_rkk_karyawan (karyawan_id, tugas, deskripsi, jenis) VALUES (?, ?, ?, ?)");
        foreach($tugasList as $t) {
            $stmtInsert->execute([$karyawan_id, $t['tugas'], $t['deskripsi'], 'Pokok']);
        }
        syncRKKtoKomite($pdo, $karyawan_id);
        header("Location: sop_kpi_rkk.php?success=copy&karyawan_id=".$karyawan_id);
        exit;
    } elseif ($action === 'save_log') {
        // Simpan log harian RKK
        $karyawan_id_log = (int)$_POST['karyawan_id'];
        $hari            = (int)$_POST['hari'];
        $bulan           = (int)$_POST['bulan'];
        $tahun           = (int)$_POST['tahun'];
        $rkk_ids         = $_POST['rkk_ids'] ?? []; // array id tugas yang dicentang

        try {
            $pdo->beginTransaction();
            // Hapus log hari ini dulu
            $pdo->prepare("DELETE FROM kpi_rkk_log WHERE karyawan_id=? AND hari=? AND bulan=? AND tahun=?")
                ->execute([$karyawan_id_log, $hari, $bulan, $tahun]);
            // Insert yang dicentang
            $ins = $pdo->prepare("INSERT INTO kpi_rkk_log (karyawan_id, rkk_id, hari, bulan, tahun, created_by) VALUES (?,?,?,?,?,?)");
            foreach ($rkk_ids as $rid) {
                $ins->execute([$karyawan_id_log, (int)$rid, $hari, $bulan, $tahun, $_SESSION['user']['nama'] ?? 'Admin']);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
        header("Location: sop_kpi_rkk.php?success=log&karyawan_id={$karyawan_id_log}&tab=log&bulan={$bulan}&tahun={$tahun}");
        exit;
    }
}

// Fetch Karyawan
$stmtK = $pdo->query("SELECT id, nama, jabatan, unit FROM kpi_karyawan WHERE status='Aktif' ORDER BY nama ASC");
$karyawanList = $stmtK->fetchAll();

$karyawan_id = $_GET['karyawan_id'] ?? '';
$rkkList = [];
$karyawanData = null;

if ($karyawan_id) {
    // Info karyawan
    $stmtKaryawan = $pdo->prepare("SELECT * FROM kpi_karyawan WHERE id = ?");
    $stmtKaryawan->execute([$karyawan_id]);
    $karyawanData = $stmtKaryawan->fetch();
    
    // List RKK
    $stmtRKK = $pdo->prepare("SELECT * FROM kpi_rkk_karyawan WHERE karyawan_id = ? ORDER BY jenis DESC, id ASC");
    $stmtRKK->execute([$karyawan_id]);
    $rkkList = $stmtRKK->fetchAll();
}

// Fetch Template untuk fitur copy
$stmtTemp = $pdo->query("SELECT id, jabatan, unit FROM kpi_rkk_template ORDER BY jabatan ASC");
$templateList = $stmtTemp->fetchAll();

// ── Parameter log harian ──
$tab_sel   = $_GET['tab']   ?? 'rkk';
$bulan_log = (int)($_GET['bulan'] ?? date('m'));
$tahun_log = (int)($_GET['tahun'] ?? date('Y'));
$hari_log  = (int)($_GET['hari']  ?? date('d'));
$jumlah_hari_log = (int)date('t', mktime(0,0,0,$bulan_log,1,$tahun_log));

// Data log bulan ini
$logBulanIni = [];
if ($karyawan_id) {
    try {
        $stLog = $pdo->prepare("SELECT rkk_id, hari FROM kpi_rkk_log WHERE karyawan_id=? AND bulan=? AND tahun=?");
        $stLog->execute([$karyawan_id, $bulan_log, $tahun_log]);
        foreach ($stLog->fetchAll() as $row) {
            $logBulanIni[$row['hari']][$row['rkk_id']] = true;
        }
    } catch (Exception $e) { $logBulanIni = []; }
}

$bulanList = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
              7=>'Jul',8=>'Ags',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
$bulanFull = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

function namaHariRKK(int $h, int $b, int $y): string {
    return ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][(int)date('w',mktime(0,0,0,$b,$h,$y))];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log RKK / Job Des - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 flex flex-col relative h-screen overflow-hidden">
        <?php include 'includes/header.php'; ?>
        
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Log RKK / Job Des</h1>
                        <p class="text-gray-500 mt-1">Rincian Kewenangan Klinis & Tugas per Karyawan</p>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span><?= $_GET['success']==='log' ? 'Log harian berhasil disimpan! Nilai RKK di penilaian harian otomatis terupdate.' : 'Perubahan berhasil disimpan!' ?></span>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Sidebar: Pilih Karyawan -->
                    <div class="lg:col-span-1 space-y-4">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                            <h3 class="font-bold text-gray-800 mb-4">Pilih Karyawan</h3>
                            <form method="GET">
                                <select name="karyawan_id" onchange="this.form.submit()" class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                    <option value="">-- Pilih Karyawan --</option>
                                    <?php foreach($karyawanList as $k): ?>
                                    <option value="<?= $k['id'] ?>" <?= ($karyawan_id == $k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>

                        <?php if($karyawan_id && !empty($rkkList)): ?>
                        <!-- Ringkasan log bulan ini -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                            <h3 class="font-bold text-gray-800 mb-3 text-sm">Ringkasan Log <?= $bulanFull[$bulan_log] ?></h3>
                            <?php
                            $hariAda = count($logBulanIni);
                            $totalTugas = count($rkkList);
                            ?>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between text-gray-600">
                                    <span>Hari tercatat</span>
                                    <span class="font-bold text-teal-700"><?= $hariAda ?> hari</span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>Total tugas</span>
                                    <span class="font-bold"><?= $totalTugas ?> tugas</span>
                                </div>
                                <?php if($hariAda > 0 && $totalTugas > 0):
                                    $totalNilai = 0;
                                    foreach($logBulanIni as $h => $tugas) {
                                        $totalNilai += round((count($tugas) / $totalTugas) * 5, 1);
                                    }
                                    $rataLog = round($totalNilai / $hariAda, 1);
                                ?>
                                <div class="flex justify-between text-gray-600 border-t pt-2 mt-1">
                                    <span>Rata-rata nilai RKK</span>
                                    <span class="font-bold text-emerald-700"><?= $rataLog ?> / 5</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Main Content -->
                    <div class="lg:col-span-3">
                        <?php if($karyawan_id && $karyawanData): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <!-- Info Karyawan -->
                            <div class="p-5 border-b border-gray-100 bg-gradient-to-r from-teal-500 to-emerald-600 text-white flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                                <div>
                                    <h2 class="text-xl font-bold"><?= htmlspecialchars($karyawanData['nama']) ?></h2>
                                    <p class="text-teal-50 text-sm mt-0.5"><?= htmlspecialchars($karyawanData['jabatan']) ?> • <?= htmlspecialchars($karyawanData['unit']) ?></p>
                                </div>
                                <div class="flex gap-2 flex-wrap">
                                    <button onclick="openModal('modalCopy')" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-xl text-sm font-semibold transition-colors flex items-center gap-1.5">
                                        <i data-lucide="copy" class="w-4 h-4"></i> Salin Template
                                    </button>
                                    <button onclick="openModal('modalAdd')" class="bg-white text-teal-600 hover:bg-teal-50 px-3 py-1.5 rounded-xl text-sm font-bold shadow-sm transition-colors flex items-center gap-1.5">
                                        <i data-lucide="plus" class="w-4 h-4"></i> Tambah Tugas
                                    </button>
                                </div>
                            </div>

                            <!-- Tabs -->
                            <div class="flex border-b border-gray-200 px-4 pt-2">
                                <a href="?karyawan_id=<?=$karyawan_id?>&tab=rkk&bulan=<?=$bulan_log?>&tahun=<?=$tahun_log?>"
                                   class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors <?= $tab_sel==='rkk' ? 'text-teal-600 border-teal-600' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
                                    <i data-lucide="list" class="w-4 h-4 inline mr-1"></i>Daftar Tugas RKK
                                </a>
                                <a href="?karyawan_id=<?=$karyawan_id?>&tab=log&bulan=<?=$bulan_log?>&tahun=<?=$tahun_log?>"
                                   class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors <?= $tab_sel==='log' ? 'text-teal-600 border-teal-600' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
                                    <i data-lucide="calendar-check" class="w-4 h-4 inline mr-1"></i>Log Harian
                                    <?php if(!empty($logBulanIni)): ?>
                                    <span class="ml-1 px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded text-xs"><?=count($logBulanIni)?></span>
                                    <?php endif; ?>
                                </a>
                            </div>                            <!-- Tab Content: Daftar Tugas RKK -->
                            <?php if($tab_sel === 'rkk'): ?>
                            <div class="p-5 space-y-3">
                                <?php if(empty($rkkList)): ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <i data-lucide="file-x" class="w-12 h-12 mx-auto text-gray-300 mb-3"></i>
                                        <p>Belum ada rincian tugas untuk karyawan ini.</p>
                                    </div>
                                <?php endif; ?>
                                <?php foreach($rkkList as $r): ?>
                                <div class="p-4 border border-gray-200 rounded-xl hover:border-teal-300 transition-colors group flex justify-between items-start">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="font-bold text-gray-800"><?= htmlspecialchars($r['tugas']) ?></h4>
                                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= $r['jenis']==='Pokok' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' ?>">
                                                <?= $r['jenis'] ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600"><?= nl2br(htmlspecialchars($r['deskripsi'])) ?></p>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-2 ml-3 flex-shrink-0">
                                        <button onclick='editData(<?= json_encode($r) ?>)' class="text-blue-500 hover:bg-blue-50 p-2 rounded-lg transition-colors"><i data-lucide="edit" class="w-4 h-4"></i></button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Hapus tugas ini?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="karyawan_id" value="<?= $karyawan_id ?>">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Tab Content: Log Harian -->
                            <?php else: ?>
                            <div class="p-5">
                                <!-- Filter bulan/tahun + pilih hari -->
                                <form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
                                    <input type="hidden" name="karyawan_id" value="<?=$karyawan_id?>">
                                    <input type="hidden" name="tab" value="log">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
                                        <select name="bulan" class="border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-teal-400">
                                            <?php foreach($bulanFull as $b=>$nm): ?>
                                            <option value="<?=$b?>" <?=$bulan_log==$b?'selected':''?>><?=$nm?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
                                        <select name="tahun" class="border border-gray-200 rounded-xl py-2 px-3 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-teal-400">
                                            <?php for($y=date('Y');$y>=date('Y')-2;$y--): ?>
                                            <option value="<?=$y?>" <?=$tahun_log==$y?'selected':''?>><?=$y?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-semibold hover:bg-teal-700">Tampilkan</button>
                                </form>

                                <?php if(empty($rkkList)): ?>
                                <div class="text-center py-8 text-amber-600 bg-amber-50 rounded-xl">
                                    <i data-lucide="alert-triangle" class="w-8 h-8 mx-auto mb-2"></i>
                                    <p class="text-sm font-medium">Belum ada daftar tugas RKK. Tambahkan tugas di tab <strong>Daftar Tugas RKK</strong> terlebih dahulu.</p>
                                </div>
                                <?php else: ?>

                                <!-- Grid log per hari -->
                                <div class="overflow-x-auto rounded-xl border border-gray-200">
                                    <table class="w-full text-sm border-collapse">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase sticky left-0 bg-gray-50 min-w-[180px]">Tugas</th>
                                                <th class="px-2 py-3 text-center text-xs font-bold text-gray-600 uppercase min-w-[40px]">Jenis</th>
                                                <?php for($h=1;$h<=$jumlah_hari_log;$h++):
                                                    $dw = (int)date('w',mktime(0,0,0,$bulan_log,$h,$tahun_log));
                                                    $isWe = $dw===0||$dw===6; ?>
                                                <th class="py-2 text-center min-w-[34px] <?=$isWe?'bg-red-50':''?>"
                                                    style="font-size:10px;font-weight:600;color:<?=$isWe?'#ef4444':'#64748b'?>">
                                                    <div><?=$h?></div>
                                                    <div style="font-weight:400;color:#94a3b8"><?=namaHariRKK($h,$bulan_log,$tahun_log)?></div>
                                                </th>
                                                <?php endfor; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                        <?php foreach($rkkList as $r): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 sticky left-0 bg-white font-medium text-gray-800 text-xs"><?= htmlspecialchars($r['tugas']) ?></td>
                                            <td class="px-2 py-2 text-center">
                                                <span class="px-1.5 py-0.5 rounded text-xs <?=$r['jenis']==='Pokok'?'bg-blue-100 text-blue-700':'bg-gray-100 text-gray-500'?>"><?=$r['jenis']==='Pokok'?'P':'T'?></span>
                                            </td>
                                            <?php for($h=1;$h<=$jumlah_hari_log;$h++):
                                                $dw = (int)date('w',mktime(0,0,0,$bulan_log,$h,$tahun_log));
                                                $isWe = $dw===0||$dw===6;
                                                $done = isset($logBulanIni[$h][$r['id']]); ?>
                                            <td class="py-1 text-center <?=$isWe?'bg-red-50':''?>" style="min-width:34px">
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded <?=$done?'bg-emerald-100 text-emerald-700':'text-gray-200'?>">
                                                    <?=$done?'✓':''?>
                                                </span>
                                            </td>
                                            <?php endfor; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Form isi log per hari -->
                                <div class="mt-5 p-5 bg-emerald-50 border border-emerald-200 rounded-xl">
                                    <h3 class="font-bold text-emerald-800 mb-3 flex items-center gap-2">
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                        Isi Log Hari Ini / Pilih Tanggal
                                    </h3>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="save_log">
                                        <input type="hidden" name="karyawan_id" value="<?=$karyawan_id?>">
                                        <input type="hidden" name="bulan" value="<?=$bulan_log?>">
                                        <input type="hidden" name="tahun" value="<?=$tahun_log?>">
                                        <div class="flex flex-wrap gap-4 items-end mb-4">
                                            <div>
                                                <label class="block text-xs font-semibold text-emerald-700 mb-1">Tanggal</label>
                                                <select name="hari" id="hari_pilih" class="border border-emerald-300 rounded-xl py-2 px-3 text-sm bg-white outline-none focus:ring-2 focus:ring-emerald-400" onchange="loadLogHari(this.value)">
                                                    <?php for($h=1;$h<=$jumlah_hari_log;$h++):
                                                        $dw=(int)date('w',mktime(0,0,0,$bulan_log,$h,$tahun_log));
                                                        $nm=['Min','Sen','Sel','Rab','Kam','Jum','Sab'][$dw]; ?>
                                                    <option value="<?=$h?>" <?=$h==(int)date('d')?'selected':''?>><?=$h?> - <?=$nm?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <p class="text-xs text-emerald-600">Centang tugas yang <strong>sudah dikerjakan</strong> pada tanggal tersebut.</p>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-4" id="tugas-checklist">
                                            <?php
                                            $hariDefault = (int)date('d');
                                            foreach($rkkList as $r):
                                                $checked = isset($logBulanIni[$hariDefault][$r['id']]);
                                            ?>
                                            <label class="flex items-start gap-3 p-3 bg-white border border-emerald-200 rounded-xl cursor-pointer hover:bg-emerald-50 transition-colors tugas-item" data-hari="<?=$hariDefault?>">
                                                <input type="checkbox" name="rkk_ids[]" value="<?=$r['id']?>"
                                                       class="mt-0.5 w-4 h-4 text-emerald-600 rounded tugas-cb"
                                                       data-rkk="<?=$r['id']?>"
                                                       <?=$checked?'checked':''?>>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-800"><?=htmlspecialchars($r['tugas'])?></p>
                                                    <p class="text-xs text-gray-500"><?=htmlspecialchars($r['jenis'])?></p>
                                                </div>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-xl font-semibold text-sm hover:bg-emerald-700 flex items-center gap-2">
                                                <i data-lucide="save" class="w-4 h-4"></i> Simpan Log
                                            </button>
                                            <p class="text-xs text-emerald-600">Nilai RKK di penilaian harian akan otomatis dihitung.</p>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center text-gray-500">
                            <i data-lucide="users" class="w-16 h-16 mx-auto text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-bold text-gray-700 mb-2">Pilih Karyawan</h3>
                            <p>Silakan pilih karyawan dari menu di sebelah kiri untuk melihat dan mengelola RKK / Job Description.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <?php if($karyawan_id): ?>
    <!-- Modal Tambah/Edit Tugas -->
    <div id="modalAdd" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 m-4 relative shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4" id="modalTitle">Tambah Tugas</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add" id="modalAction">
                <input type="hidden" name="karyawan_id" value="<?= $karyawan_id ?>">
                <input type="hidden" name="id" id="tugas_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Tugas / Kewenangan</label>
                        <input type="text" name="tugas" id="tugas" required class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi Detail</label>
                        <textarea name="deskripsi" id="deskripsi" rows="3" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis Tugas</label>
                        <select name="jenis" id="jenis" class="w-full border border-gray-200 py-2 px-3 rounded-xl focus:ring-teal-500 outline-none">
                            <option value="Pokok">Tugas Pokok</option>
                            <option value="Tambahan">Tugas Tambahan</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalAdd')" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-medium">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Copy Template -->
    <div id="modalCopy" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 m-4 relative shadow-xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Salin dari Template Jabatan</h2>
            <form method="POST" onsubmit="return confirm('Proses ini akan menambahkan tugas dari template ke karyawan ini. Lanjutkan?');">
                <input type="hidden" name="action" value="copy_template">
                <input type="hidden" name="karyawan_id" value="<?= $karyawan_id ?>">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih Template</label>
                        <select name="template_id" required class="w-full border border-gray-200 py-2.5 px-3 rounded-xl focus:ring-teal-500 outline-none bg-gray-50">
                            <option value="">-- Pilih Template --</option>
                            <?php foreach($templateList as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['jabatan']) ?> (<?= htmlspecialchars($t['unit']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('modalCopy')" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium">Salin Tugas</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        lucide.createIcons();

        // Data log per hari dari PHP (untuk update checklist tanpa reload)
        const logData = <?= json_encode($logBulanIni) ?>;
        const rkkIds  = <?= json_encode(array_column($rkkList, 'id')) ?>;

        function loadLogHari(hari) {
            const cbs = document.querySelectorAll('.tugas-cb');
            cbs.forEach(cb => {
                const rkkId = parseInt(cb.dataset.rkk);
                cb.checked = !!(logData[hari] && logData[hari][rkkId]);
            });
        }

        function openModal(id) {
            if(id === 'modalAdd'){
                document.getElementById('modalAction').value = 'add';
                document.getElementById('modalTitle').textContent = 'Tambah Tugas';
                document.getElementById('tugas_id').value = '';
                document.getElementById('tugas').value = '';
                document.getElementById('deskripsi').value = '';
                document.getElementById('jenis').value = 'Pokok';
            }
            document.getElementById(id).classList.remove('hidden');
            document.getElementById(id).classList.add('flex');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }
        function editData(data) {
            document.getElementById('modalAction').value = 'edit';
            document.getElementById('modalTitle').textContent = 'Edit Tugas';
            document.getElementById('tugas_id').value = data.id;
            document.getElementById('tugas').value = data.tugas;
            document.getElementById('deskripsi').value = data.deskripsi;
            document.getElementById('jenis').value = data.jenis;
            document.getElementById('modalAdd').classList.remove('hidden');
            document.getElementById('modalAdd').classList.add('flex');
        }
    </script>
</body>
</html>
