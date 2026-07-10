<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

/**
 * Helper to parse various date formats into Y-m-d format for database storage.
 */
function parseImportDate($dateStr) {
    if (empty($dateStr)) {
        return null;
    }
    
    $dateStr = trim($dateStr);
    
    // Check if it's already YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }
    
    // Check for DD/MM/YYYY or DD-MM-YYYY format
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateStr, $matches)) {
        return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
    }
    
    // Check for YYYY/MM/DD format
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $dateStr, $matches)) {
        return sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
    }
    
    // Try strtotime fallback
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

$action = $_GET['action'] ?? '';
$module = $_GET['module'] ?? '';

// Define module configurations
$moduleConfigs = [
    'pks' => [
        'table' => 'pengajuan_pks',
        'filename' => 'pks_data',
        'columns' => [
            'Nama Instansi' => 'unit_pengusul',
            'Kategori PKS' => 'jenis_kerjasama',
            'Objek Kerjasama' => 'objek_kerjasama',
            'Analisa Dasar' => 'analisa_alasan',
            'Nama Calon Mitra' => 'calon_mitra',
            'Biaya' => 'biaya',
            'Potongan Harga' => 'potongan_harga'
        ],
        'insertColumns' => [
            'tanggal_pengajuan', 'unit_pengusul', 'jenis_kerjasama', 'objek_kerjasama', 
            'analisa_alasan', 'calon_mitra', 'keunggulan_mitra', 'kekurangan_mitra', 
            'biaya', 'potongan_harga', 'referensi_kerjasama', 'capaian_mutu', 'rekomendasi_pengadaan', 
            'rekomendasi_legal', 'rekomendasi_keuangan', 'nomor_dokumen', 'tanggal_mulai', 'tanggal_berakhir', 'file_path'
        ],
        'displayName' => 'Perjanjian Kerjasama (PKS)'
    ],
    'regulasi' => [
        'table' => 'dokumen_regulasi',
        'filename' => 'regulasi_data',
        'columns' => [
            'Judul Regulasi' => 'judul_regulasi',
            'Nomor Regulasi' => 'nomor_regulasi',
            'Kategori Regulasi' => 'kategori_regulasi',
            'Tanggal Terbit' => 'tanggal_terbit',
            'Penanggung Jawab' => 'penanggung_jawab'
        ],
        'insertColumns' => ['judul_regulasi', 'nomor_regulasi', 'kategori_regulasi', 'tanggal_terbit', 'penanggung_jawab', 'file_path'],
        'displayName' => 'Regulasi'
    ],
    'perizinan' => [
        'table' => 'dokumen_perizinan',
        'filename' => 'perizinan_data',
        'columns' => [
            'Nama Izin' => 'nama_izin',
            'Nomor Izin' => 'id', // Assuming id is used as nomor izin, adjust if needed
            'Pemilik Izin' => 'pemilik_izin',
            'Tanggal Mulai' => 'masa_berlaku_mulai',
            'Tanggal Berakhir' => 'masa_berlaku_akhir',
            'Instansi Penerbit' => 'instansi_penerbit',
            'Penanggung Jawab' => 'penanggung_jawab'
        ],
        'insertColumns' => ['nama_izin', 'pemilik_izin', 'masa_berlaku_mulai', 'masa_berlaku_akhir', 'instansi_penerbit', 'penanggung_jawab', 'file_path'],
        'displayName' => 'Perizinan'
    ],
    'tenaga_medis' => [
        'table' => 'tenaga_medis',
        'filename' => 'tenaga_medis_data',
        'columns' => [
            'Nama Tenaga Medis' => 'nama_lengkap',
            'Unit/Ruangan' => 'unit_ruangan',
            'Status Kepegawaian' => 'status_kepegawaian',
            'Jabatan Keperawatan' => 'jabatan_keperawatan',
            'Spesialis' => 'spesialis',
            'Lantai' => 'lantai',
            'Nomor SK Direktur' => 'nomor_keputusan_direktur',
            'Nomor PKWT' => 'nomor_pkwt',
            'Rincian Kewenangan Klinis' => 'rincian_kewenangan_klinis',
            'No. STR' => 'no_str',
            'No. SIP' => 'no_sip',
            'Masa Berlaku SIP Mulai' => 'masa_berlaku_sip_mulai',
            'Masa Berlaku SIP Akhir' => 'masa_berlaku_sip_akhir'
        ],
        'insertColumns' => ['nama_lengkap', 'unit_ruangan', 'status_kepegawaian', 'tipe_form', 'jabatan_keperawatan', 'spesialis', 'lantai', 'nomor_keputusan_direktur', 'nomor_pkwt', 'rincian_kewenangan_klinis', 'no_str', 'file_str', 'no_sip', 'masa_berlaku_sip_mulai', 'masa_berlaku_sip_akhir', 'file_sip', 'no_pks', 'masa_berlaku_pks_mulai', 'masa_berlaku_pks_akhir', 'file_pks', 'no_sk', 'masa_berlaku_sk_mulai', 'masa_berlaku_sk_akhir', 'file_sk', 'kompetensi_klinis', 'sertifikasi_kompetensi'],
        'displayName' => 'Tenaga Medis'
    ],
    'sop' => [
        'table' => 'dokumen_sop',
        'filename' => 'sop_data',
        'columns' => [
            'Nomor Dokumen' => 'nomor_sop',
            'Judul Dokumen' => 'judul',
            'Sub Kategori' => 'unit_kerja',
            'Tanggal' => 'tanggal_terbit'
        ],
        'insertColumns' => ['judul', 'nomor_sop', 'unit_kerja', 'tanggal_terbit', 'tanggal_expired', 'file_path', 'created_by'],
        'displayName' => 'SOP & SDM'
    ],
    'corsec' => [
        'table' => 'dokumen_corsec',
        'filename' => 'corsec_data',
        'columns' => [
            'Judul' => 'judul',
            'Nomor Dokumen' => 'nomor_dokumen',
            'Kategori' => 'kategori',
            'Tanggal Terbit' => 'tanggal_terbit'
        ],
        'insertColumns' => ['judul', 'nomor_dokumen', 'kategori', 'tanggal_terbit', 'file_path'],
        'displayName' => 'Corporate Secretary'
    ],
    'legal-arsip' => [
        'table' => 'dokumen_arsip_legal',
        'filename' => 'legal_arsip_data',
        'columns' => [
            'Tipe Kontrak' => 'tipe_kontrak',
            'Perusahaan' => 'perusahaan',
            'Ruang Lingkup' => 'ruang_lingkup',
            'Nilai Kontrak' => 'nilai_kontrak',
            'Potongan Harga' => 'potongan_harga',
            'Cara Pembayaran' => 'cara_pembayaran',
            'Tanggal Mulai' => 'tanggal_mulai',
            'Tanggal Berakhir' => 'tanggal_berakhir',
            'Nama PJ' => 'nama_pj',
            'No Telp PJ' => 'no_telp_pj'
        ],
        'insertColumns' => ['tipe_kontrak', 'perusahaan', 'ruang_lingkup', 'nilai_kontrak', 'potongan_harga', 'cara_pembayaran', 'tanggal_mulai', 'tanggal_berakhir', 'nama_pj', 'no_telp_pj', 'file_path'],
        'displayName' => 'Arsip Dokumen Legal'
    ]
];

