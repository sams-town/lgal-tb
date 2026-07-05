<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

function renderStepIndicator($stepId, $stepStatus) {
    $status = $stepStatus[$stepId] ?? 'pending';
    $icon = '';
    $colorClass = '';
    
    if ($status === 'approved') {
        $icon = '✅';
        $colorClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
    } elseif ($status === 'rejected') {
        $icon = '❌';
        $colorClass = 'bg-red-100 text-red-800 border-red-200';
    } else {
        $icon = '⏳';
        $colorClass = 'bg-amber-100 text-amber-800 border-amber-200';
    }

    return "<span class=\"inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold border $colorClass\">$icon " . strtoupper($stepId) . "</span>";
}

// Handle form submission for adding new Pengajuan Dokumen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pengajuan'])) {
    $judulDokumen = $_POST['judul_dokumen'] ?? null;
    $jenisPengajuan = $_POST['jenis_pengajuan'] ?? null;
    $jenisRegulasi = $_POST['jenis_regulasi'] ?? null;
    $kategoriAkreditasi = $_POST['kategori_akreditasi'] ?? null;
    $unitPengusul = $_POST['unit_pengusul'] ?? null;
    $pengusul = $_POST['pengusul'] ?? null;
    $jenisDokumen = $_POST['jenis_dokumen'] ?? null;
    $ruangLingkup = $_POST['ruang_lingkup'] ?? null;
    $tujuanRegulasi = $_POST['tujuan_regulasi'] ?? null;
    $dasarHukum = $_POST['dasar_hukum'] ?? null;
    $tanggal = $_POST['tanggal'] ?? null;
    $alasanPerubahan = $_POST['alasan_perubahan'] ?? null;
    $alasanPencabutan = $_POST['alasan_pencabutan'] ?? null;

    // Initialize step status
    $stepStatus = [
        'km' => 'pending',
        'legal' => 'pending',
        'sekretariat' => 'pending',
        'dk' => 'pending',
        'dsdml' => 'pending',
        'du' => 'pending'
    ];

    // For pencabutan, we skip some steps
    if ($jenisPengajuan === 'Pencabutan Dokumen') {
        $stepStatus = [
            'km' => 'pending',
            'dk' => 'pending',
            'dsdml' => 'pending',
            'du' => 'pending'
        ];
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO pengajuan_dokumen (
                judul_dokumen, jenis_pengajuan, jenis_regulasi, kategori_akreditasi, 
                unit_pengusul, pengusul, jenis_dokumen, tanggal, 
                ruang_lingkup, tujuan_regulasi, dasar_hukum, 
                alasan_perubahan, alasan_pencabutan, step_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $judulDokumen,
            $jenisPengajuan,
            $jenisRegulasi,
            $kategoriAkreditasi,
            $unitPengusul,
            $pengusul,
            $jenisDokumen,
            $tanggal,
            $ruangLingkup,
            $tujuanRegulasi,
            $dasarHukum,
            $alasanPerubahan,
            $alasanPencabutan,
            json_encode($stepStatus)
        ]);
        
        // Send notification to Komite Mutu
        notifyByPermission(
            "Verifikasi Regulasi",
            "Ada pengajuan regulasi baru ($judulDokumen) dari unit $unitPengusul yang perlu diverifikasi.",
            "legal"
        );
        
        $_SESSION['pks_success'] = "Pengajuan dokumen berhasil dikirim!";
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menyimpan data: " . $e->getMessage();
    }
    header("Location: pengajuan.php");
    exit;
}

