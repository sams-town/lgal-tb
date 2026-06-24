<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Get current page
$page = isset($_GET['page']) ? $_GET['page'] : 'komite-medik';

// Define jabatan options for Komite Nakes
$jabatanOptions = [
    'Supervisor',
    'Fisioterapis',
    'Apoteker',
    'Radiografer',
    'Fisikawan Medis',
    'Asisten Apoteker',
    'Analis Laboratorium',
    'Kesehatan Lingkungan'
];

// Define jabatan options for Komite Keperawatan
$jabatanKeperawatanOptions = [
    'Supervisor',
    'Bidan',
    'Perawat'
];

// Define default unit ruangan options
$unitRuanganOptions = [
    'Rawat Inap',
    'Rawat Jalan',
    'IGD',
    'ICU',
    'Kamar Operasi',
    'Radiologi',
    'Laboratorium',
    'Apotek',
    'Kebidanan',
    'Anak',
    'Penyakit Dalam',
    'Bedah',
    'Kardiologi',
    'Neurologi'
];

// Initialize database tables
try {
    // Table for Tenaga Medis
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tenaga_medis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_lengkap VARCHAR(255) NOT NULL,
            unit_ruangan VARCHAR(100) NULL,
            status_kepegawaian VARCHAR(50) NOT NULL,
            tipe_form VARCHAR(50) NOT NULL,
            no_str VARCHAR(255) NULL,
            file_str VARCHAR(255) NULL,
            no_sip VARCHAR(255) NULL,
            masa_berlaku_sip_mulai DATE NULL,
            masa_berlaku_sip_akhir DATE NULL,
            file_sip VARCHAR(255) NULL,
            no_pks VARCHAR(255) NULL,
            masa_berlaku_pks_mulai DATE NULL,
            masa_berlaku_pks_akhir DATE NULL,
            file_pks VARCHAR(255) NULL,
            no_sk VARCHAR(255) NULL,
            masa_berlaku_sk_mulai DATE NULL,
            masa_berlaku_sk_akhir DATE NULL,
            file_sk VARCHAR(255) NULL,
            kompetensi_klinis TEXT NULL,
            sertifikasi_kompetensi JSON NULL,
            jabatan_keperawatan VARCHAR(100) NULL,
            spesialis VARCHAR(255) NULL,
            nomor_pkwt VARCHAR(255) NULL,
            rincian_kewenangan_klinis TEXT NULL,
            lantai VARCHAR(10) NULL,
            nomor_keputusan_direktur VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_unit_ruangan (unit_ruangan),
            INDEX idx_tipe_form (tipe_form),
            INDEX idx_nama_lengkap (nama_lengkap)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Add new columns if they don't exist
    try {
        $pdo->exec("ALTER TABLE tenaga_medis ADD COLUMN jabatan_keperawatan VARCHAR(100) NULL AFTER sertifikasi_kompetensi");
    } catch (PDOException $e) { /* Ignore */ }
    try {
        $pdo->exec("ALTER TABLE tenaga_medis ADD COLUMN spesialis VARCHAR(255) NULL AFTER jabatan_keperawatan");
    } catch (PDOException $e) { /* Ignore */ }
    try {
        $pdo->exec("ALTER TABLE tenaga_medis ADD COLUMN nomor_pkwt VARCHAR(255) NULL AFTER spesialis");
    } catch (PDOException $e) { /* Ignore */ }
    try {
        $pdo->exec("ALTER TABLE tenaga_medis ADD COLUMN rincian_kewenangan_klinis TEXT NULL AFTER nomor_pkwt");
    } catch (PDOException $e) { /* Ignore */ }
    try {
        $pdo->exec("ALTER TABLE tenaga_medis MODIFY unit_ruangan VARCHAR(100) NULL");
    } catch (PDOException $e) { /* Ignore */ }
    try {
        $pdo->exec("ALTER TABLE tenaga_medis ADD COLUMN lantai VARCHAR(10) NULL AFTER rincian_kewenangan_klinis");
    } catch (PDOException $e) { /* Ignore */ }
    try {
        $pdo->exec("ALTER TABLE tenaga_medis ADD COLUMN nomor_keputusan_direktur VARCHAR(255) NULL AFTER lantai");
    } catch (PDOException $e) { /* Ignore */ }

    // Insert sample data if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM tenaga_medis");
    if (false && $stmt->fetchColumn() == 0) {
        $sampleData = [
            [
                'nama_lengkap' => 'Dr. Andi Wijaya, Sp.PD',
                'unit_ruangan' => 'Rawat Inap',
                'status_kepegawaian' => 'Tetap',
                'tipe_form' => 'sip-dokter',
                'no_str' => 'STR.1234567890',
                'no_sip' => 'SIP.0987654321',
                'masa_berlaku_sip_mulai' => '2024-01-01',
                'masa_berlaku_sip_akhir' => '2026-12-31',
                'no_pks' => 'PKS.111222333',
                'masa_berlaku_pks_mulai' => '2024-01-01',
                'masa_berlaku_pks_akhir' => '2025-12-31',
                'no_sk' => 'SK.444555666',
                'masa_berlaku_sk_mulai' => '2024-01-01',
                'masa_berlaku_sk_akhir' => '2026-12-31',
                'kompetensi_klinis' => 'Penyakit Dalam, Kardiologi',
                'sertifikasi_kompetensi' => json_encode(['ACLS', 'PALS', 'ECMO'])
            ],
            [
                'nama_lengkap' => 'Nurse Siti Nurhaliza',
                'unit_ruangan' => 'IGD',
                'status_kepegawaian' => 'Kontrak',
                'tipe_form' => 'str-nakes',
                'no_str' => 'STR.9876543210',
                'no_sip' => 'SIP.1234567890',
                'masa_berlaku_sip_mulai' => '2024-03-01',
                'masa_berlaku_sip_akhir' => '2026-02-28',
                'kompetensi_klinis' => 'Keperawatan Gawat Darurat',
                'sertifikasi_kompetensi' => json_encode(['BTLS', 'ATLS'])
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO tenaga_medis (
                nama_lengkap, unit_ruangan, status_kepegawaian, tipe_form,
                no_str, file_str,
                no_sip, masa_berlaku_sip_mulai, masa_berlaku_sip_akhir, file_sip,
                no_pks, masa_berlaku_pks_mulai, masa_berlaku_pks_akhir, file_pks,
                no_sk, masa_berlaku_sk_mulai, masa_berlaku_sk_akhir, file_sk,
                kompetensi_klinis, sertifikasi_kompetensi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($sampleData as $data) {
            $stmt->execute([
                $data['nama_lengkap'],
                $data['unit_ruangan'],
                $data['status_kepegawaian'],
                $data['tipe_form'],
                $data['no_str'] ?? null,
                $data['file_str'] ?? null,
                $data['no_sip'] ?? null,
                $data['masa_berlaku_sip_mulai'] ?? null,
                $data['masa_berlaku_sip_akhir'] ?? null,
                $data['file_sip'] ?? null,
                $data['no_pks'] ?? null,
                $data['masa_berlaku_pks_mulai'] ?? null,
                $data['masa_berlaku_pks_akhir'] ?? null,
                $data['file_pks'] ?? null,
                $data['no_sk'] ?? null,
                $data['masa_berlaku_sk_mulai'] ?? null,
                $data['masa_berlaku_sk_akhir'] ?? null,
                $data['file_sk'] ?? null,
                $data['kompetensi_klinis'] ?? null,
                $data['sertifikasi_kompetensi'] ?? null
            ]);
        }
    }
} catch (PDOException $e) {
    // Continue if sample data fails to insert
}

// Handle file upload helper
function handleFileUpload($fileInputName, $uploadDir) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = uniqid() . '_' . basename($_FILES[$fileInputName]['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetFile)) {
            return $targetFile;
        }
    }
    return null;
}

// Handle form submission for SIP Dokter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_sip_dokter'])) {
    $uploadDir = 'uploads/medis/';
    $fileStr = handleFileUpload('file_str', $uploadDir);
    $fileSip = handleFileUpload('file_sip', $uploadDir);
    $filePks = handleFileUpload('file_pks', $uploadDir);
    $fileSk = handleFileUpload('file_sk', $uploadDir);

    $sertifikasi = isset($_POST['sertifikasi']) ? json_encode($_POST['sertifikasi']) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenaga_medis (
                nama_lengkap, unit_ruangan, status_kepegawaian, tipe_form,
                no_str, file_str,
                no_sip, masa_berlaku_sip_mulai, masa_berlaku_sip_akhir, file_sip,
                no_pks, masa_berlaku_pks_mulai, masa_berlaku_pks_akhir, file_pks,
                no_sk, masa_berlaku_sk_mulai, masa_berlaku_sk_akhir, file_sk,
                kompetensi_klinis, sertifikasi_kompetensi, jabatan_keperawatan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['nama_lengkap'],
            $_POST['unit_ruangan'],
            $_POST['status_kepegawaian'],
            'sip-dokter',
            $_POST['no_str'] ?? null,
            $fileStr,
            $_POST['no_sip'] ?? null,
            $_POST['masa_berlaku_sip_mulai'] ?? null,
            $_POST['masa_berlaku_sip_akhir'] ?? null,
            $fileSip,
            $_POST['no_pks'] ?? null,
            $_POST['masa_berlaku_pks_mulai'] ?? null,
            $_POST['masa_berlaku_pks_akhir'] ?? null,
            $filePks,
            $_POST['no_sk'] ?? null,
            $_POST['masa_berlaku_sk_mulai'] ?? null,
            $_POST['masa_berlaku_sk_akhir'] ?? null,
            $fileSk,
            $_POST['kompetensi_klinis'] ?? null,
            $sertifikasi,
            $_POST['jabatan_keperawatan'] ?? null
        ]);
        $success = "Data SIP Dokter berhasil ditambahkan";
    } catch (PDOException $e) {
        $error = "Gagal menambahkan data: " . $e->getMessage();
    }
}