if (!isset($moduleConfigs[$module])) {
    die('Modul tidak valid');
}

$config = $moduleConfigs[$module];

if ($action === 'download_template') {
    // Download CSV template
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $config['filename'] . '_template.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array_keys($config['columns']));
    fclose($output);
    exit;

} elseif ($action === 'export_data') {
    // Export data to CSV
    try {
        $stmt = $pdo->query("SELECT * FROM " . $config['table'] . " ORDER BY created_at DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $config['filename'] . '_' . date('YmdHis') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($config['columns']));
        
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($config['columns'] as $dbColumn) {
                $csvRow[] = $row[$dbColumn] ?? '';
            }
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        die('Gagal mengekspor data: ' . $e->getMessage());
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'import_data' && isset($_FILES['csv_file'])) {
        // Import data from CSV
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['import_error'] = 'Gagal mengunggah file';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
        
        $filePath = $file['tmp_name'];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            $_SESSION['import_error'] = 'Gagal membuka file';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
        
        // Skip header row
        fgetcsv($handle);
        
        $importedCount = 0;
        $errors = [];
        
        try {
            $pdo->beginTransaction();
            
            while (($row = fgetcsv($handle)) !== false) {
                // Prepare data based on module
                $insertData = [];
                
                if ($module === 'pks') {
                    // For PKS, we need to handle data from template
                    $insertData = [
                        date('Y-m-d'), // tanggal_pengajuan (today)
                        $row[0] ?? '', // unit_pengusul
                        $row[1] ?? 'Klinis', // jenis_kerjasama
                        $row[2] ?? 'Diimpor dari CSV', // objek_kerjasama
                        $row[3] ?? 'Diimpor dari CSV', // analisa_alasan
                        json_encode([]), // calon_mitra
                        null, // keunggulan_mitra
                        null, // kekurangan_mitra
                        $row[4] ?? null, // biaya
                        $row[5] ?? null, // potongan_harga
                        null, // referensi_kerjasama
                        null, // capaian_mutu
                        null, // rekomendasi_pengadaan
                        null, // rekomendasi_legal
                        null, // rekomendasi_keuangan
                        null, // nomor_dokumen
                        null, // tanggal_mulai
                        null, // tanggal_berakhir
                        null // file_path
                    ];
                } elseif ($module === 'regulasi') {
                    $insertData = [
                        $row[0] ?? '', // judul_regulasi
                        $row[1] ?? '', // nomor_regulasi
                        $row[2] ?? '', // kategori_regulasi
                        parseImportDate($row[3] ?? null) ?? date('Y-m-d'), // tanggal_terbit
                        null // file_path
                    ];
                } elseif ($module === 'perizinan') {
                    $insertData = [
                        $row[0] ?? '', // nama_izin
                        in_array(trim(strtoupper($row[2] ?? '')), ['RS THB', 'PT PBA']) ? trim(strtoupper($row[2])) : 'RS THB', // pemilik_izin
                        parseImportDate($row[3] ?? null) ?? date('Y-m-d'), // masa_berlaku_mulai
                        parseImportDate($row[4] ?? null), // masa_berlaku_akhir
                        $row[5] ?? '', // instansi_penerbit
                        $row[6] ?? '', // penanggung_jawab
                        null // file_path
                    ];
                } elseif ($module === 'tenaga_medis') {
                    $insertData = [
                        $row[0] ?? '', // nama_lengkap
                        $row[1] ?? 'Rawat Inap', // unit_ruangan
                        $row[2] ?? 'Tetap', // status_kepegawaian
                        $_GET['subpage'] ?? 'sip-dokter', // tipe_form
                        $row[3] ?? null, // jabatan_keperawatan
                        $row[4] ?? null, // spesialis
                        $row[5] ?? null, // lantai
                        $row[6] ?? null, // nomor_keputusan_direktur
                        $row[7] ?? null, // nomor_pkwt
                        $row[8] ?? null, // rincian_kewenangan_klinis
                        $row[9] ?? null, // no_str
                        null, // file_str
                        $row[10] ?? null, // no_sip
                        parseImportDate($row[11] ?? null), // masa_berlaku_sip_mulai
                        parseImportDate($row[12] ?? null), // masa_berlaku_sip_akhir
                        null, // file_sip
                        null, // no_pks
                        null, // masa_berlaku_pks_mulai
                        null, // masa_berlaku_pks_akhir
                        null, // file_pks
                        null, // no_sk
                        null, // masa_berlaku_sk_mulai
                        null, // masa_berlaku_sk_akhir
                        null, // file_sk
                        null, // kompetensi_klinis
                        null // sertifikasi_kompetensi
                    ];
                } elseif ($module === 'sop') {
                    $insertData = [
                        $row[1] ?? '', // judul
                        $row[0] ?? '', // nomor_sop
                        $row[2] ?? '', // unit_kerja
                        parseImportDate($row[3] ?? null) ?? date('Y-m-d'), // tanggal_terbit
                        null, // tanggal_expired
                        null, // file_path
                        $_SESSION['user']['id'] ?? null // created_by
                    ];
                } elseif ($module === 'corsec') {
                    $insertData = [
                        $row[0] ?? '', // judul
                        $row[1] ?? '', // nomor_dokumen
                        $row[2] ?? 'GCG', // kategori
                        parseImportDate($row[3] ?? null) ?? date('Y-m-d'), // tanggal_terbit
                        null // file_path
                    ];
                } elseif ($module === 'legal-arsip') {
                    $insertData = [
                        $row[0] ?? 'Asuransi', // tipe_kontrak
                        $row[1] ?? '', // perusahaan
                        $row[2] ?? null, // ruang_lingkup
                        !empty($row[3]) ? (float)$row[3] : null, // nilai_kontrak
                        $row[4] ?? null, // potongan_harga
                        $row[5] ?? null, // cara_pembayaran
                        parseImportDate($row[6] ?? null), // tanggal_mulai
                        parseImportDate($row[7] ?? null), // tanggal_berakhir
                        $row[8] ?? null, // nama_pj
                        $row[9] ?? null, // no_telp_pj
                        null // file_path
                    ];
                }
                
                if (!empty($insertData)) {
                    $placeholders = str_repeat('?,', count($insertData) - 1) . '?';
                    $sql = "INSERT INTO " . $config['table'] . " (" . implode(',', $config['insertColumns']) . ") VALUES ($placeholders)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($insertData);
                    $importedCount++;
                }
            }
            
            $pdo->commit();
            fclose($handle);
            
            // Send notification
            if ($importedCount > 0) {
                createNotification(
                    "Import Data Berhasil",
                    "Berhasil mengimpor $importedCount data baru ke modul " . $config['displayName'] . ".",
                    null, // target_role
                    $_SESSION['user']['id'] ?? null // user_id
                );
            }
            
            $_SESSION['import_success'] = "Berhasil mengimpor $importedCount data";
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            fclose($handle);
            $_SESSION['import_error'] = 'Gagal mengimpor data: ' . $e->getMessage();
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }

die('Aksi tidak valid');
