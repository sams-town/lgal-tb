<?php
/*
CREATE TABLE IF NOT EXISTS dokumen_legal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori VARCHAR(50) NOT NULL,
    nama_dokumen VARCHAR(255) NOT NULL,
    sub_kategori VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL,
    status VARCHAR(50) NOT NULL,
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kategori (kategori),
    INDEX idx_sub_kategori (sub_kategori),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dokumen_arsip_legal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipe_kontrak VARCHAR(100) NOT NULL,
    perusahaan VARCHAR(255) NOT NULL,
    ruang_lingkup TEXT NULL,
    nilai_kontrak DECIMAL(15,2) NULL,
    tanggal_mulai DATE NULL,
    tanggal_berakhir DATE NULL,
    nama_pj VARCHAR(255) NULL,
    no_telp_pj VARCHAR(50) NULL,
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipe_kontrak (tipe_kontrak),
    INDEX idx_tanggal_berakhir (tanggal_berakhir)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengajuan_pks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_instansi VARCHAR(255) NOT NULL,
    nomor_dokumen VARCHAR(255) NOT NULL,
    kategori_pks VARCHAR(50) NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_berakhir DATE NOT NULL,
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kategori_pks (kategori_pks),
    INDEX idx_tanggal_berakhir (tanggal_berakhir)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dokumen_regulasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul_regulasi VARCHAR(255) NOT NULL,
    nomor_regulasi VARCHAR(255) NOT NULL,
    kategori_regulasi VARCHAR(50) NOT NULL,
    tanggal_terbit DATE NOT NULL,
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kategori_regulasi (kategori_regulasi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dokumen_perizinan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_izin VARCHAR(255) NOT NULL,
    pemilik_izin VARCHAR(50) NOT NULL,
    masa_berlaku_mulai DATE NOT NULL,
    masa_berlaku_akhir DATE NOT NULL,
    instansi_penerbit VARCHAR(255) NOT NULL,
    penanggung_jawab VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pemilik_izin (pemilik_izin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengajuan_dokumen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jenis_pengajuan VARCHAR(50) NOT NULL,
    pengusul VARCHAR(255) NOT NULL,
    jenis_dokumen VARCHAR(255) NOT NULL,
    tanggal DATE NOT NULL,
    ruang_lingkup TEXT NULL,
    alasan_perubahan TEXT NULL,
    alasan_pencabutan TEXT NULL,
    step_status JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jenis_pengajuan (jenis_pengajuan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Initialize tables and insert sample data if empty
try {
    // Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dokumen_legal (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kategori VARCHAR(50) NOT NULL,
            nama_dokumen VARCHAR(255) NOT NULL,
            sub_kategori VARCHAR(100) NOT NULL,
            tanggal DATE NOT NULL,
            status VARCHAR(50) NOT NULL,
            file_path VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kategori (kategori),
            INDEX idx_sub_kategori (sub_kategori),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengajuan_pks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal_pengajuan DATE NULL,
            unit_pengusul VARCHAR(255) NULL,
            jenis_kerjasama VARCHAR(50) NULL,
            objek_kerjasama TEXT NULL,
            analisa_alasan TEXT NULL,
            calon_mitra JSON NULL,
            keunggulan_mitra TEXT NULL,
            kekurangan_mitra TEXT NULL,
            biaya TEXT NULL,
            referensi_kerjasama TEXT NULL,
            capaian_mutu TEXT NULL,
            rekomendasi_pengadaan TEXT NULL,
            rekomendasi_legal TEXT NULL,
            nomor_dokumen VARCHAR(255) NULL,
            tanggal_mulai DATE NULL,
            tanggal_berakhir DATE NULL,
            file_path VARCHAR(255) NULL,
            step_status JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Add columns if they don't exist for existing tables
    try {
        // Check for step_status column
        $stepStatusCol = $pdo->query("SHOW COLUMNS FROM pengajuan_pks LIKE 'step_status'")->fetch();
        if (!$stepStatusCol) {
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN step_status JSON NULL AFTER file_path");
        }
        
        $columns = $pdo->query("SHOW COLUMNS FROM pengajuan_pks LIKE 'tanggal_pengajuan'")->fetch();
        if (!$columns) {
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN tanggal_pengajuan DATE NOT NULL AFTER id");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN unit_pengusul VARCHAR(255) NOT NULL AFTER tanggal_pengajuan");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN jenis_kerjasama VARCHAR(50) NOT NULL AFTER unit_pengusul");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN objek_kerjasama TEXT NOT NULL AFTER jenis_kerjasama");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN analisa_alasan TEXT NOT NULL AFTER objek_kerjasama");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN calon_mitra JSON NOT NULL AFTER analisa_alasan");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN keunggulan_mitra TEXT NULL AFTER calon_mitra");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN kekurangan_mitra TEXT NULL AFTER keunggulan_mitra");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN biaya TEXT NULL AFTER kekurangan_mitra");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN referensi_kerjasama TEXT NULL AFTER biaya");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN capaian_mutu TEXT NULL AFTER referensi_kerjasama");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN rekomendasi_pengadaan TEXT NULL AFTER capaian_mutu");
            $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN rekomendasi_legal TEXT NULL AFTER rekomendasi_pengadaan");
        }
    } catch (PDOException $e) {
        // Ignore errors for existing columns
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dokumen_regulasi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul_regulasi VARCHAR(255) NOT NULL,
            nomor_regulasi VARCHAR(255) NOT NULL,
            kategori_regulasi VARCHAR(50) NOT NULL,
            tanggal_terbit DATE NOT NULL,
            file_path VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kategori_regulasi (kategori_regulasi)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dokumen_perizinan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_izin VARCHAR(255) NOT NULL,
            pemilik_izin VARCHAR(50) NOT NULL,
            masa_berlaku_mulai DATE NOT NULL,
            masa_berlaku_akhir DATE NOT NULL,
            instansi_penerbit VARCHAR(255) NOT NULL,
            penanggung_jawab VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pemilik_izin (pemilik_izin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Check if table exists and has correct structure and data
    try {
        $checkTable = $pdo->query("DESCRIBE dokumen_arsip_legal")->fetchAll();
        $hasNewColumns = false;
        foreach ($checkTable as $col) {
            if ($col['Field'] === 'tipe_kontrak') {
                $hasNewColumns = true;
                break;
            }
        }
        if (!$hasNewColumns) {
            $pdo->exec("DROP TABLE IF EXISTS dokumen_arsip_legal");
        } else {
            // Check if we have old data types
            $checkData = $pdo->query("SELECT tipe_kontrak FROM dokumen_arsip_legal LIMIT 1")->fetch();
            if ($checkData && in_array($checkData['tipe_kontrak'], ['PKS Medis', 'PKS Non-Medis', 'JKN/BPJS', 'Asuransi Swasta', 'Vendor', 'KSO', 'Dokumen Internal', 'Dokumen Eksternal'])) {
                $pdo->exec("DROP TABLE IF EXISTS dokumen_arsip_legal");
            }
        }
    } catch (PDOException $e) {
        $pdo->exec("DROP TABLE IF EXISTS dokumen_arsip_legal");
    }

    // Create new table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dokumen_arsip_legal (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipe_kontrak VARCHAR(100) NOT NULL,
            perusahaan VARCHAR(255) NOT NULL,
            ruang_lingkup TEXT NULL,
            nilai_kontrak DECIMAL(15,2) NULL,
            tanggal_mulai DATE NULL,
            tanggal_berakhir DATE NULL,
            nama_pj VARCHAR(255) NULL,
            no_telp_pj VARCHAR(50) NULL,
            file_path VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tipe_kontrak (tipe_kontrak),
            INDEX idx_tanggal_berakhir (tanggal_berakhir)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert sample data for dokumen_arsip_legal if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_arsip_legal");
    if (false && $stmt->fetchColumn() == 0) {
        $today = new DateTime();
        $today2 = clone $today;
        $today3 = clone $today;
        $sampleData = [
            ['tipe_kontrak' => 'Asuransi', 'perusahaan' => 'PT Asuransi Sejahtera', 'ruang_lingkup' => 'Asuransi karyawan', 'nilai_kontrak' => 100000000, 'tanggal_mulai' => '2024-01-01', 'tanggal_berakhir' => $today->add(new DateInterval('P120D'))->format('Y-m-d'), 'nama_pj' => 'Dr. Andi', 'no_telp_pj' => '081234567890', 'file_path' => 'uploads/arsip_legal/pks_medis.pdf'],
            ['tipe_kontrak' => 'Operasional', 'perusahaan' => 'PT Bersih Sehat', 'ruang_lingkup' => 'Layanan kebersihan', 'nilai_kontrak' => 20000000, 'tanggal_mulai' => '2024-05-01', 'tanggal_berakhir' => $today2->sub(new DateInterval('P30D'))->format('Y-m-d'), 'nama_pj' => 'Budi Santoso', 'no_telp_pj' => '085678901234', 'file_path' => null],
            ['tipe_kontrak' => 'Farmasi', 'perusahaan' => 'PT Farmasi Sejahtera', 'ruang_lingkup' => 'Penyediaan obat-obatan', 'nilai_kontrak' => 300000000, 'tanggal_mulai' => '2023-01-01', 'tanggal_berakhir' => $today3->sub(new DateInterval('P120D'))->format('Y-m-d'), 'nama_pj' => 'Siti Aminah', 'no_telp_pj' => '089012345678', 'file_path' => null]
        ];
        $stmt = $pdo->prepare("INSERT INTO dokumen_arsip_legal (tipe_kontrak, perusahaan, ruang_lingkup, nilai_kontrak, tanggal_mulai, tanggal_berakhir, nama_pj, no_telp_pj, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleData as $data) {
            $stmt->execute([$data['tipe_kontrak'], $data['perusahaan'], $data['ruang_lingkup'], $data['nilai_kontrak'], $data['tanggal_mulai'], $data['tanggal_berakhir'], $data['nama_pj'], $data['no_telp_pj'], $data['file_path']]);
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengajuan_dokumen (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul_dokumen VARCHAR(255) NOT NULL,
            jenis_pengajuan VARCHAR(50) NOT NULL,
            jenis_regulasi VARCHAR(100) NOT NULL,
            kategori_akreditasi VARCHAR(100) NOT NULL,
            unit_pengusul VARCHAR(255) NOT NULL,
            ruang_lingkup TEXT NOT NULL,
            tujuan_regulasi TEXT NOT NULL,
            dasar_hukum TEXT NULL,
            tanggal_pengajuan DATE NOT NULL,
            file_path VARCHAR(255) NULL,
            catatan_pengusul TEXT NULL,
            step_status JSON NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_jenis_pengajuan (jenis_pengajuan)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Add columns if they don't exist for existing tables
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM pengajuan_dokumen LIKE 'judul_dokumen'")->fetch();
        if (!$columns) {
            $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN judul_dokumen VARCHAR(255) NOT NULL AFTER id");
            $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN jenis_regulasi VARCHAR(100) NOT NULL AFTER jenis_pengajuan");
            $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN kategori_akreditasi VARCHAR(100) NOT NULL AFTER jenis_regulasi");
            $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN unit_pengusul VARCHAR(255) NOT NULL AFTER kategori_akreditasi");
            $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN tujuan_regulasi TEXT NOT NULL AFTER ruang_lingkup");
            $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN dasar_hukum TEXT NULL AFTER tujuan_regulasi");
            $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN tanggal_pengajuan DATE NOT NULL AFTER dasar_hukum");
            $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN file_path VARCHAR(255) NULL AFTER tanggal_pengajuan");
            $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN catatan_pengusul TEXT NULL AFTER file_path");
        }
    } catch (PDOException $e) {
        // Ignore errors for existing columns
    }

    // Insert sample data for regulasi if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_regulasi");
    if (false && $stmt->fetchColumn() == 0) {
        $sampleData = [
            ['judul_regulasi' => 'Standar Pelayanan Operasional', 'nomor_regulasi' => 'SPO-001/2024', 'kategori_regulasi' => 'SPO', 'tanggal_terbit' => '2024-01-15', 'file_path' => 'uploads/legal/regulasi-001.pdf'],
            ['judul_regulasi' => 'Peraturan Direktur No. 05', 'nomor_regulasi' => 'PERDIR-05/2024', 'kategori_regulasi' => 'Peraturan Direktur', 'tanggal_terbit' => '2024-03-20', 'file_path' => null]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO dokumen_regulasi (judul_regulasi, nomor_regulasi, kategori_regulasi, tanggal_terbit, file_path)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($sampleData as $data) {
            $stmt->execute([
                $data['judul_regulasi'],
                $data['nomor_regulasi'],
                $data['kategori_regulasi'],
                $data['tanggal_terbit'],
                $data['file_path']
            ]);
        }
    }

    // Insert sample data for perizinan if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_perizinan");
    if (false && $stmt->fetchColumn() == 0) {
        $sampleData = [
            ['nama_izin' => 'Izin Operasional Rumah Sakit', 'pemilik_izin' => 'RS THB', 'masa_berlaku_mulai' => '2023-01-01', 'masa_berlaku_akhir' => '2028-01-01', 'instansi_penerbit' => 'Dinas Kesehatan', 'penanggung_jawab' => 'Dr. Andi Wijaya', 'file_path' => 'uploads/legal/perizinan-001.pdf'],
            ['nama_izin' => 'Izin Praktik Medis', 'pemilik_izin' => 'PT PBA', 'masa_berlaku_mulai' => '2024-01-01', 'masa_berlaku_akhir' => '2029-01-01', 'instansi_penerbit' => 'Kementerian Kesehatan', 'penanggung_jawab' => 'Dr. Budi Santoso', 'file_path' => null]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO dokumen_perizinan (nama_izin, pemilik_izin, masa_berlaku_mulai, masa_berlaku_akhir, instansi_penerbit, penanggung_jawab, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($sampleData as $data) {
            $stmt->execute([
                $data['nama_izin'],
                $data['pemilik_izin'],
                $data['masa_berlaku_mulai'],
                $data['masa_berlaku_akhir'],
                $data['instansi_penerbit'],
                $data['penanggung_jawab'],
                $data['file_path']
            ]);
        }
    }

    // Insert sample data for pengajuan_dokumen if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengajuan_dokumen");
    if (false && $stmt->fetchColumn() == 0) {
        $initialStepStatus = json_encode([
            'km' => 'pending',
            'legal' => 'pending',
            'sekretariat' => 'pending',
            'dk' => 'pending',
            'dsdml' => 'pending',
            'du' => 'pending'
        ]);

        $sampleData = [
            [
                'judul_dokumen' => 'Peraturan Penggunaan Alat Medis Baru',
                'jenis_pengajuan' => 'Pengajuan Baru',
                'jenis_regulasi' => 'Peraturan Direktur',
                'kategori_akreditasi' => 'Tata Kelola Rumah Sakit',
                'unit_pengusul' => 'Bagian Medis',
                'ruang_lingkup' => 'Seluruh unit rumah sakit',
                'tujuan_regulasi' => 'Menyediakan panduan penggunaan alat medis baru',
                'dasar_hukum' => 'Peraturan Kemenkes No. 12 Tahun 2023',
                'tanggal_pengajuan' => '2024-06-10',
                'catatan_pengusul' => 'Membutuhkan review cepat',
                'file_path' => null,
                'step_status' => $initialStepStatus
            ],
            [
                'judul_dokumen' => 'Revisi SPO Unit Gawat Darurat',
                'jenis_pengajuan' => 'Perubahan Dokumen',
                'jenis_regulasi' => 'SPO',
                'kategori_akreditasi' => 'Pelayanan Medis',
                'unit_pengusul' => 'Unit Gawat Darurat',
                'ruang_lingkup' => 'Unit Gawat Darurat',
                'tujuan_regulasi' => 'Perubahan alur pelayanan untuk efisiensi',
                'dasar_hukum' => null,
                'tanggal_pengajuan' => '2024-06-11',
                'catatan_pengusul' => null,
                'file_path' => null,
                'step_status' => $initialStepStatus
            ],
            [
                'judul_dokumen' => 'Pencabutan Peraturan Lama',
                'jenis_pengajuan' => 'Pencabutan Dokumen',
                'jenis_regulasi' => 'Keputusan Direktur',
                'kategori_akreditasi' => 'Tata Kelola Rumah Sakit',
                'unit_pengusul' => 'Kepala Rumah Sakit',
                'ruang_lingkup' => 'Seluruh unit rumah sakit',
                'tujuan_regulasi' => 'Menghapus peraturan yang sudah tidak relevan',
                'dasar_hukum' => null,
                'tanggal_pengajuan' => '2024-06-12',
                'catatan_pengusul' => 'Peraturan sudah tidak sesuai dengan kondisi saat ini',
                'file_path' => null,
                'step_status' => $initialStepStatus
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO pengajuan_dokumen (judul_dokumen, jenis_pengajuan, jenis_regulasi, kategori_akreditasi, unit_pengusul, ruang_lingkup, tujuan_regulasi, dasar_hukum, tanggal_pengajuan, catatan_pengusul, file_path, step_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($sampleData as $data) {
            $stmt->execute([
                $data['judul_dokumen'],
                $data['jenis_pengajuan'],
                $data['jenis_regulasi'],
                $data['kategori_akreditasi'],
                $data['unit_pengusul'],
                $data['ruang_lingkup'],
                $data['tujuan_regulasi'],
                $data['dasar_hukum'],
                $data['tanggal_pengajuan'],
                $data['catatan_pengusul'],
                $data['file_path'],
                $data['step_status']
            ]);
        }
    }

    // Insert sample data for pengajuan_pks if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengajuan_pks");
    if (false && $stmt->fetchColumn() == 0) {
        $today = new DateTime();
        $samplePKS = [
            [
                'tanggal_pengajuan' => '2024-06-01',
                'unit_pengusul' => 'Bagian Medis',
                'jenis_kerjasama' => 'Klinis',
                'objek_kerjasama' => 'Kerjasama penyediaan tenaga medis',
                'analisa_alasan' => 'Kebutuhan penambahan tenaga medis untuk unit gawat darurat',
                'calon_mitra' => json_encode([
                    ['nama' => 'PT Mitra Sehat Medika', 'narahubung' => '08123456789'],
                    ['nama' => 'Klinik Utama Sejahtera', 'narahubung' => '08198765432']
                ]),
                'keunggulan_mitra' => 'Mitra memiliki tenaga medis yang berpengalaman',
                'kekurangan_mitra' => 'Jarak dari rumah sakit agak jauh',
                'biaya' => 'Biaya sesuai dengan budget yang tersedia',
                'referensi_kerjasama' => 'Sudah bekerjasama dengan rumah sakit lain di daerah',
                'capaian_mutu' => 'Sesuai dengan standar mutu rumah sakit',
                'rekomendasi_pengadaan' => null,
                'rekomendasi_legal' => null,
                'nomor_dokumen' => 'PKS-001/2024',
                'tanggal_mulai' => '2024-07-01',
                'tanggal_berakhir' => '2025-06-30',
                'file_path' => null
            ],
            [
                'tanggal_pengajuan' => '2024-06-05',
                'unit_pengusul' => 'Bagian Umum',
                'jenis_kerjasama' => 'Non Klinis',
                'objek_kerjasama' => 'Kerjasama penyediaan alat kebersihan',
                'analisa_alasan' => 'Kebutuhan penggantian vendor alat kebersihan yang lebih efisien',
                'calon_mitra' => json_encode([
                    ['nama' => 'CV Bersih Sejahtera', 'narahubung' => '08212345678'],
                    ['nama' => 'PT Cleaning Service Indonesia', 'narahubung' => '08298765432']
                ]),
                'keunggulan_mitra' => 'Harga kompetitif dan layanan 24 jam',
                'kekurangan_mitra' => 'Belum memiliki pengalaman dengan rumah sakit',
                'biaya' => 'Lebih hemat 15% dari vendor sebelumnya',
                'referensi_kerjasama' => 'Bekerjasama dengan beberapa perkantoran besar',
                'capaian_mutu' => 'Memiliki sertifikat ISO 9001',
                'rekomendasi_pengadaan' => null,
                'rekomendasi_legal' => null,
                'nomor_dokumen' => 'PKS-002/2024',
                'tanggal_mulai' => '2024-08-01',
                'tanggal_berakhir' => '2025-07-31',
                'file_path' => null
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO pengajuan_pks (
                tanggal_pengajuan, unit_pengusul, jenis_kerjasama, objek_kerjasama, 
                analisa_alasan, calon_mitra, keunggulan_mitra, kekurangan_mitra, 
                biaya, referensi_kerjasama, capaian_mutu, rekomendasi_pengadaan, 
                rekomendasi_legal, nomor_dokumen, tanggal_mulai, tanggal_berakhir, file_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($samplePKS as $data) {
            $stmt->execute([
                $data['tanggal_pengajuan'],
                $data['unit_pengusul'],
                $data['jenis_kerjasama'],
                $data['objek_kerjasama'],
                $data['analisa_alasan'],
                $data['calon_mitra'],
                $data['keunggulan_mitra'],
                $data['kekurangan_mitra'],
                $data['biaya'],
                $data['referensi_kerjasama'],
                $data['capaian_mutu'],
                $data['rekomendasi_pengadaan'],
                $data['rekomendasi_legal'],
                $data['nomor_dokumen'],
                $data['tanggal_mulai'],
                $data['tanggal_berakhir'],
                $data['file_path']
            ]);
        }
    }
} catch (PDOException $e) {
    // Continue if sample data fails to insert
}

// Handle form submission for adding new PKS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pks'])) {
    $tanggal_pengajuan = $_POST['tanggal_pengajuan'] ?? null;
    $unit_pengusul = $_POST['unit_pengusul'] ?? null;
    $jenis_kerjasama = $_POST['jenis_kerjasama'] ?? null;
    $objek_kerjasama = $_POST['objek_kerjasama'] ?? null;
    $analisa_alasan = $_POST['analisa_alasan'] ?? null;
    
    // Process calon mitra
    $calon_mitra = [];
    if (isset($_POST['nama_mitra']) && is_array($_POST['nama_mitra'])) {
        foreach ($_POST['nama_mitra'] as $index => $nama) {
            if (!empty($nama)) {
                $calon_mitra[] = [
                    'nama' => $nama,
                    'narahubung' => $_POST['narahubung'][$index] ?? ''
                ];
            }
        }
    }
    $calon_mitra_json = json_encode($calon_mitra);
    
    $keunggulan_mitra = $_POST['keunggulan_mitra'] ?? null;
    $kekurangan_mitra = $_POST['kekurangan_mitra'] ?? null;
    $biaya = $_POST['biaya'] ?? null;
    $referensi_kerjasama = $_POST['referensi_kerjasama'] ?? null;
    $capaian_mutu = $_POST['capaian_mutu'] ?? null;
    $rekomendasi_pengadaan = $_POST['rekomendasi_pengadaan'] ?? null;
    $rekomendasi_legal = $_POST['rekomendasi_legal'] ?? null;
    
    $nomor_dokumen = $_POST['nomor_dokumen'] ?? null;
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? null;
    $tanggal_berakhir = $_POST['tanggal_berakhir'] ?? null;
    
    $file_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/pks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $file_path = $targetFile;
        }
    }

    // Insert into database
    try {
        // Initialize step status for new PKS
        $initialStepStatus = [
            'km' => 'pending',
            'legal' => 'pending',
            'sekretariat' => 'pending',
            'dk' => 'pending',
            'dsdml' => 'pending',
            'du' => 'pending'
        ];
        $step_status_json = json_encode($initialStepStatus);
        
        $stmt = $pdo->prepare("
            INSERT INTO pengajuan_pks (
                tanggal_pengajuan, unit_pengusul, jenis_kerjasama, objek_kerjasama, 
                analisa_alasan, calon_mitra, keunggulan_mitra, kekurangan_mitra, 
                biaya, referensi_kerjasama, capaian_mutu, rekomendasi_pengadaan, 
                rekomendasi_legal, nomor_dokumen, tanggal_mulai, tanggal_berakhir, 
                file_path, step_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tanggal_pengajuan, $unit_pengusul, $jenis_kerjasama, $objek_kerjasama,
            $analisa_alasan, $calon_mitra_json, $keunggulan_mitra, $kekurangan_mitra,
            $biaya, $referensi_kerjasama, $capaian_mutu, $rekomendasi_pengadaan,
            $rekomendasi_legal, $nomor_dokumen, $tanggal_mulai, $tanggal_berakhir,
            $file_path, $step_status_json
        ]);
        
        // Send notification for new PKS
        notifyByPermission(
            "Pengajuan PKS Baru",
            "Ada pengajuan PKS baru untuk kerjasama $jenis_kerjasama dengan objek: $objek_kerjasama dari unit $unit_pengusul.",
            "legal"
        );
        
        $_SESSION['pks_success'] = "Pengajuan PKS berhasil disimpan!";
        
    } catch (PDOException $e) {
        $_SESSION['pks_error'] = "Gagal menyimpan data: " . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header("Location: legal.php?page=pks");
    exit;
}

// Handle form submission for adding new Regulasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_regulasi'])) {
    $judul_regulasi = $_POST['judul_regulasi'];
    $nomor_regulasi = $_POST['nomor_regulasi'];
    $kategori_regulasi = $_POST['kategori_regulasi'];
    $tanggal_terbit = $_POST['tanggal_terbit'];
    $file_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/legal/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $file_path = $targetFile;
        }
    }

    // Insert into database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO dokumen_regulasi (judul_regulasi, nomor_regulasi, kategori_regulasi, tanggal_terbit, file_path)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$judul_regulasi, $nomor_regulasi, $kategori_regulasi, $tanggal_terbit, $file_path]);
        
        // Send notification for new regulasi
        createNotification(
            "Dokumen Regulasi Baru",
            "Dokumen regulasi baru telah ditambahkan: $judul_regulasi (nomor: $nomor_regulasi).",
            "Staf Legal"
        );
        
    } catch (PDOException $e) {
        $error = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// Handle form submission for adding new Perizinan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_perizinan'])) {
    $nama_izin = $_POST['nama_izin'];
    $pemilik_izin = $_POST['pemilik_izin'];
    $masa_berlaku_mulai = $_POST['masa_berlaku_mulai'];
    $masa_berlaku_akhir = $_POST['masa_berlaku_akhir'];
    $instansi_penerbit = $_POST['instansi_penerbit'];
    $penanggung_jawab = $_POST['penanggung_jawab'];
    $file_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/legal/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $file_path = $targetFile;
        }
    }

    // Insert into database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO dokumen_perizinan (nama_izin, pemilik_izin, masa_berlaku_mulai, masa_berlaku_akhir, instansi_penerbit, penanggung_jawab, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nama_izin, $pemilik_izin, $masa_berlaku_mulai, $masa_berlaku_akhir, $instansi_penerbit, $penanggung_jawab, $file_path]);
        
        // Send notification for new perizinan
        notifyByPermission(
            "Dokumen Perizinan Baru",
            "Ada perizinan baru ($nama_izin) yang perlu direview.",
            "legal"
        );
        
    } catch (PDOException $e) {
        $error = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// Handle form submission for adding new Arsip Dokumen Legal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_arsip_legal'])) {
    $tipe_kontrak = $_POST['tipe_kontrak'];
    $perusahaan = $_POST['perusahaan'];
    $ruang_lingkup = $_POST['ruang_lingkup'] ?? null;
    $nilai_kontrak = !empty($_POST['nilai_kontrak']) ? (float)$_POST['nilai_kontrak'] : null;
    $tanggal_mulai = !empty($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : null;
    $tanggal_berakhir = !empty($_POST['tanggal_berakhir']) ? $_POST['tanggal_berakhir'] : null;
    $nama_pj = $_POST['nama_pj'] ?? null;
    $no_telp_pj = $_POST['no_telp_pj'] ?? null;
    $file_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/arsip_legal/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $file_path = $targetFile;
        }
    }

    // Insert into database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO dokumen_arsip_legal (tipe_kontrak, perusahaan, ruang_lingkup, nilai_kontrak, tanggal_mulai, tanggal_berakhir, nama_pj, no_telp_pj, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tipe_kontrak, $perusahaan, $ruang_lingkup, $nilai_kontrak, $tanggal_mulai, $tanggal_berakhir, $nama_pj, $no_telp_pj, $file_path]);
        $success = "Dokumen arsip legal berhasil ditambahkan!";
        
        // Send notification
        notifyByPermission(
            "Dokumen Arsip Legal Baru",
            "Dokumen arsip legal baru telah ditambahkan: $perusahaan ($tipe_kontrak)",
            "legal"
        );
    } catch (PDOException $e) {
        $error = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// Handle form submission for adding new Pengajuan Dokumen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pengajuan'])) {
    $judulDokumen = $_POST['judul_dokumen'];
    $jenisPengajuan = $_POST['jenis_pengajuan'];
    $jenisRegulasi = $_POST['jenis_regulasi'];
    $kategoriAkreditasi = $_POST['kategori_akreditasi'];
    $unitPengusul = $_POST['unit_pengusul'];
    $ruangLingkup = $_POST['ruang_lingkup'];
    $tujuanRegulasi = $_POST['tujuan_regulasi'];
    $dasarHukum = $_POST['dasar_hukum'] ?? null;
    $tanggalPengajuan = $_POST['tanggal_pengajuan'];
    $catatanPengusul = $_POST['catatan_pengusul'] ?? null;
    
    $filePath = null;
    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/pengajuan/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $filePath = $targetFile;
        }
    }

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
                unit_pengusul, ruang_lingkup, tujuan_regulasi, dasar_hukum, 
                tanggal_pengajuan, file_path, catatan_pengusul, step_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $judulDokumen,
            $jenisPengajuan,
            $jenisRegulasi,
            $kategoriAkreditasi,
            $unitPengusul,
            $ruangLingkup,
            $tujuanRegulasi,
            $dasarHukum,
            $tanggalPengajuan,
            $filePath,
            $catatanPengusul,
            json_encode($stepStatus)
        ]);
        
        // Send notification to Komite Mutu
        notifyByPermission(
            "Verifikasi Regulasi",
            "Ada pengajuan regulasi baru ($judulDokumen) dari unit $unitPengusul yang perlu diverifikasi.",
            "legal"
        );
        
    } catch (PDOException $e) {
        $error = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// Handle form submission for adding new legal document (non-PKS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_dokumen'])) {
    $kategori = $_POST['kategori'];
    $nama_dokumen = $_POST['nama_dokumen'];
    $sub_kategori = $_POST['sub_kategori'];
    $tanggal = $_POST['tanggal'];
    $status = $_POST['status'];
    $file_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/legal/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $file_path = $targetFile;
        }
    }

    // Insert into database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO dokumen_legal (kategori, nama_dokumen, sub_kategori, tanggal, status, file_path)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$kategori, $nama_dokumen, $sub_kategori, $tanggal, $status, $file_path]);
    } catch (PDOException $e) {
        $error = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $currentPage = $_GET['page'] ?? 'pks';
    try {
        if ($currentPage === 'pks') {
            // Get file path before deleting
            $stmt = $pdo->prepare("SELECT file_path FROM pengajuan_pks WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM pengajuan_pks WHERE id = ?");
            $stmt->execute([$id]);

            // Delete file if exists
            if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
        } elseif ($currentPage === 'regulasi') {
            // Get file path before deleting
            $stmt = $pdo->prepare("SELECT file_path FROM dokumen_regulasi WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM dokumen_regulasi WHERE id = ?");
            $stmt->execute([$id]);

            // Delete file if exists
            if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
        } elseif ($currentPage === 'perizinan') {
            // Get file path before deleting
            $stmt = $pdo->prepare("SELECT file_path FROM dokumen_perizinan WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM dokumen_perizinan WHERE id = ?");
            $stmt->execute([$id]);

            // Delete file if exists
            if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
        } elseif ($currentPage === 'legal-arsip') {
            // Get file path before deleting
            $stmt = $pdo->prepare("SELECT file_path FROM dokumen_arsip_legal WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM dokumen_arsip_legal WHERE id = ?");
            $stmt->execute([$id]);

            // Delete file if exists
            if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
        } elseif ($currentPage === 'pengajuan') {
            // Get file path before deleting
            $stmt = $pdo->prepare("SELECT file_path FROM pengajuan_dokumen WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM pengajuan_dokumen WHERE id = ?");
            $stmt->execute([$id]);

            // Delete file if exists
            if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
        } else {
            // Get file path before deleting
            $stmt = $pdo->prepare("SELECT file_path FROM dokumen_legal WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM dokumen_legal WHERE id = ?");
            $stmt->execute([$id]);

            // Delete file if exists
            if ($doc && $doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
        }
    } catch (PDOException $e) {
        $error = "Gagal menghapus data: " . $e->getMessage();
    }
}

// Get page parameter
$page = isset($_GET['page']) ? $_GET['page'] : 'pks';
// Normalize page parameter (support both legal-xxx and xxx, but skip legal-arsip)
if (str_starts_with($page, 'legal-') && $page !== 'legal-arsip') {
    $page = substr($page, 6);
}
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'semua';

// Define page configuration
$pageConfigs = [
    'pks' => [
        'title' => 'Perjanjian Kerjasama (PKS)',
        'description' => 'Manajemen seluruh perjanjian kerjasama rumah sakit dengan mitra dan pihak ketiga',
        'statsLabels' => ['Total Pengajuan', 'Klinis', 'Non Klinis', '-']
    ],
    'regulasi' => [
        'title' => 'Regulasi',
        'description' => 'Arsip dan manajemen seluruh peraturan dan undang-undang terkait kesehatan',
        'statsLabels' => ['Total Regulasi', 'SPO', 'Peraturan Direktur', 'Kebijakan Mutu']
    ],
    'perizinan' => [
        'title' => 'Perizinan',
        'description' => 'Pengelolaan seluruh izin operasional dan perizinan rumah sakit',
        'statsLabels' => ['Total Perizinan', 'RS THB', 'PT PBA', 'Aktif']
    ],
    'pengajuan' => [
        'title' => 'Pengajuan Dokumen',
        'description' => 'Pengelolaan pengajuan, perubahan, dan pencabutan dokumen',
        'statsLabels' => ['Total Pengajuan', 'Pengajuan Baru', 'Perubahan', 'Pencabutan']
    ],
    'legal-arsip' => [
        'title' => 'Arsip Dokumen Legal',
        'description' => 'Tempat menyimpan dokumen umum legal dan pengingat otomatis kadaluarsa',
        'statsLabels' => ['Total Dokumen', 'Aktif', 'Mendekati Kadaluarsa', 'Kadaluarsa']
    ]
];

$currentConfig = $pageConfigs[$page] ?? $pageConfigs['pks'];

// Get documents based on page
try {
    if ($page === 'pks') {
        $stmt = $pdo->query("SELECT * FROM pengajuan_pks ORDER BY created_at DESC");
        $documents = $stmt->fetchAll();
    } elseif ($page === 'regulasi') {
        $stmt = $pdo->query("SELECT * FROM dokumen_regulasi ORDER BY created_at DESC");
        $documents = $stmt->fetchAll();
    } elseif ($page === 'perizinan') {
        $stmt = $pdo->query("SELECT * FROM dokumen_perizinan ORDER BY created_at DESC");
        $documents = $stmt->fetchAll();
    } elseif ($page === 'pengajuan') {
        $stmt = $pdo->query("SELECT * FROM pengajuan_dokumen ORDER BY created_at DESC");
        $documents = $stmt->fetchAll();
    } elseif ($page === 'legal-arsip') {
        $stmt = $pdo->query("SELECT * FROM dokumen_arsip_legal ORDER BY created_at DESC");
        $documents = $stmt->fetchAll();
    } else {
        $whereConditions = ['kategori = ?'];
        $params = [$page];

        if ($statusFilter !== 'semua') {
            $whereConditions[] = 'status = ?';
            $params[] = $statusFilter;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $stmt = $pdo->prepare("SELECT * FROM dokumen_legal WHERE $whereClause ORDER BY created_at DESC");
        $stmt->execute($params);
        $documents = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $documents = [];
}

// Calculate stats
try {
    if ($page === 'pks') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM pengajuan_pks");
        $totalDocs = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_pks WHERE jenis_kerjasama = ?");
        $stmt->execute(['Klinis']);
        $aktif = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_pks WHERE jenis_kerjasama = ?");
        $stmt->execute(['Non Klinis']);
        $mendekati = $stmt->fetchColumn();

        $kadaluarsa = 0;
    } elseif ($page === 'regulasi') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_regulasi");
        $totalDocs = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_regulasi WHERE kategori_regulasi = ?");
        $stmt->execute(['SPO']);
        $spo = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_regulasi WHERE kategori_regulasi = ?");
        $stmt->execute(['Peraturan Direktur']);
        $perdir = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_regulasi WHERE kategori_regulasi = ?");
        $stmt->execute(['Kebijakan Mutu']);
        $kebijakan = $stmt->fetchColumn();
    } elseif ($page === 'perizinan') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_perizinan");
        $totalDocs = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_perizinan WHERE pemilik_izin = ?");
        $stmt->execute(['RS THB']);
        $rsthb = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_perizinan WHERE pemilik_izin = ?");
        $stmt->execute(['PT PBA']);
        $ptpba = $stmt->fetchColumn();

        $today = new DateTime();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_perizinan WHERE masa_berlaku_akhir > ?");
        $stmt->execute([$today->format('Y-m-d')]);
        $aktif = $stmt->fetchColumn();
    } elseif ($page === 'pengajuan') {
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
    } elseif ($page === 'legal-arsip') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM dokumen_arsip_legal");
        $totalDocs = $stmt->fetchColumn();

        $today = new DateTime();
        $sixtyDaysFromNow = (clone $today)->add(new DateInterval('P60D'));

        // Aktif (lebih dari 60 hari lagi atau tanpa tanggal berakhir)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_arsip_legal WHERE tanggal_berakhir > ? OR tanggal_berakhir IS NULL");
        $stmt->execute([$sixtyDaysFromNow->format('Y-m-d')]);
        $aktif = $stmt->fetchColumn();

        // Mendekati kadaluarsa (1-60 hari)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_arsip_legal WHERE tanggal_berakhir BETWEEN ? AND ?");
        $stmt->execute([$today->format('Y-m-d'), $sixtyDaysFromNow->format('Y-m-d')]);
        $mendekati = $stmt->fetchColumn();

        // Kadaluarsa (sudah lewat)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_arsip_legal WHERE tanggal_berakhir < ?");
        $stmt->execute([$today->format('Y-m-d')]);
        $kadaluarsa = $stmt->fetchColumn();
    } else {
        // Total documents
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_legal WHERE kategori = ?");
        $stmt->execute([$page]);
        $totalDocs = $stmt->fetchColumn();

        // Aktif/Terpenuhi
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_legal WHERE kategori = ? AND status = 'Publish/Aktif'");
        $stmt->execute([$page]);
        $aktif = $stmt->fetchColumn();

        // Dalam Review
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_legal WHERE kategori = ? AND status IN ('Draft', 'Review Legal')");
        $stmt->execute([$page]);
        $review = $stmt->fetchColumn();

        // Perlu Perhatian/Kadaluarsa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_legal WHERE kategori = ? AND status = 'Approval'");
        $stmt->execute([$page]);
        $butuhPerhatian = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    $totalDocs = $aktif = $mendekati = $kadaluarsa = $spo = $perdir = $kebijakan = $rsthb = $ptpba = $baru = $perubahan = $pencabutan = 0;
}

// Helper functions
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Publish/Aktif':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'Approval':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'Review Legal':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'Draft':
            return 'bg-gray-100 text-gray-800 border-gray-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

function getPKSStatus($tanggalBerakhir) {
    $today = new DateTime();
    $berakhir = new DateTime($tanggalBerakhir);
    $diff = $today->diff($berakhir)->days;
    $invert = $today->diff($berakhir)->invert;

    if ($invert == 1) {
        return ['text' => 'Kadaluarsa', 'class' => 'bg-red-100 text-red-800 border-red-200'];
    } elseif ($diff <= 60) {
        return ['text' => "Mendekati Kadaluarsa (H-$diff)", 'class' => 'bg-amber-100 text-amber-800 border-amber-200'];
    } else {
        return ['text' => 'Aktif', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200'];
    }
}

function getLegalArsipStatus($tanggalBerakhir) {
    if (!$tanggalBerakhir) {
        return ['text' => 'Tidak Ada Tanggal Berakhir', 'class' => 'bg-gray-100 text-gray-800 border-gray-200'];
    }
    $today = new DateTime();
    $berakhir = new DateTime($tanggalBerakhir);
    $diff = $today->diff($berakhir)->days;
    $invert = $today->diff($berakhir)->invert;

    if ($invert == 1) {
        return ['text' => 'Expired / Kadaluarsa', 'class' => 'bg-red-600 text-white border-red-700'];
    } elseif ($diff <= 60) {
        return ['text' => 'Mendekati Kadaluarsa', 'class' => 'bg-amber-100 text-amber-800 border-amber-200'];
    } else {
        return ['text' => 'Aktif', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200'];
    }
}

function formatDate($date) {
    if (!$date) return '-';
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $months[$d->format('n')] . ' ' . $d->format('Y');
}

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
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <?php include 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900"><?php echo $currentConfig['title']; ?></h1>
                    <p class="text-gray-600 mt-2"><?php echo $currentConfig['description']; ?></p>
                </div>

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

                <!-- Page Tabs for Legal -->
                <div class="flex flex-wrap gap-3">
                    <?php 
                    $pageTabs = [
                        'pks' => 'PKS',
                        'regulasi' => 'Regulasi',
                        'perizinan' => 'Perizinan',
                        'legal-arsip' => 'Arsip Dokumen'
                    ];
                    foreach ($pageTabs as $pageVal => $pageLabel): 
                    ?>
                        <a href="legal.php?page=<?php echo $pageVal; ?>" class="px-5 py-2 rounded-xl font-medium transition-all <?php echo $page === $pageVal ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:border-emerald-300 hover:text-emerald-600'; ?>">
                            <?php echo $pageLabel; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php if ($page === 'pks'): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][0]; ?></p>
                                    <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1">Klinis</p>
                                    <h3 class="text-3xl font-bold text-emerald-600"><?php echo $aktif; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">🏥</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1">Non Klinis</p>
                                    <h3 class="text-3xl font-bold text-blue-600"><?php echo $mendekati; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">🏢</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1">-</p>
                                    <h3 class="text-3xl font-bold text-gray-400">-</h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-gray-300 to-gray-400 rounded-2xl flex items-center justify-center text-3xl">-</div>
                            </div>
                        </div>
                    <?php elseif ($page === 'regulasi'): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][0]; ?></p>
                                    <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][1]; ?></p>
                                    <h3 class="text-3xl font-bold text-emerald-600"><?php echo $spo; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">📚</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][2]; ?></p>
                                    <h3 class="text-3xl font-bold text-blue-600"><?php echo $perdir; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">📋</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][3]; ?></p>
                                    <h3 class="text-3xl font-bold text-amber-600"><?php echo $kebijakan; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">📝</div>
                            </div>
                        </div>
                    <?php elseif ($page === 'perizinan'): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][0]; ?></p>
                                    <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][1]; ?></p>
                                    <h3 class="text-3xl font-bold text-emerald-600"><?php echo $rsthb; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">🏥</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][2]; ?></p>
                                    <h3 class="text-3xl font-bold text-blue-600"><?php echo $ptpba; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">🏢</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][3]; ?></p>
                                    <h3 class="text-3xl font-bold text-amber-600"><?php echo $aktif; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">✅</div>
                            </div>
                        </div>
                    <?php elseif ($page === 'legal-arsip'): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][0]; ?></p>
                                    <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][1]; ?></p>
                                    <h3 class="text-3xl font-bold text-emerald-600"><?php echo $aktif; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">✅</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][2]; ?></p>
                                    <h3 class="text-3xl font-bold text-amber-600"><?php echo $mendekati; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">⚠️</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][3]; ?></p>
                                    <h3 class="text-3xl font-bold text-red-600"><?php echo $kadaluarsa; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-3xl">❌</div>
                            </div>
                        </div>
                    <?php elseif ($page === 'pengajuan'): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][0]; ?></p>
                                    <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][1]; ?></p>
                                    <h3 class="text-3xl font-bold text-emerald-600"><?php echo $baru; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">➕</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][2]; ?></p>
                                    <h3 class="text-3xl font-bold text-blue-600"><?php echo $perubahan; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">✏️</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][3]; ?></p>
                                    <h3 class="text-3xl font-bold text-red-600"><?php echo $pencabutan; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-3xl">🗑️</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][0]; ?></p>
                                    <h3 class="text-3xl font-bold text-gray-900"><?php echo $totalDocs; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📄</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][1]; ?></p>
                                    <h3 class="text-3xl font-bold text-emerald-600"><?php echo $aktif; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">✅</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][2]; ?></p>
                                    <h3 class="text-3xl font-bold text-blue-600"><?php echo $review; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">📝</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1"><?php echo $currentConfig['statsLabels'][3]; ?></p>
                                    <h3 class="text-3xl font-bold text-amber-600"><?php echo $butuhPerhatian; ?></h3>
                                </div>
                                <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">⚠️</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Export/Import Buttons -->
                <?php if (in_array($page, ['pks', 'regulasi', 'perizinan', 'legal-arsip'])): ?>
                <div class="flex items-center gap-3 mb-4">
                    <a href="export_handler.php?action=export_data&module=<?php echo $page; ?>" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        📊
                        <span>Export Excel</span>
                    </a>
                    <button onclick="openImportModal()" class="flex items-center gap-2 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        📤
                        <span>Import Excel</span>
                    </button>
                    <a href="export_handler.php?action=download_template&module=<?php echo $page; ?>" class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                        📄
                        <span>Download Template</span>
                    </a>
                    <?php if ($page === 'pks'): ?>
                    <a href="legal.php?page=pengajuan" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors shadow-sm ml-4">
                        📝
                        <span>Pengajuan Dokumen</span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <?php if ($page === 'pks'): ?>
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal Pengajuan</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Unit Pengusul</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Jenis Kerjasama</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Objek Kerjasama</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Calon Mitra</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                                Belum ada pengajuan kerjasama yang tersedia
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $index => $doc): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo formatDate($doc['tanggal_pengajuan'] ?? null); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo htmlspecialchars($doc['unit_pengusul'] ?? '-'); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php $jenis_kerjasama = $doc['jenis_kerjasama'] ?? '-'; ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $jenis_kerjasama === 'Klinis' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-blue-100 text-blue-800 border-blue-200'; ?>">
                                                        <?php echo htmlspecialchars($jenis_kerjasama); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700 max-w-xs">
                                                    <p class="text-sm truncate" title="<?php echo htmlspecialchars($doc['objek_kerjasama'] ?? '-'); ?>"><?php echo htmlspecialchars($doc['objek_kerjasama'] ?? '-'); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700 max-w-xs">
                                                    <?php 
                                                    $calonMitra = json_decode($doc['calon_mitra'] ?? '[]', true);
                                                    if (!empty($calonMitra)) {
                                                        $mitraNames = array_column($calonMitra, 'nama');
                                                        echo '<p class="text-sm truncate" title="' . htmlspecialchars(implode(', ', $mitraNames)) . '">' . htmlspecialchars(implode(', ', $mitraNames)) . '</p>';
                                                    } else {
                                                        echo '<span class="text-gray-400 text-sm">-</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                    <?php if (!empty($file_path)): ?>
                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                            📥
                                                            <span>Download</span>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                        <?php if (!empty($file_path)): ?>
                                                            <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                                Lihat
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="legal.php?delete=<?php echo $doc['id']; ?>&page=<?php echo $page; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                                            Hapus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php elseif ($page === 'regulasi'): ?>
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Judul Regulasi</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nomor Regulasi</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Kategori Regulasi</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal Terbit</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                                Belum ada dokumen regulasi yang tersedia
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $index => $doc): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['judul_regulasi'] ?? '-'); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo htmlspecialchars($doc['nomor_regulasi'] ?? '-'); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border bg-blue-100 text-blue-800 border-blue-200">
                                                        <?php echo htmlspecialchars($doc['kategori_regulasi'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo formatDate($doc['tanggal_terbit'] ?? null); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                    <?php if (!empty($file_path)): ?>
                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                            📥
                                                            <span>Download</span>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                        <?php if (!empty($file_path)): ?>
                                                            <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                                Lihat
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="legal.php?delete=<?php echo $doc['id']; ?>&page=<?php echo $page; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                                            Hapus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php elseif ($page === 'perizinan'): ?>
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nama Izin</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Pemilik Izin</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Masa Berlaku</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Instansi Penerbit</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Penanggung Jawab</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                                Belum ada dokumen perizinan yang tersedia
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $index => $doc): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['nama_izin'] ?? '-'); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php $pemilik_izin = $doc['pemilik_izin'] ?? '-'; ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $pemilik_izin === 'RS THB' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-blue-100 text-blue-800 border-blue-200'; ?>">
                                                        <?php echo htmlspecialchars($pemilik_izin); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo formatDate($doc['masa_berlaku_mulai'] ?? null); ?> - <?php echo formatDate($doc['masa_berlaku_akhir'] ?? null); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo htmlspecialchars($doc['instansi_penerbit'] ?? '-'); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo htmlspecialchars($doc['penanggung_jawab'] ?? '-'); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                    <?php if (!empty($file_path)): ?>
                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                            📥
                                                            <span>Download</span>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                        <?php if (!empty($file_path)): ?>
                                                            <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                                Lihat
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="legal.php?delete=<?php echo $doc['id']; ?>&page=<?php echo $page; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                                            Hapus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php elseif ($page === 'legal-arsip'): ?>
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tipe Kontrak</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Perusahaan/Instansi</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Ruang Lingkup Kerjasama</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nilai Kontrak</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Jangka Waktu</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Penanggung Jawab Mitra</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="10" class="px-6 py-12 text-center text-gray-500">
                                                Belum ada dokumen arsip yang tersedia
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $index => $doc): 
                                            $status = getLegalArsipStatus($doc['tanggal_berakhir'] ?? null);
                                        ?>
                                            <tr class="hover:bg-gray-50 <?php echo $status['text'] === 'Expired / Kadaluarsa' ? 'bg-red-50' : ''; ?>">
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border bg-blue-100 text-blue-800 border-blue-200">
                                                        <?php echo htmlspecialchars($doc['tipe_kontrak'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['perusahaan'] ?? '-'); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo htmlspecialchars($doc['ruang_lingkup'] ?? '-'); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-semibold">
                                                        <?php echo $doc['nilai_kontrak'] ? 'Rp ' . number_format($doc['nilai_kontrak'], 2, ',', '.') : '-'; ?>
                                                    </p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm">
                                                        <?php echo formatDate($doc['tanggal_mulai'] ?? null); ?> - <?php echo formatDate($doc['tanggal_berakhir'] ?? null); ?>
                                                    </p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm">
                                                        <strong><?php echo htmlspecialchars($doc['nama_pj'] ?? '-'); ?></strong>
                                                        <?php $no_telp_pj = $doc['no_telp_pj'] ?? ''; ?>
                                                        <?php if (!empty($no_telp_pj)): ?>
                                                            <br><small class="text-gray-500"><?php echo htmlspecialchars($no_telp_pj); ?></small>
                                                        <?php endif; ?>
                                                    </p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $status['class']; ?>">
                                                        <?php echo $status['text']; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                    <?php if (!empty($file_path)): ?>
                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                            📥
                                                            <span>Download</span>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                        <?php if (!empty($file_path)): ?>
                                                            <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                                Lihat
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="legal.php?delete=<?php echo $doc['id']; ?>&page=<?php echo $page; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                                            Hapus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php elseif ($page === 'pengajuan'): ?>
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
                                                    <p class="text-sm"><?php echo formatDate($doc['tanggal_pengajuan'] ?? null); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php $file_path = $doc['file_path'] ?? ''; ?>
                                                    <?php if (!empty($file_path)): ?>
                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                            📄 Lihat
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    <?php endif; ?>
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
                        <?php else: ?>
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nama Dokumen</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Sub-Kategori</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Berkas</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                                Belum ada dokumen yang tersedia
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $index => $doc): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['nama_dokumen']); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo htmlspecialchars($doc['sub_kategori']); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo formatDate($doc['tanggal']); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo getStatusBadgeClass($doc['status']); ?>">
                                                        <?php echo htmlspecialchars($doc['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php if ($doc['file_path']): ?>
                                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm flex items-center gap-1">
                                                            📥
                                                            <span>Download</span>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <?php if ($doc['file_path']): ?>
                                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                                                Lihat
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="legal.php?delete=<?php echo $doc['id']; ?>&page=<?php echo $page; ?>&status=<?php echo urlencode($statusFilter); ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                                            Hapus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal for PKS -->
    <?php if ($page === 'pks'): ?>
        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">FORMULIR PENGAJUAN KERJASAMA</h2>
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                    <!-- Bagian 1: Informasi Umum -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Informasi Umum</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Hari, Tanggal, Tahun</label>
                                <input type="date" name="tanggal_pengajuan" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Unit/Departemen Pengusul</label>
                                <input type="text" name="unit_pengusul" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kerjasama</label>
                                <select name="jenis_kerjasama" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    <option value="Klinis">Klinis</option>
                                    <option value="Non Klinis">Non Klinis</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Objek Kerjasama</label>
                                <input type="text" name="objek_kerjasama" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                        </div>
                    </div>

                    <!-- Bagian 2: Diisi oleh Unit Pengusul -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Diisi oleh Unit/Departemen Pengusul/Pengguna</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Analisa dasar/alasan pengajuan kerjasama (disesuaikan dengan kebutuhan, rencana dan budget)</label>
                            <textarea name="analisa_alasan" required rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        </div>

                        <!-- Calon Mitra -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Usulan calon mitra kerjasama (minimal 3 mitra kerjasama)</label>
                            <div id="mitra-container" class="space-y-3">
                                <div class="mitra-item grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Nama Calon Mitra</label>
                                        <input type="text" name="nama_mitra[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Narahubung</label>
                                        <input type="text" name="narahubung[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                    </div>
                                </div>
                                <div class="mitra-item grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Nama Calon Mitra</label>
                                        <input type="text" name="nama_mitra[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Narahubung</label>
                                        <input type="text" name="narahubung[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                    </div>
                                </div>
                                <div class="mitra-item grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Nama Calon Mitra</label>
                                        <input type="text" name="nama_mitra[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Narahubung</label>
                                        <input type="text" name="narahubung[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="addMitra()" class="mt-2 text-emerald-600 hover:text-emerald-700 text-sm font-medium flex items-center gap-1">
                                <span>+</span> Tambah Mitra
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Keunggulan calon mitra kerjasama (dijabarkan)</label>
                            <textarea name="keunggulan_mitra" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kekurangan calon mitra kerjasama (dijabarkan)</label>
                            <textarea name="kekurangan_mitra" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Biaya-biaya</label>
                            <textarea name="biaya" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Referensi kerjasama (calon mitra kerjasama sudah melakukan kerjasama dengan perusahaan apa saja)</label>
                            <textarea name="referensi_kerjasama" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Capaian Mutu Kerjasama</label>
                            <textarea name="capaian_mutu" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        </div>
                    </div>

                    <!-- Bagian 3: Diisi oleh Unit Terkait -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Diisi oleh Unit Terkait</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hasil rekomendasi Bagian Pengadaan</label>
                            <textarea name="rekomendasi_pengadaan" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hasil rekomendasi Bagian Legal</label>
                            <textarea name="rekomendasi_legal" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        </div>
                    </div>

                    <!-- Informasi Dokumen Tambahan -->
                    <div class="space-y-4 border-t pt-4">
                        <h3 class="text-lg font-semibold text-gray-800">Informasi Dokumen (Opsional)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Dokumen</label>
                                <input type="text" name="nomor_dokumen" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai Kerjasama</label>
                                <input type="date" name="tanggal_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Berakhir Kerjasama</label>
                                <input type="date" name="tanggal_berakhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas Softcopy (PDF)</label>
                            <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                            Batal
                        </button>
                        <button type="submit" name="tambah_pks" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($page === 'pengajuan'): ?>
        <!-- Modal for Pengajuan Dokumen -->
        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">Pengajuan Baru/Perubahan/Pencabutan</h2>
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Judul Dokumen <span class="text-red-500">*</span></label>
                        <input type="text" name="judul_dokumen" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Masukkan judul dokumen">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Pengajuan <span class="text-red-500">*</span></label>
                        <select name="jenis_pengajuan" id="jenis_pengajuan" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
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
                        <input type="date" name="tanggal_pengajuan" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload Draft Regulasi <span class="text-red-500">*</span></label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-emerald-500 transition-colors">
                            <input type="file" name="file" accept=".pdf" required class="hidden" id="file-upload">
                            <label for="file-upload" class="cursor-pointer">
                                <div class="text-gray-500 text-4xl mb-2">⬆️</div>
                                <p class="text-gray-600">Klik untuk upload draft regulasi</p>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Catatan Pengusul</label>
                        <textarea name="catatan_pengusul" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Tambahkan catatan pengusul (opsional)"></textarea>
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
    <?php elseif ($page === 'regulasi'): ?>
        <!-- Modal for Regulasi -->
        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">Tambah Dokumen Regulasi</h2>
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Judul Regulasi</label>
                        <input type="text" name="judul_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Regulasi</label>
                        <input type="text" name="nomor_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Regulasi</label>
                        <select name="kategori_regulasi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="SPO">SPO</option>
                            <option value="Peraturan Direktur">Peraturan Direktur</option>
                            <option value="Keputusan Direktur">Keputusan Direktur</option>
                            <option value="Kebijakan Mutu">Kebijakan Mutu</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Terbit</label>
                        <input type="date" name="tanggal_terbit" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas (PDF)</label>
                        <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                            Batal
                        </button>
                        <button type="submit" name="tambah_regulasi" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($page === 'perizinan'): ?>
        <!-- Modal for Perizinan -->
        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">Tambah Dokumen Perizinan</h2>
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Izin</label>
                        <input type="text" name="nama_izin" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pemilik Izin</label>
                        <select name="pemilik_izin" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="RS THB">RS THB</option>
                            <option value="PT PBA">PT PBA</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku Mulai</label>
                            <input type="date" name="masa_berlaku_mulai" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Masa Berlaku Akhir</label>
                            <input type="date" name="masa_berlaku_akhir" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Instansi Penerbit</label>
                        <input type="text" name="instansi_penerbit" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Penanggung Jawab</label>
                        <input type="text" name="penanggung_jawab" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas (PDF)</label>
                        <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                            Batal
                        </button>
                        <button type="submit" name="tambah_perizinan" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($page === 'legal-arsip'): ?>
        <!-- Modal for Legal Arsip -->
        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-2xl shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">Tambah Dokumen Arsip Legal</h2>
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Kontrak</label>
                        <select name="tipe_kontrak" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="Asuransi">Asuransi</option>
                            <option value="Perusahaan">Perusahaan</option>
                            <option value="Alat Kesehatan">Alat Kesehatan</option>
                            <option value="Farmasi">Farmasi</option>
                            <option value="Penelitian/Pendidikan">Penelitian/Pendidikan</option>
                            <option value="Operasional">Operasional</option>
                            <option value="Umum">Umum</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Perusahaan/Instansi</label>
                        <input type="text" name="perusahaan" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ruang Lingkup Kerjasama</label>
                        <textarea name="ruang_lingkup" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nilai Kontrak</label>
                        <input type="number" name="nilai_kontrak" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Berakhir</label>
                            <input type="date" name="tanggal_berakhir" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Penanggung Jawab Mitra</label>
                            <input type="text" name="nama_pj" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon Penanggung Jawab</label>
                            <input type="text" name="no_telp_pj" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload Berkas (PDF)</label>
                        <input type="file" name="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                            Batal
                        </button>
                        <button type="submit" name="tambah_arsip_legal" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Import Excel</h2>
                <button onclick="closeImportModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" action="export_handler.php?action=import_data&module=<?php echo $page; ?>" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih File CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full px-4 py-2 border border-gray-300 rounded-xl">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeImportModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
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
        function openImportModal() {
            openModal('importModal');
        }

        function closeImportModal() {
            closeModal('importModal');
        }

        function addMitra() {
            const container = document.getElementById('mitra-container');
            const template = `
                <div class="mitra-item grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Nama Calon Mitra</label>
                        <input type="text" name="nama_mitra[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Narahubung</label>
                        <input type="text" name="narahubung[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', template);
        }
    </script>
</body>
</html>