// Handle form submission for STR Nakes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_str_nakes'])) {
    $uploadDir = 'uploads/medis/';
    $fileStr = handleFileUpload('file_str', $uploadDir);
    $fileSip = handleFileUpload('file_sip', $uploadDir);
    $filePks = handleFileUpload('file_pks', $uploadDir);
    $fileSk = handleFileUpload('file_sk', $uploadDir);

    $sertifikasi = isset($_POST['sertifikasi']) ? json_encode($_POST['sertifikasi']) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenaga_medis (
                nama_lengkap, unit_ruangan, status_kepegawaian, tipe_form,
                no_str, file_str,
                no_sip, masa_berlaku_sip_mulai, masa_berlaku_sip_akhir, file_sip,
                no_pks, masa_berlaku_pks_mulai, masa_berlaku_pks_akhir, file_pks,
                no_sk, masa_berlaku_sk_mulai, masa_berlaku_sk_akhir, file_sk,
                kompetensi_klinis, sertifikasi_kompetensi, jabatan_keperawatan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['nama_lengkap'],
            $_POST['unit_ruangan'],
            $_POST['status_kepegawaian'],
            'str-nakes',
            $_POST['no_str'] ?? null,
            $fileStr,
            $_POST['no_sip'] ?? null,
            $_POST['masa_berlaku_sip_mulai'] ?? null,
            $_POST['masa_berlaku_sip_akhir'] ?? null,
            $fileSip,
            $_POST['no_pks'] ?? null,
            $_POST['masa_berlaku_pks_mulai'] ?? null,
            $_POST['masa_berlaku_pks_akhir'] ?? null,
            $filePks,
            $_POST['no_sk'] ?? null,
            $_POST['masa_berlaku_sk_mulai'] ?? null,
            $_POST['masa_berlaku_sk_akhir'] ?? null,
            $fileSk,
            $_POST['kompetensi_klinis'] ?? null,
            $sertifikasi,
            $_POST['jabatan_keperawatan'] ?? null
        ]);
        $success = "Data STR Nakes berhasil ditambahkan";
    } catch (PDOException $e) {
        $error = "Gagal menambahkan data: " . $e->getMessage();
    }
}

