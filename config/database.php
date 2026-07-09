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
        } catch (Exception $ex) {
            // Silently continue if table not created yet
        }
    }
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}