// Get Pengajuan documents
try {
    $stmt = $pdo->query("SELECT * FROM pengajuan_dokumen ORDER BY created_at DESC");
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Calculate stats for Pengajuan
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengajuan_dokumen");
    $totalDocs = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_dokumen WHERE jenis_pengajuan = ?");
    $stmt->execute(['Pengajuan Baru']);
    $baru = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_dokumen WHERE jenis_pengajuan = ?");
    $stmt->execute(['Perubahan Dokumen']);
    $perubahan = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_dokumen WHERE jenis_pengajuan = ?");
    $stmt->execute(['Pencabutan Dokumen']);
    $pencabutan = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalDocs = 0;
    $baru = 0;
    $perubahan = 0;
    $pencabutan = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Dokumen - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <?php include 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Pengajuan Dokumen</h1>
                    <p class="text-gray-600 mt-2">Pengelolaan pengajuan, perubahan, dan pencabutan dokumen regulasi internal</p>
                </div>

                <?php if (isset($_SESSION['pks_success'])): ?>
                    <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl">
                        <?php echo htmlspecialchars($_SESSION['pks_success']); ?>
                    </div>
                    <?php unset($_SESSION['pks_success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['pks_error'])): ?>
                    <div class="p-4 bg-red-100 text-red-800 rounded-xl">
                        <?php echo htmlspecialchars($_SESSION['pks_error']); ?>
                    </div>
                    <?php unset($_SESSION['pks_error']); ?>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Pengajuan</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Pengajuan Baru</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $baru; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">🆕</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Perubahan</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $perubahan; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">🔄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Pencabutan</p>
                                <h3 class="text-3xl font-bold text-red-600"><?php echo $pencabutan; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-3xl">🚫</div>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Judul Dokumen</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Jenis Pengajuan</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Unit Pengusul</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal Pengajuan</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status Alur</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada pengajuan dokumen yang tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $index => $doc): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['judul_dokumen'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php 
                                                $jenis_pengajuan = $doc['jenis_pengajuan'] ?? '-';
                                                ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $jenis_pengajuan === 'Pengajuan Baru' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : ($jenis_pengajuan === 'Perubahan Dokumen' ? 'bg-blue-100 text-blue-800 border-blue-200' : 'bg-red-100 text-red-800 border-red-200'); ?>">
                                                    <?php echo htmlspecialchars($jenis_pengajuan); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo htmlspecialchars($doc['unit_pengusul'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo formatDate($doc['tanggal'] ?? null); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="text-gray-400 text-sm">-</span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-wrap gap-1">
                                                    <?php 
                                                    $stepStatus = json_decode($doc['step_status'] ?? '[]', true);
                                                    if ($jenis_pengajuan === 'Pencabutan Dokumen'):
                                                        echo renderStepIndicator('km', $stepStatus);
                                                        echo renderStepIndicator('dk', $stepStatus);
                                                        echo renderStepIndicator('dsdml', $stepStatus);
                                                        echo renderStepIndicator('du', $stepStatus);
                                                    else:
                                                        echo renderStepIndicator('km', $stepStatus);
                                                        echo renderStepIndicator('legal', $stepStatus);
                                                        echo renderStepIndicator('sekretariat', $stepStatus);
                                                        echo renderStepIndicator('dk', $stepStatus);
                                                        echo renderStepIndicator('dsdml', $stepStatus);
                                                        echo renderStepIndicator('du', $stepStatus);
                                                    endif;
                                                    ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal for Pengajuan Dokumen -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Pengajuan Baru/Perubahan/Pencabutan</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Judul Dokumen <span class="text-red-500">*</span></label>
                    <input type="text" name="judul_dokumen" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan judul dokumen">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Pengajuan <span class="text-red-500">*</span></label>
                    <select name="jenis_pengajuan" id="jenis_pengajuan" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" onchange="toggleAlasanFields()">
                        <option value="Pengajuan Baru">Dokumen Baru</option>
                        <option value="Perubahan Dokumen">Perubahan Dokumen</option>
                        <option value="Pencabutan Dokumen">Pencabutan Dokumen</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Regulasi <span class="text-red-500">*</span></label>
                    <select name="jenis_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="Peraturan Direktur">Peraturan Direktur</option>
                        <option value="SPO">SPO</option>
                        <option value="Kebijakan Mutu">Kebijakan Mutu</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Akreditasi <span class="text-red-500">*</span></label>
                    <select name="kategori_akreditasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="Tata Kelola Rumah Sakit">Tata Kelola Rumah Sakit</option>
                        <option value="Pelayanan Medis">Pelayanan Medis</option>
                        <option value="Keperawatan">Keperawatan</option>
                        <option value="Manajemen Klinis">Manajemen Klinis</option>
                        <option value="Manajemen Sarana dan Prasarana">Manajemen Sarana dan Prasarana</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Unit Pengusul <span class="text-red-500">*</span></label>
                    <input type="text" name="unit_pengusul" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan unit pengusul">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Pengusul <span class="text-red-500">*</span></label>
                    <input type="text" name="pengusul" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nama pengusul">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Dokumen <span class="text-red-500">*</span></label>
                    <input type="text" name="jenis_dokumen" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan jenis dokumen">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ruang Lingkup <span class="text-red-500">*</span></label>
                    <textarea name="ruang_lingkup" required rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Deskripsikan ruang lingkup"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tujuan Regulasi <span class="text-red-500">*</span></label>
                    <textarea name="tujuan_regulasi" required rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Deskripsikan tujuan regulasi"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dasar Hukum/Referensi</label>
                    <textarea name="dasar_hukum" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan dasar hukum atau referensi (opsional)"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Pengajuan <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <div id="alasanPerubahanField" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Perubahan</label>
                    <textarea name="alasan_perubahan" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Jelaskan alasan perubahan dokumen"></textarea>
                </div>

                <div id="alasanPencabutanField" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Pencabutan</label>
                    <textarea name="alasan_pencabutan" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Jelaskan alasan pencabutan dokumen"></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-300 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_pengajuan" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Submit Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>
<script>
    function toggleAlasanFields() {
        const jenis = document.getElementById('jenis_pengajuan').value;
        const perubahanField = document.getElementById('alasanPerubahanField');
        const pencabutanField = document.getElementById('alasanPencabutanField');
        
        perubahanField.style.display = jenis === 'Perubahan Dokumen' ? 'block' : 'none';
        pencabutanField.style.display = jenis === 'Pencabutan Dokumen' ? 'block' : 'none';
    }
</script>
</body>
</html>