// Handle form submission for Tambah Tenaga Medis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_tenaga_medis'])) {
    $uploadDir = 'uploads/medis/';
    $fileStr = handleFileUpload('file_str', $uploadDir);
    $fileSip = handleFileUpload('file_sip', $uploadDir);
    $filePks = handleFileUpload('file_pks', $uploadDir);
    $fileSk = handleFileUpload('file_sk', $uploadDir);

    $sertifikasi = isset($_POST['sertifikasi']) ? json_encode($_POST['sertifikasi']) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenaga_medis (
                nama_lengkap, unit_ruangan, status_kepegawaian, tipe_form,
                no_str, file_str,
                no_sip, masa_berlaku_sip_mulai, masa_berlaku_sip_akhir, file_sip,
                no_pks, masa_berlaku_pks_mulai, masa_berlaku_pks_akhir, file_pks,
                no_sk, masa_berlaku_sk_mulai, masa_berlaku_sk_akhir, file_sk,
                kompetensi_klinis, sertifikasi_kompetensi, jabatan_keperawatan,
                spesialis, nomor_pkwt, rincian_kewenangan_klinis,
                lantai, nomor_keputusan_direktur
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['nama_lengkap'],
            $_POST['unit_ruangan'] ?? null,
            $_POST['status_kepegawaian'],
            $page, // Use current page as tipe_form
            $_POST['no_str'] ?? null,
            $fileStr,
            $_POST['no_sip'] ?? null,
            $_POST['masa_berlaku_sip_mulai'] ?? null,
            $_POST['masa_berlaku_sip_akhir'] ?? null,
            $fileSip,
            $_POST['no_pks'] ?? null,
            $_POST['masa_berlaku_pks_mulai'] ?? null,
            $_POST['masa_berlaku_pks_akhir'] ?? null,
            $filePks,
            $_POST['no_sk'] ?? null,
            $_POST['masa_berlaku_sk_mulai'] ?? null,
            $_POST['masa_berlaku_sk_akhir'] ?? null,
            $fileSk,
            $_POST['kompetensi_klinis'] ?? null,
            $sertifikasi,
            $_POST['jabatan_keperawatan'] ?? null,
            $_POST['spesialis'] ?? null,
            $_POST['nomor_pkwt'] ?? null,
            $_POST['rincian_kewenangan_klinis'] ?? null,
            $_POST['lantai'] ?? null,
            $_POST['nomor_keputusan_direktur'] ?? null
        ]);
        $success = "Data Tenaga Medis berhasil ditambahkan";
    } catch (PDOException $e) {
        $error = "Gagal menambahkan data: " . $e->getMessage();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT file_str, file_sip, file_pks, file_sk FROM tenaga_medis WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM tenaga_medis WHERE id = ?");
        $stmt->execute([$id]);

        $files = [$data['file_str'], $data['file_sip'], $data['file_pks'], $data['file_sk']];
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                unlink($file);
            }
        }
        $success = "Data berhasil dihapus";
    } catch (PDOException $e) {
        $error = "Gagal menghapus data: " . $e->getMessage();
    }
}

