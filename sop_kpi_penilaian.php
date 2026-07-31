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

$user = $_SESSION['user'];

// --- Backend Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $karyawan_id = $_POST['karyawan_id'];
    $bulan = $_POST['bulan'];
    $tahun = $_POST['tahun'];
    $catatan = $_POST['catatan'];
    $nilai_arr = $_POST['nilai'] ?? []; // Array [kriteria_id => nilai]
    
    try {
        $pdo->beginTransaction();
        
        // Cek apakah sudah ada penilaian di bulan & tahun ini
        $stmtCek = $pdo->prepare("SELECT id FROM kpi_penilaian WHERE karyawan_id = ? AND bulan = ? AND tahun = ?");
        $stmtCek->execute([$karyawan_id, $bulan, $tahun]);
        $existing = $stmtCek->fetch();
        
        $total_skor = 0;
        // Hitung total_skor berdasarkan input dan bobot (opsional, asumsikan rata-rata tertimbang atau sekadar jumlah dari perhitungan)
        // Disini kita asumsikan bobot dari DB, tapi untuk kemudahan kita ambil rata-rata berbobot
        $stmtKriteria = $pdo->query("SELECT id, bobot FROM kpi_kriteria");
        $kriteriaList = $stmtKriteria->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $total_bobot = 0;
        foreach($nilai_arr as $kid => $val) {
            $bobot = $kriteriaList[$kid] ?? 10;
            $total_skor += ($val * ($bobot / 100)); // perhitungan sederhana
        }

        if ($existing) {
            $penilaian_id = $existing['id'];
            $stmtUpdate = $pdo->prepare("UPDATE kpi_penilaian SET total_skor = ?, catatan = ? WHERE id = ?");
            $stmtUpdate->execute([$total_skor, $catatan, $penilaian_id]);
            
            // Delete old details
            $pdo->prepare("DELETE FROM kpi_penilaian_detail WHERE penilaian_id = ?")->execute([$penilaian_id]);
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO kpi_penilaian (karyawan_id, bulan, tahun, total_skor, catatan, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([$karyawan_id, $bulan, $tahun, $total_skor, $catatan, $user['nama'] ?? 'Admin']);
            $penilaian_id = $pdo->lastInsertId();
        }

        $stmtDetail = $pdo->prepare("INSERT INTO kpi_penilaian_detail (penilaian_id, kriteria_id, nilai) VALUES (?, ?, ?)");
        foreach($nilai_arr as $kid => $val) {
            $stmtDetail->execute([$penilaian_id, $kid, $val]);
        }

        $pdo->commit();
        header("Location: sop_kpi_penilaian.php?success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Fetch Karyawan
$stmtK = $pdo->query("SELECT id, nama, jabatan, unit FROM kpi_karyawan WHERE status='Aktif' ORDER BY nama ASC");
$karyawanList = $stmtK->fetchAll();

// Fetch Kriteria
$stmtKr = $pdo->query("SELECT * FROM kpi_kriteria ORDER BY id ASC");
$kriteriaList = $stmtKr->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Penilaian KPI - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 flex flex-col relative h-screen overflow-hidden">
        <?php include 'includes/header.php'; ?>
        
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-4xl mx-auto space-y-6">
                <!-- Header -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 text-center md:text-left">
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Form Input Penilaian</h1>
                    <p class="text-gray-500 mt-1">Evaluasi performa karyawan berdasarkan indikator yang ditetapkan</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span>Penilaian berhasil disimpan!</span>
                </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <!-- Form Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                            <i data-lucide="clipboard-edit" class="w-5 h-5 text-teal-600"></i> Detail Penilaian
                        </h3>
                    </div>
                    <form method="POST" class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Karyawan Select -->
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-gray-700">Pilih Karyawan</label>
                                <select name="karyawan_id" required class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                    <option value="">Pilih...</option>
                                    <?php foreach($karyawanList as $k): ?>
                                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama'] . ' - ' . $k['jabatan'] . ' (' . $k['unit'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Periode -->
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-gray-700">Periode Penilaian</label>
                                <div class="flex gap-2">
                                    <select name="bulan" required class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                        <?php 
                                        $months = ['01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'];
                                        $curB = date('m');
                                        foreach($months as $k => $v) {
                                            $sel = ($curB == $k) ? 'selected' : '';
                                            echo "<option value='$k' $sel>$v</option>";
                                        }
                                        ?>
                                    </select>
                                    <select name="tahun" required class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50">
                                        <?php 
                                        $curY = date('Y');
                                        for($i = $curY; $i >= $curY - 2; $i--) {
                                            echo "<option value='$i'>$i</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        <!-- Kriteria Penilaian -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-800">Indikator Penilaian</h4>
                            
                            <?php if (empty($kriteriaList)): ?>
                                <p class="text-sm text-gray-500 italic">Belum ada data kriteria penilaian. Silakan atur di menu Kriteria Penilaian.</p>
                            <?php endif; ?>

                            <?php foreach($kriteriaList as $index => $kr): ?>
                            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="text-sm font-medium text-gray-700"><?= ($index+1).'. '.htmlspecialchars($kr['nama_indikator']).' (Bobot '.$kr['bobot'].'%)' ?></label>
                                    <span class="text-xs font-bold bg-teal-100 text-teal-700 px-2 py-1 rounded">Skor: 0 - 100</span>
                                </div>
                                <input type="number" name="nilai[<?= $kr['id'] ?>]" min="0" max="100" required placeholder="Masukkan nilai 0-100" class="w-full md:w-1/3 border border-gray-200 text-gray-700 py-2 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-white">
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Catatan -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-700">Catatan / Feedback Evaluator</label>
                            <textarea name="catatan" rows="3" class="w-full border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none bg-gray-50" placeholder="Berikan catatan konstruktif..."></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button type="reset" class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium transition-colors">Reset</button>
                            <button type="submit" class="px-5 py-2.5 bg-teal-600 text-white rounded-xl hover:bg-teal-700 font-semibold shadow-md transition-colors flex items-center gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i> Simpan Penilaian
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
