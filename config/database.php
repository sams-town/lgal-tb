<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Deteksi Otomatis: Apakah web dibuka di Laptop (termasuk CLI) atau Server Hosting cPanel
$isLocal = (php_sapi_name() === 'cli') || 
           (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) || 
           (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', 'new-hospital.test']));

if ($isLocal) {
    // 💻 SETELAN UNTUK LAPTOP ANDA (Laragon/XAMPP)
    $host = 'localhost';
    $dbname = 'new_legal';
    $username = 'root';
    $password = '';
} else {
    // 🌐 SETELAN UNTUK SERVER CPANEL ONLINE (Menggunakan tanda hubung '-')
    $host = 'localhost';
    $dbname = 'rsthbid_admin-legal'; 
    $username = 'rsthbid_user-legal';  
    $password = 'samboja90';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Guard schema checks to run only once per session or on CLI
    $shouldCheck = false;
    if (php_sapi_name() === 'cli') {
        $shouldCheck = true;
    } else {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['schema_checked'])) {
            $shouldCheck = true;
            $_SESSION['schema_checked'] = true;
        }
    }

    if ($shouldCheck) {
        // Auto-ensure required columns on tenaga_medis table exist
        try {
            $checkCols = [
                'masa_berlaku_str_mulai' => "DATE NULL AFTER no_str",
                'masa_berlaku_str_akhir' => "DATE NULL AFTER masa_berlaku_str_mulai",
                'masa_berlaku_pks_akhir' => "DATE NULL AFTER masa_berlaku_pks_mulai",
                'masa_berlaku_sk_akhir' => "DATE NULL AFTER masa_berlaku_sk_mulai"
            ];
            foreach ($checkCols as $cName => $cDef) {
                $colExists = $pdo->query("SHOW COLUMNS FROM tenaga_medis LIKE '$cName'")->rowCount();
                if ($colExists == 0) {
                    $pdo->exec("ALTER TABLE tenaga_medis ADD COLUMN $cName $cDef");
                }
            }
        } catch (Exception $ex) {
            // Silently continue if table not created yet
        }

        // Auto-ensure file_path column exists on pengajuan_dokumen table
        try {
            $colExists = $pdo->query("SHOW COLUMNS FROM pengajuan_dokumen LIKE 'file_path'")->rowCount();
            if ($colExists == 0) {
                $pdo->exec("ALTER TABLE pengajuan_dokumen ADD COLUMN file_path VARCHAR(500) DEFAULT NULL AFTER alasan_pencabutan");
            }
        } catch (Exception $ex) {
            // Silently continue if table not created yet
        }

        // Auto-ensure required columns on dokumen_arsip_legal table exist
        try {
            $checkLegalCols = [
                'potongan_harga' => "VARCHAR(255) NULL AFTER nilai_kontrak",
                'cara_pembayaran' => "VARCHAR(255) NULL AFTER potongan_harga"
            ];
            foreach ($checkLegalCols as $cName => $cDef) {
                $colExists = $pdo->query("SHOW COLUMNS FROM dokumen_arsip_legal LIKE '$cName'")->rowCount();
                if ($colExists == 0) {
                    $pdo->exec("ALTER TABLE dokumen_arsip_legal ADD COLUMN $cName $cDef");
                }
            }

            // Ensure potongan_harga is VARCHAR if it was previously DECIMAL
            $colInfo = $pdo->query("SHOW COLUMNS FROM dokumen_arsip_legal LIKE 'potongan_harga'")->fetch();
            if ($colInfo && strpos(strtolower($colInfo['Type']), 'varchar') === false) {
                $pdo->exec("ALTER TABLE dokumen_arsip_legal MODIFY COLUMN potongan_harga VARCHAR(255) NULL");
            }
        } catch (Exception $ex) {
            // Silently continue if table not created yet
        }

        // Auto-ensure required columns on pengajuan_pks table exist
        try {
            $checkPksCols = [
                'status' => "VARCHAR(50) NOT NULL DEFAULT 'Dalam Proses' AFTER step_status",
                'reject_reason' => "TEXT NULL AFTER status",
                'rekomendasi_keuangan' => "TEXT NULL AFTER rekomendasi_legal",
                'potongan_harga' => "VARCHAR(255) NULL AFTER biaya",
                'status_keuangan' => "VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER rekomendasi_keuangan",
                'status_pengadaan' => "VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER status_keuangan",
                'status_legal' => "VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER status_pengadaan"
            ];
            foreach ($checkPksCols as $cName => $cDef) {
                $colExists = $pdo->query("SHOW COLUMNS FROM pengajuan_pks LIKE '$cName'")->rowCount();
                if ($colExists == 0) {
                    $pdo->exec("ALTER TABLE pengajuan_pks ADD COLUMN $cName $cDef");
                }
            }
            
            // Ensure rekomendasi_legal and file_path columns are TEXT to support JSON arrays
            $colInfo = $pdo->query("SHOW COLUMNS FROM pengajuan_pks LIKE 'rekomendasi_legal'")->fetch();
            if ($colInfo && strpos(strtolower($colInfo['Type']), 'text') === false) {
                $pdo->exec("ALTER TABLE pengajuan_pks MODIFY COLUMN rekomendasi_legal TEXT NULL");
            }
            $colInfo = $pdo->query("SHOW COLUMNS FROM pengajuan_pks LIKE 'file_path'")->fetch();
            if ($colInfo && strpos(strtolower($colInfo['Type']), 'text') === false) {
                $pdo->exec("ALTER TABLE pengajuan_pks MODIFY COLUMN file_path TEXT NULL");
            }
            
            // Ensure penanggung_jawab column exists on dokumen_regulasi table
            $colExists = $pdo->query("SHOW COLUMNS FROM dokumen_regulasi LIKE 'penanggung_jawab'")->rowCount();
            if ($colExists == 0) {
                $pdo->exec("ALTER TABLE dokumen_regulasi ADD COLUMN penanggung_jawab VARCHAR(255) NULL AFTER tanggal_terbit");
            }
            // Ensure file_path on dokumen_regulasi is TEXT (to hold JSON array of multiple paths)
            $colInfo = $pdo->query("SHOW COLUMNS FROM dokumen_regulasi LIKE 'file_path'")->fetch();
            if ($colInfo && strpos(strtolower($colInfo['Type']), 'text') === false) {
                $pdo->exec("ALTER TABLE dokumen_regulasi MODIFY COLUMN file_path TEXT NULL");
            }
            
            // ============================================
            // AUTO CREATE KPI TABLES (SOP & SDM MODULE)
            // ============================================
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kpi_karyawan` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `nik` varchar(50) NOT NULL,
                  `nama` varchar(150) NOT NULL,
                  `unit` varchar(100) NOT NULL,
                  `jabatan` varchar(100) NOT NULL,
                  `status` varchar(50) DEFAULT 'Aktif',
                  `tenaga_medis_id` int(11) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `nik` (`nik`),
                  KEY `tenaga_medis_id` (`tenaga_medis_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            
            // Ensure tenaga_medis_id column exists on kpi_karyawan table (for sync)
            try {
                $colExists = $pdo->query("SHOW COLUMNS FROM kpi_karyawan LIKE 'tenaga_medis_id'")->rowCount();
                if ($colExists == 0) {
                    $pdo->exec("ALTER TABLE kpi_karyawan ADD COLUMN tenaga_medis_id INT NULL AFTER status");
                }
            } catch (Exception $ex) {
                // Silently continue
            }

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kpi_kriteria` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `kategori` varchar(100) NOT NULL,
                  `nama_indikator` varchar(200) NOT NULL,
                  `deskripsi` text,
                  `bobot` int(11) NOT NULL DEFAULT '10',
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kpi_penilaian` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `karyawan_id` int(11) NOT NULL,
                  `bulan` varchar(2) NOT NULL,
                  `tahun` varchar(4) NOT NULL,
                  `total_skor` decimal(5,2) DEFAULT '0.00',
                  `catatan` text,
                  `created_by` varchar(100) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `karyawan_id` (`karyawan_id`),
                  CONSTRAINT `kpi_penilaian_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `kpi_karyawan` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kpi_penilaian_detail` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `penilaian_id` int(11) NOT NULL,
                  `kriteria_id` int(11) NOT NULL,
                  `nilai` decimal(5,2) NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `penilaian_id` (`penilaian_id`),
                  KEY `kriteria_id` (`kriteria_id`),
                  CONSTRAINT `kpi_penilaian_detail_ibfk_1` FOREIGN KEY (`penilaian_id`) REFERENCES `kpi_penilaian` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `kpi_penilaian_detail_ibfk_2` FOREIGN KEY (`kriteria_id`) REFERENCES `kpi_kriteria` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kpi_rkk_template` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `jabatan` varchar(100) NOT NULL,
                  `unit` varchar(100) NOT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kpi_rkk_tugas` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `template_id` int(11) DEFAULT NULL,
                  `tipe_tugas` varchar(50) DEFAULT 'Pokok',
                  `deskripsi` text NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `template_id` (`template_id`),
                  CONSTRAINT `kpi_rkk_tugas_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `kpi_rkk_template` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kpi_rkk_karyawan` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `karyawan_id` int(11) NOT NULL,
                  `tugas_id` int(11) NOT NULL,
                  `status` varchar(50) DEFAULT 'Aktif',
                  PRIMARY KEY (`id`),
                  KEY `karyawan_id` (`karyawan_id`),
                  KEY `tugas_id` (`tugas_id`),
                  CONSTRAINT `kpi_rkk_karyawan_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `kpi_karyawan` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `kpi_rkk_karyawan_ibfk_2` FOREIGN KEY (`tugas_id`) REFERENCES `kpi_rkk_tugas` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // kpi_penilaian_harian — grid nilai per hari per kriteria
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kpi_penilaian_harian` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `karyawan_id` int(11) NOT NULL,
                  `kriteria_id` int(11) NOT NULL,
                  `hari` tinyint(2) NOT NULL COMMENT '1-31',
                  `bulan` tinyint(2) NOT NULL COMMENT '1-12',
                  `tahun` smallint(4) NOT NULL,
                  `nilai` varchar(10) DEFAULT NULL,
                  `created_by` varchar(100) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_harian` (`karyawan_id`,`kriteria_id`,`hari`,`bulan`,`tahun`),
                  KEY `idx_karyawan_periode` (`karyawan_id`,`bulan`,`tahun`),
                  KEY `idx_kriteria` (`kriteria_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Tambah kolom user_id di kpi_karyawan jika belum ada (link ke akun login)
            try {
                $colExists = $pdo->query("SHOW COLUMNS FROM `kpi_karyawan` LIKE 'user_id'")->rowCount();
                if ($colExists == 0) {
                    $pdo->exec("ALTER TABLE `kpi_karyawan` ADD COLUMN `user_id` INT DEFAULT NULL AFTER `tenaga_medis_id`");
                }
            } catch (Exception $ex) { /* skip */ }

            // Tambah kolom atasan_id di kpi_karyawan jika belum ada
            try {
                $colExists = $pdo->query("SHOW COLUMNS FROM `kpi_karyawan` LIKE 'atasan_id'")->rowCount();
                if ($colExists == 0) {
                    $pdo->exec("ALTER TABLE `kpi_karyawan` ADD COLUMN `atasan_id` INT DEFAULT NULL AFTER `user_id`");
                }
            } catch (Exception $ex) { /* skip */ }
        } catch (Exception $ex) {
            // Silently continue if table not created yet
        }
    }
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}