// Get data for current page
try {
    // Filter by tipe_form for all pages except 'all' (if we ever add that)
    $allowedPages = ['sip-dokter', 'str-nakes', 'tambah-tenaga-medis', 'komite-medik', 'komite-keperawatan', 'komite-nakes', 'komite-tenaga-kesehatan-lainnya'];
    $where = in_array($page, $allowedPages) ? "WHERE tipe_form = ?" : "";
    $params = in_array($page, $allowedPages) ? [$page] : [];
    
    $stmt = $pdo->prepare("SELECT * FROM tenaga_medis $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $dataMedis = $stmt->fetchAll();
} catch (PDOException $e) {
    $dataMedis = [];
}

// Calculate stats
$totalData = count($dataMedis);
$today = new DateTime();
$aktifCount = 0;
$h30Count = 0;
$expiredCount = 0;

foreach ($dataMedis as $data) {
    $checkDate = $data['masa_berlaku_sip_akhir'] ?? $data['masa_berlaku_sk_akhir'] ?? null;
    if ($checkDate) {
        $tanggal = new DateTime($checkDate);
        $selisih = $today->diff($tanggal)->days;
        
        if ($tanggal < $today) {
            $expiredCount++;
        } elseif ($selisih <= 30) {
            $h30Count++;
        } else {
            $aktifCount++;
        }
    }
}

// Page config
$pageConfigs = [
    'komite-medik' => [
        'title' => 'Komite Medik',
        'description' => 'Manajemen komite medik (untuk dokter)'
    ],
    'komite-keperawatan' => [
        'title' => 'Komite Keperawatan',
        'description' => 'Manajemen komite keperawatan (untuk perawat)'
    ],
    'komite-nakes' => [
        'title' => 'Komite Nakes',
        'description' => 'Manajemen komite nakes'
    ],
    'komite-tenaga-kesehatan-lainnya' => [
        'title' => 'Komite Tenaga Kesehatan Lainnya',
        'description' => 'Manajemen komite tenaga kesehatan lainnya'
    ],
    // Keep old ones just in case (but user said don't change form)
    'sip-dokter' => [
        'title' => 'SIP Dokter',
        'description' => 'Input dan manajemen Surat Izin Praktik Dokter'
    ],
    'str-nakes' => [
        'title' => 'STR Nakes',
        'description' => 'Registrasi dan manajemen Surat Tanda Registrasi Tenaga Kesehatan'
    ],
    'kredensial' => [
        'title' => 'Kredensial',
        'description' => 'Manajemen kredensial tenaga medis'
    ],
    're-kredensial' => [
        'title' => 'Re-Kredensial',
        'description' => 'Proses re-kredensial tenaga medis'
    ],
    'monitoring' => [
        'title' => 'Monitoring SIP/STR',
        'description' => 'Monitoring masa aktif dan expiring SIP/STR'
    ],
    'komite-tenaga-kesehatan' => [
        'title' => 'Komite Tenaga Kesehatan',
        'description' => 'Manajemen komite tenaga kesehatan'
    ],
    'jadwal-komite-medik' => [
        'title' => 'Jadwal Komite Medik',
        'description' => 'Jadwal rapat komite medik'
    ],
    'sertifikasi-kompetensi' => [
        'title' => 'Sertifikasi Kompetensi',
        'description' => 'Manajemen sertifikasi kompetensi'
    ],
    'tambah-tenaga-medis' => [
        'title' => 'Tambah Tenaga Medis',
        'description' => 'Form tambah tenaga medis baru'
    ]
];

$currentConfig = $pageConfigs[$page] ?? ['title' => 'Tenaga Medis', 'description' => 'Manajemen tenaga medis'];

// Helper functions
function formatDate($date) {
    if (!$date) return '-';
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $months[$d->format('n')] . ' ' . $d->format('Y');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentConfig['title']; ?> - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <?php require 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <!-- Header -->
        <?php require 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <!-- Submenu Navigation -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-2">
                    <nav class="flex flex-wrap gap-1">
                        <a href="tenaga_medis.php?page=komite-medik" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $page === 'komite-medik' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
                            Komite Medik
                        </a>
                        <a href="tenaga_medis.php?page=komite-keperawatan" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $page === 'komite-keperawatan' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
                            Komite Keperawatan
                        </a>
                        <a href="tenaga_medis.php?page=komite-nakes" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $page === 'komite-nakes' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
                            Komite Nakes
                        </a>
                        <a href="tenaga_medis.php?page=komite-tenaga-kesehatan-lainnya" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $page === 'komite-tenaga-kesehatan-lainnya' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
                            Komite Tenaga Kesehatan Lainnya
                        </a>
                    </nav>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900"><?php echo $currentConfig['title']; ?></h1>
                        <p class="text-gray-600 mt-2"><?php echo $currentConfig['description']; ?></p>
                    </div>
                    <div class="flex gap-3">
                        <?php if ($page === 'sip-dokter'): ?>
                            <button onclick="openModal('sipDokterModal')" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                                <span>+</span>
                                <span>Input SIP Dokter</span>
                            </button>
                        <?php elseif ($page === 'str-nakes'): ?>
                            <button onclick="openModal('strNakesModal')" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                                <span>+</span>
                                <span>Registrasi STR Nakes</span>
                            </button>
                        <?php elseif (in_array($page, ['komite-medik', 'komite-keperawatan', 'komite-nakes', 'komite-tenaga-kesehatan-lainnya', 'tambah-tenaga-medis'])): ?>
                            <button onclick="openModal('tambahTenagaMedisModal')" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                                <span>+</span>
                                <span>Tambah Dokumen</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="p-4 bg-red-100 text-red-800 rounded-xl">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['import_success'])): ?>
                    <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl">
                        <?php echo htmlspecialchars($_SESSION['import_success']); ?>
                    </div>
                    <?php unset($_SESSION['import_success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['import_error'])): ?>
                    <div class="p-4 bg-red-100 text-red-800 rounded-xl">
                        <?php echo htmlspecialchars($_SESSION['import_error']); ?>
                    </div>
                    <?php unset($_SESSION['import_error']); ?>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Data</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalData; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Aktif</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $aktifCount; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">✅</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Expired H-30</p>
                                <h3 class="text-3xl font-bold text-amber-600"><?php echo $h30Count; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">⚠️</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Expired</p>
                                <h3 class="text-3xl font-bold text-red-600"><?php echo $expiredCount; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-3xl">🚨</div>
                        </div>
                    </div>
                </div>

                <!-- Export/Import Buttons -->
                <?php if (in_array($page, ['sip-dokter', 'str-nakes', 'tambah-tenaga-medis', 'komite-medik', 'komite-keperawatan', 'komite-nakes', 'komite-tenaga-kesehatan-lainnya'])): ?>
                <div class="flex items-center gap-3 mb-4">
                    <a href="export_handler.php?action=export_data&module=tenaga_medis&subpage=<?php echo $page; ?>" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        📊
                        <span>Export Excel</span>
                    </a>
                    <button onclick="openImportModal()" class="flex items-center gap-2 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        📤
                        <span>Import Excel</span>
                    </button>
                    <a href="export_handler.php?action=download_template&module=tenaga_medis" class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                        📄
                        <span>Download Template</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nama Lengkap</th>
                                <?php if ($page === 'komite-medik'): ?>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Spesialis</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Rincian Kewenangan Klinis</th>
                        <?php else: ?>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b"><?php echo $page === 'komite-nakes' || $page === 'komite-tenaga-kesehatan-lainnya' ? 'Jabatan' : 'Unit/Ruangan'; ?></th>
                        <?php if ($page === 'komite-keperawatan'): ?>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Lantai</th>
                        <?php endif; ?>
                        <?php if ($page === 'komite-keperawatan' || $page === 'komite-nakes' || $page === 'komite-tenaga-kesehatan-lainnya'): ?>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b"><?php echo $page === 'komite-keperawatan' ? 'Jabatan' : ''; ?></th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Rincian Kewenangan Klinis</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nomor Keputusan Direktur</th>
                        <?php endif; ?>
                        <?php endif; ?>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status Kepegawaian</th>
                        <?php if ($page === 'komite-medik' || $page === 'komite-keperawatan' || $page === 'komite-nakes' || $page === 'komite-tenaga-kesehatan-lainnya'): ?>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nomor PKWT</th>
                                <?php endif; ?>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No. SIP/STR</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Masa Berlaku</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($dataMedis)): ?>
                                    <tr>
                                        <td colspan="12" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada data yang tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dataMedis as $index => $data): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($data['nama_lengkap']); ?></p>
                                            </td>
                                            <?php if ($page === 'komite-medik'): ?>
                        <td class="px-6 py-4 text-gray-700">
                            <p class="text-sm"><?php echo htmlspecialchars($data['spesialis'] ?? '-'); ?></p>
                        </td>
                        <td class="px-6 py-4 text-gray-700">
                            <p class="text-sm"><?php echo htmlspecialchars(substr($data['rincian_kewenangan_klinis'] ?? '-', 0, 100)); ?><?php echo strlen($data['rincian_kewenangan_klinis'] ?? '') > 100 ? '...' : ''; ?></p>
                        </td>
                        <?php else: ?>
                        <td class="px-6 py-4 text-gray-700">
                            <p class="text-sm"><?php echo htmlspecialchars($data['unit_ruangan'] ?? '-'); ?></p>
                        </td>
                        <?php if ($page === 'komite-keperawatan'): ?>
                        <td class="px-6 py-4 text-gray-700">
                            <p class="text-sm"><?php echo htmlspecialchars($data['lantai'] ?? '-'); ?></p>
                        </td>
                        <td class="px-6 py-4 text-gray-700">
                            <p class="text-sm"><?php echo htmlspecialchars($data['jabatan_keperawatan'] ?? '-'); ?></p>
                        </td>
                        <?php endif; ?>
                        <?php if ($page === 'komite-keperawatan' || $page === 'komite-nakes' || $page === 'komite-tenaga-kesehatan-lainnya'): ?>
                        <td class="px-6 py-4 text-gray-700">
                            <p class="text-sm"><?php echo htmlspecialchars(substr($data['rincian_kewenangan_klinis'] ?? '-', 0, 100)); ?><?php echo strlen($data['rincian_kewenangan_klinis'] ?? '') > 100 ? '...' : ''; ?></p>
                        </td>
                        <td class="px-6 py-4 text-gray-700">
                            <p class="text-sm"><?php echo htmlspecialchars($data['nomor_keputusan_direktur'] ?? '-'); ?></p>
                        </td>
                        <?php endif; ?>
                        <?php endif; ?>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $data['status_kepegawaian'] === 'Tetap' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-blue-100 text-blue-800 border-blue-200'; ?>">
                                                    <?php echo htmlspecialchars($data['status_kepegawaian']); ?>
                                                </span>
                                            </td>
                                            <?php if ($page === 'komite-medik' || $page === 'komite-keperawatan' || $page === 'komite-nakes'): ?>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm"><?php echo htmlspecialchars($data['nomor_pkwt'] ?? '-'); ?></p>
                                            </td>
                                            <?php endif; ?>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm font-mono"><?php echo htmlspecialchars($data['no_sip'] ?? $data['no_str'] ?? '-'); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">
                                                <p class="text-sm">
                                                    <?php 
                                                    $masaAwal = $data['masa_berlaku_sip_mulai'] ?? $data['masa_berlaku_sk_mulai'] ?? null;
                                                    $masaAkhir = $data['masa_berlaku_sip_akhir'] ?? $data['masa_berlaku_sk_akhir'] ?? null;
                                                    if ($masaAwal && $masaAkhir) {
                                                        echo formatDate($masaAwal) . ' - ' . formatDate($masaAkhir);
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex gap-2">
                                                    <?php if ($data['file_str']): ?>
                                                        <a href="<?php echo htmlspecialchars($data['file_str']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">STR</a>
                                                    <?php endif; ?>
                                                    <?php if ($data['file_sip']): ?>
                                                        <a href="<?php echo htmlspecialchars($data['file_sip']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">SIP</a>
                                                    <?php endif; ?>
                                                    <?php if ($data['file_pks']): ?>
                                                        <a href="<?php echo htmlspecialchars($data['file_pks']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">PKS</a>
                                                    <?php endif; ?>
                                                    <?php if ($data['file_sk']): ?>
                                                        <a href="<?php echo htmlspecialchars($data['file_sk']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">SK</a>
                                                    <?php endif; ?>
                                                    <?php if (!$data['file_str'] && !$data['file_sip'] && !$data['file_pks'] && !$data['file_sk']): ?>
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <button class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">Edit</button>
                                                    <a href="tenaga_medis.php?page=<?php echo $page; ?>&delete=<?php echo $data['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">Hapus</a>
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

    <!-- Modal SIP Dokter -->
    <div id="sipDokterModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Form Input SIP Dokter</h2>
                <button onclick="closeModal('sipDokterModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                <!-- Informasi Dasar -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Informasi Dasar</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_lengkap" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nama lengkap">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit/Ruangan</label>
                            <select name="unit_ruangan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <?php foreach ($unitRuanganOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Kepegawaian <span class="text-red-500">*</span></label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="status_kepegawaian" value="Tetap" checked class="text-emerald-600 focus:ring-emerald-500">
                                    <span>Tetap</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="status_kepegawaian" value="Tidak Tetap" class="text-emerald-600 focus:ring-emerald-500">
                                    <span>Tidak Tetap</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dokumen Legalitas -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Dokumen Legalitas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- STR -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">STR (Surat Tanda Registrasi)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. STR</label>
                                    <input type="text" name="no_str" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor STR">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">STR (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_str" accept=".pdf" class="hidden" id="file_str_sip">
                                        <label for="file_str_sip" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file STR (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SIP -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">SIP (Surat Izin Praktik)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. SIP</label>
                                    <input type="text" name="no_sip" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SIP">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_sip_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_sip_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SIP (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_sip" accept=".pdf" class="hidden" id="file_sip_sip">
                                        <label for="file_sip_sip" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file SIP (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PKS -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">PKS (Perjanjian Kerja Sama)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. PKS</label>
                                    <input type="text" name="no_pks" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor PKS">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_pks_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_pks_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">PKS (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_pks" accept=".pdf" class="hidden" id="file_pks_sip">
                                        <label for="file_pks_sip" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file PKS (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SK -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">SK (Surat Keputusan)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. SK</label>
                                    <input type="text" name="no_sk" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SK">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_sk_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_sk_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SK (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_sk" accept=".pdf" class="hidden" id="file_sk_sip">
                                        <label for="file_sk_sip" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file SK (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kompetensi Klinis -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Kompetensi Klinis</h3>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Sertifikasi Kompetensi</label>
                            <button type="button" onclick="addSertifikasi('sipDokter')" class="text-emerald-600 hover:text-emerald-700 text-sm font-medium flex items-center gap-1">
                                <span>+</span> Tambah Sertifikasi
                            </button>
                        </div>
                        <div id="sertifikasi-container-sipDokter" class="space-y-2">
                            <div class="flex gap-2">
                                <input type="text" name="sertifikasi[]" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan sertifikasi">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kompetensi Klinis</label>
                        <textarea name="kompetensi_klinis" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Tulis daftar tindakan medis yang diizinkan"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('sipDokterModal')" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-300 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_sip_dokter" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal STR Nakes -->
    <div id="strNakesModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Form Registrasi STR Nakes</h2>
                <button onclick="closeModal('strNakesModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                <!-- Informasi Dasar -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Informasi Dasar</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_lengkap" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nama lengkap">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit/Ruangan</label>
                            <select name="unit_ruangan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <?php foreach ($unitRuanganOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Kepegawaian <span class="text-red-500">*</span></label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="status_kepegawaian" value="Tetap" checked class="text-emerald-600 focus:ring-emerald-500">
                                    <span>Tetap</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="status_kepegawaian" value="Tidak Tetap" class="text-emerald-600 focus:ring-emerald-500">
                                    <span>Tidak Tetap</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dokumen Legalitas -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Dokumen Legalitas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- STR -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">STR (Surat Tanda Registrasi)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. STR</label>
                                    <input type="text" name="no_str" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor STR">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">STR (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_str" accept=".pdf" class="hidden" id="file_str_str">
                                        <label for="file_str_str" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file STR (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SIP -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">SIP (Surat Izin Praktik)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. SIP</label>
                                    <input type="text" name="no_sip" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SIP">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_sip_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_sip_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SIP (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_sip" accept=".pdf" class="hidden" id="file_sip_str">
                                        <label for="file_sip_str" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file SIP (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PKS -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">PKS (Perjanjian Kerja Sama)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. PKS</label>
                                    <input type="text" name="no_pks" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor PKS">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_pks_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_pks_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">PKS (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_pks" accept=".pdf" class="hidden" id="file_pks_str">
                                        <label for="file_pks_str" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file PKS (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SK -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">SK (Surat Keputusan)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. SK</label>
                                    <input type="text" name="no_sk" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SK">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_sk_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_sk_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SK (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_sk" accept=".pdf" class="hidden" id="file_sk_str">
                                        <label for="file_sk_str" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file SK (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kompetensi Klinis -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Kompetensi Klinis</h3>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Sertifikasi Kompetensi</label>
                            <button type="button" onclick="addSertifikasi('strNakes')" class="text-emerald-600 hover:text-emerald-700 text-sm font-medium flex items-center gap-1">
                                <span>+</span> Tambah Sertifikasi
                            </button>
                        </div>
                        <div id="sertifikasi-container-strNakes" class="space-y-2">
                            <div class="flex gap-2">
                                <input type="text" name="sertifikasi[]" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan sertifikasi">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kompetensi Klinis</label>
                        <textarea name="kompetensi_klinis" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Tulis daftar tindakan medis yang diizinkan"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('strNakesModal')" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-300 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_str_nakes" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Tambah Tenaga Medis -->
    <div id="tambahTenagaMedisModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Form Tambah Tenaga Medis</h2>
                <button onclick="closeModal('tambahTenagaMedisModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                <!-- Informasi Dasar -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Informasi Dasar</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_lengkap" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nama lengkap">
                        </div>
                        <?php if ($page === 'komite-medik'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Spesialis</label>
                            <input type="text" name="spesialis" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan spesialis">
                        </div>
                        <?php elseif ($page === 'komite-keperawatan'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit/Ruangan</label>
                            <input type="text" name="unit_ruangan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan unit/ruangan">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lantai</label>
                            <select name="lantai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="1">Lantai 1</option>
                                <option value="2">Lantai 2</option>
                                <option value="3">Lantai 3</option>
                                <option value="5">Lantai 5</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
                            <select name="jabatan_keperawatan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <?php foreach ($jabatanKeperawatanOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php elseif ($page === 'komite-nakes' || $page === 'komite-tenaga-kesehatan-lainnya'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
                            <select name="unit_ruangan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <?php foreach ($jabatanOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($page === 'komite-medik'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Karyawan <span class="text-red-500">*</span></label>
                            <select name="status_kepegawaian" id="status_karyawan_medik" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">[ Pilih Status Karyawan ]</option>
                                <option value="Tetap" selected>Tetap</option>
                                <option value="Tidak Tetap">Tidak Tetap</option>
                            </select>
                        </div>
                        <div id="container_sk_medik" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor SK Direktur</label>
                            <input type="text" name="nomor_keputusan_direktur" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SK Direktur">
                        </div>
                        <div id="container_pkwt_medik" class="mt-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor PKWT</label>
                            <input type="text" name="nomor_pkwt" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor PKWT">
                        </div>
                        <?php elseif ($page === 'komite-keperawatan'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Karyawan <span class="text-red-500">*</span></label>
                            <select name="status_kepegawaian" id="status_karyawan" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Pilih Status Karyawan</option>
                                <option value="Tetap" selected>Tetap</option>
                                <option value="Tidak Tetap">Tidak Tetap</option>
                            </select>
                        </div>
                        <div id="container_sk" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor SK Direktur</label>
                            <input type="text" name="nomor_keputusan_direktur" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SK Direktur">
                        </div>
                        <div id="container_pkwt" class="mt-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor PKWT</label>
                            <input type="text" name="nomor_pkwt" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor PKWT">
                        </div>
                        <?php elseif ($page === 'komite-nakes'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Karyawan <span class="text-red-500">*</span></label>
                            <select name="status_kepegawaian" id="status_karyawan_nakes" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">[ Pilih Status Karyawan ]</option>
                                <option value="Tetap" selected>Tetap</option>
                                <option value="Tidak Tetap">Tidak Tetap</option>
                            </select>
                        </div>
                        <div id="container_sk_nakes" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor SK Direktur</label>
                            <input type="text" name="nomor_keputusan_direktur" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SK Direktur">
                        </div>
                        <div id="container_pkwt_nakes" class="mt-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor PKWT</label>
                            <input type="text" name="nomor_pkwt" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor PKWT">
                        </div>
                        <?php elseif ($page === 'komite-tenaga-kesehatan-lainnya'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Karyawan <span class="text-red-500">*</span></label>
                            <select name="status_kepegawaian" id="status_karyawan_lainnya" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">[ Pilih Status Karyawan ]</option>
                                <option value="Tetap" selected>Tetap</option>
                                <option value="Tidak Tetap">Tidak Tetap</option>
                            </select>
                        </div>
                        <div id="container_sk_lainnya" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor SK Direktur</label>
                            <input type="text" name="nomor_keputusan_direktur" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SK Direktur">
                        </div>
                        <div id="container_pkwt_lainnya" class="mt-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor PKWT</label>
                            <input type="text" name="nomor_pkwt" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor PKWT">
                        </div>
                        <?php else: ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Kepegawaian <span class="text-red-500">*</span></label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="status_kepegawaian" value="Tetap" checked class="text-emerald-600 focus:ring-emerald-500">
                                    <span>Tetap</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="status_kepegawaian" value="Tidak Tetap" class="text-emerald-600 focus:ring-emerald-500">
                                    <span>Tidak Tetap</span>
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (in_array($page, ['komite-medik', 'komite-keperawatan', 'komite-nakes', 'komite-tenaga-kesehatan-lainnya'])): ?>
                    <div class="space-y-2 pt-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rincian Kewenangan Klinis</label>
                        <textarea name="rincian_kewenangan_klinis" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan rincian kewenangan klinis"></textarea>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Dokumen Legalitas -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Dokumen Legalitas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- STR -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">STR (Surat Tanda Registrasi)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. STR</label>
                                    <input type="text" name="no_str" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor STR">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">STR (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_str" accept=".pdf" class="hidden" id="file_str_ttm">
                                        <label for="file_str_ttm" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file STR (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SIP -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">SIP (Surat Izin Praktik)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. SIP</label>
                                    <input type="text" name="no_sip" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SIP">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_sip_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_sip_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SIP (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_sip" accept=".pdf" class="hidden" id="file_sip_ttm">
                                        <label for="file_sip_ttm" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file SIP (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PKS -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">PKS (Perjanjian Kerja Sama)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. PKS</label>
                                    <input type="text" name="no_pks" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor PKS">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_pks_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_pks_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">PKS (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_pks" accept=".pdf" class="hidden" id="file_pks_ttm">
                                        <label for="file_pks_ttm" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file PKS (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SK -->
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-4">SK (Surat Keputusan)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">No. SK</label>
                                    <input type="text" name="no_sk" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan nomor SK">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Mulai)</label>
                                        <input type="date" name="masa_berlaku_sk_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku (Akhir)</label>
                                        <input type="date" name="masa_berlaku_sk_akhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SK (PDF)</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-emerald-500 transition-colors">
                                        <input type="file" name="file_sk" accept=".pdf" class="hidden" id="file_sk_ttm">
                                        <label for="file_sk_ttm" class="cursor-pointer">
                                            <span class="text-2xl">📎</span>
                                            <p class="text-sm text-gray-500 mt-1">Pilih file SK (PDF)</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kompetensi Klinis -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Kompetensi Klinis</h3>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Sertifikasi Kompetensi</label>
                            <button type="button" onclick="addSertifikasi('tambahTenagaMedis')" class="text-emerald-600 hover:text-emerald-700 text-sm font-medium flex items-center gap-1">
                                <span>+</span> Tambah Sertifikasi
                            </button>
                        </div>
                        <div id="sertifikasi-container-tambahTenagaMedis" class="space-y-2">
                            <div class="flex gap-2">
                                <input type="text" name="sertifikasi[]" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan sertifikasi">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kompetensi Klinis</label>
                        <textarea name="kompetensi_klinis" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Tulis daftar tindakan medis yang diizinkan"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('tambahTenagaMedisModal')" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-300 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_tenaga_medis" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Import Excel</h2>
                <button onclick="closeModal('importModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" action="export_handler.php?action=import_data&module=tenaga_medis&subpage=<?php echo $page; ?>" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih File CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('importModal')" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Import
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
        }

        function openImportModal() {
            openModal('importModal');
        }

        function addSertifikasi(prefix) {
            const container = document.getElementById('sertifikasi-container-' + prefix);
            const template = `
                <div class="flex gap-2">
                    <input type="text" name="sertifikasi[]" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan sertifikasi">
                </div>
            `;
            container.insertAdjacentHTML('beforeend', template);
        }

        // Handle status change for Komite Medik, Komite Keperawatan, Komite Nakes, Komite Tenaga Kesehatan Lainnya
        document.addEventListener('DOMContentLoaded', function() {
            // For radio button status
            const statusRadios = document.querySelectorAll('input[name="status_kepegawaian"]');
            const pkwtContainer = document.getElementById('nomor-pkwt-container');
            const keputusanDirekturContainer = document.getElementById('keputusan-direktur-container');
            
            // For dropdown status (Komite Keperawatan)
            const statusKaryawanDropdown = document.getElementById('status_karyawan');
            const containerSK = document.getElementById('container_sk');
            const containerPKWT = document.getElementById('container_pkwt');
            
            // For dropdown status (Komite Medik)
            const statusKaryawanMedikDropdown = document.getElementById('status_karyawan_medik');
            const containerSKMedik = document.getElementById('container_sk_medik');
            const containerPKWTMedik = document.getElementById('container_pkwt_medik');
            
            // For dropdown status (Komite Nakes)
            const statusKaryawanNakesDropdown = document.getElementById('status_karyawan_nakes');
            const containerSKNakes = document.getElementById('container_sk_nakes');
            const containerPKWTNakes = document.getElementById('container_pkwt_nakes');
            
            // For dropdown status (Komite Tenaga Kesehatan Lainnya)
            const statusKaryawanLainnyaDropdown = document.getElementById('status_karyawan_lainnya');
            const containerSKLainnya = document.getElementById('container_sk_lainnya');
            const containerPKWTLainnya = document.getElementById('container_pkwt_lainnya');
            
            function updateRadioVisibility() {
                const checkedStatus = document.querySelector('input[name="status_kepegawaian"]:checked');
                if (checkedStatus) {
                    if (pkwtContainer) {
                        pkwtContainer.style.display = checkedStatus.value === 'Tetap' ? 'none' : 'block';
                    }
                    if (keputusanDirekturContainer) {
                        keputusanDirekturContainer.style.display = checkedStatus.value === 'Tetap' ? 'block' : 'none';
                    }
                }
            }
            
            // Helper function to handle dropdown status
            function setupDropdownHandler(dropdown, skContainer, pkwtContainer) {
                if (dropdown && skContainer && pkwtContainer) {
                    // Initial check
                    const initialStatus = dropdown.value;
                    if (initialStatus === 'Tetap') {
                        skContainer.classList.remove('hidden');
                        pkwtContainer.classList.add('hidden');
                    } else if (initialStatus === 'Tidak Tetap') {
                        skContainer.classList.add('hidden');
                        pkwtContainer.classList.remove('hidden');
                    } else {
                        skContainer.classList.add('hidden');
                        pkwtContainer.classList.add('hidden');
                    }
                    
                    dropdown.addEventListener('change', function() { 
                        const status = this.value; 
                        
                        // Sembunyikan keduanya dulu setiap ada perubahan 
                        skContainer.classList.add('hidden'); 
                        pkwtContainer.classList.add('hidden'); 
                        
                        if (status === 'Tetap') { 
                            skContainer.classList.remove('hidden'); 
                        } else if (status === 'Tidak Tetap') { 
                            pkwtContainer.classList.remove('hidden'); 
                        } 
                    });
                }
            }
            
            // Setup Komite Keperawatan dropdown
            setupDropdownHandler(statusKaryawanDropdown, containerSK, containerPKWT);
            
            // Setup Komite Medik dropdown
            setupDropdownHandler(statusKaryawanMedikDropdown, containerSKMedik, containerPKWTMedik);
            
            // Setup Komite Nakes dropdown
            setupDropdownHandler(statusKaryawanNakesDropdown, containerSKNakes, containerPKWTNakes);
            
            // Setup Komite Tenaga Kesehatan Lainnya dropdown
            setupDropdownHandler(statusKaryawanLainnyaDropdown, containerSKLainnya, containerPKWTLainnya);
            
            // For remaining radios (if any)
            if (statusRadios.length > 0) {
                updateRadioVisibility();
                statusRadios.forEach(radio => {
                    radio.addEventListener('change', updateRadioVisibility);
                });
            }
        });
    </script>
</body>
</html>
