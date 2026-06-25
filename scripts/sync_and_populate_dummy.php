<?php
require_once __DIR__ . '/../config/database.php';

echo "=== SYNCHRONIZING DATABASE SCHEMAS & SEEDING REALISTIC DUMMY DATA ===\n\n";

try {
    // Disable foreign key checks for clean drop/delete
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Rebuild roles if they do not exist
    echo "• Rebuilding roles...\n";
    $pdo->exec("DELETE FROM roles");
    $stmtRole = $pdo->prepare("INSERT INTO roles (id, nama_role, deskripsi, permissions) VALUES (?, ?, ?, ?)");
    $stmtRole->execute([1, 'Super Admin', 'Super Admin IT dengan semua akses', json_encode(['dashboard', 'surat', 'sop', 'pks', 'settings'])]);
    $stmtRole->execute([2, 'Staf Legal', 'Mengelola PKS dan Dokumen Hukum', json_encode(['dashboard', 'pks', 'settings'])]);
    $stmtRole->execute([3, 'Staf Sekretariat', 'Mengelola Surat Masuk dan Keluar', json_encode(['dashboard', 'surat'])]);
    $stmtRole->execute([4, 'Direktur Keuangan', 'Melakukan review keuangan dan PKS', json_encode(['dashboard', 'pks'])]);
    $stmtRole->execute([5, 'Direktur Utama', 'Menyetujui regulasi dan PKS tingkat tinggi', json_encode(['dashboard', 'sop', 'pks'])]);

    // 2. Rebuild divisi (divisions)
    echo "• Rebuilding divisi...\n";
    $pdo->exec("DELETE FROM divisi");
    $stmtDiv = $pdo->prepare("INSERT INTO divisi (id, nama_divisi) VALUES (?, ?)");
    $stmtDiv->execute([1, 'Legal & Hukum']);
    $stmtDiv->execute([2, 'Komite Medik']);
    $stmtDiv->execute([3, 'Keperawatan']);
    $stmtDiv->execute([4, 'Sekretariat']);
    $stmtDiv->execute([5, 'Keuangan & Perencanaan']);

    // 3. Rebuild users
    echo "• Rebuilding users...\n";
    $pdo->exec("DELETE FROM users");
    
    // Hash password 'password' for testing
    $hashedPassword = password_hash('password', PASSWORD_BCRYPT);
    
    $stmtUser = $pdo->prepare("INSERT INTO users (id, nama, email, password, no_hp, pin, role_id, division_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmtUser->execute([1, 'Irsad Super Admin', 'irsad@thb.id', $hashedPassword, '081234567890', '000000', 1, 1]); // Super Admin (KM)
    $stmtUser->execute([2, 'Sarah Amalia', 'sarah@thb.id', $hashedPassword, '081234567891', '222222', 2, 1]);       // Staf Legal (LG)
    $stmtUser->execute([3, 'Bambang Wijaya', 'bambang@thb.id', $hashedPassword, '081234567892', '123456', 3, 4]);   // Staf Sekretariat (SK)
    $stmtUser->execute([4, 'Rian Pratama', 'rian@thb.id', $hashedPassword, '081234567893', '222222', 4, 5]);       // Direktur Keuangan (DK)
    $stmtUser->execute([5, 'Dr. Hendra Kusuma', 'hendra@thb.id', $hashedPassword, '081234567894', '111111', 5, 2]); // Direktur Utama (DU)

    // 4. Drop and Recreate tenaga_medis with CORRECT schema for the application
    echo "• Dropping and recreating table 'tenaga_medis'...\n";
    $pdo->exec("DROP TABLE IF EXISTS tenaga_medis");
    $pdo->exec("
        CREATE TABLE tenaga_medis (
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

    // Start transaction for DML inserts
    $pdo->beginTransaction();

    // Insert dummy tenaga_medis
    // Record 1: SIP expiring in 15 days (H-30)
    // Record 2: STR/SK expiring in 45 days (H-60)
    $stmtMedis = $pdo->prepare("
        INSERT INTO tenaga_medis (nama_lengkap, unit_ruangan, status_kepegawaian, tipe_form, masa_berlaku_sip_akhir, masa_berlaku_sk_akhir)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtMedis->execute(['Dr. Budi Utomo Sp.PD', 'Rawat Jalan', 'Tetap', 'komite-medik', date('Y-m-d', strtotime('+15 days')), date('Y-m-d', strtotime('+120 days'))]);
    $stmtMedis->execute(['Nurse Amira Amran', 'Rawat Inap', 'Kontrak', 'komite-keperawatan', date('Y-m-d', strtotime('+90 days')), date('Y-m-d', strtotime('+45 days'))]);
    $stmtMedis->execute(['Apoteker Faisal', 'Apotek', 'Tetap', 'komite-nakes', date('Y-m-d', strtotime('+200 days')), date('Y-m-d', strtotime('+300 days'))]);

    // 5. Seeding dokumen_legal
    echo "• Seeding dokumen_legal...\n";
    $pdo->exec("DELETE FROM dokumen_legal");
    $stmtLegal = $pdo->prepare("INSERT INTO dokumen_legal (kategori, nama_dokumen, sub_kategori, tanggal, status, file_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtLegal->execute(['Kontrak', 'Perjanjian Vendor IT Support', 'Vendor', date('Y-m-d', strtotime('-15 days')), 'Publish/Aktif', 'uploads/legal/IT_support_pks.pdf']);
    $stmtLegal->execute(['Perizinan', 'Izin Operasional Genset Utama', 'Genset', date('Y-m-d', strtotime('-30 days')), 'Publish/Aktif', 'uploads/legal/izin_genset.pdf']);
    $stmtLegal->execute(['Regulasi', 'Peraturan Kebijakan Parkir RS', 'Internal', date('Y-m-d', strtotime('-5 days')), 'Publish/Aktif', 'uploads/legal/kebijakan_parkir.pdf']);
    $stmtLegal->execute(['SOP', 'SOP Pelayanan Resepsionis', 'Pelayanan', date('Y-m-d', strtotime('-2 days')), 'Publish/Aktif', 'uploads/legal/sop_resepsionis.pdf']);
    
    // 6. Seeding dokumen_akreditasi
    echo "• Seeding dokumen_akreditasi...\n";
    $pdo->exec("DELETE FROM dokumen_akreditasi");
    $stmtAkreditasi = $pdo->prepare("INSERT INTO dokumen_akreditasi (bab, nama_dokumen, kode_ep, tanggal_review, target_capaian, status_pemenuhan, file_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtAkreditasi->execute(['HP', 'Panduan Hak dan Kewajiban Pasien', 'HPK 1.1 EP 1', '2026-06-10', 100, 'Sudah Terpenuhi', 'Ada']);
    $stmtAkreditasi->execute(['PP', 'SOP Cuci Tangan 6 Langkah', 'PPI 2 EP 2', '2026-06-12', 100, 'Sudah Terpenuhi', 'Ada']);
    $stmtAkreditasi->execute(['KK', 'Pemberian Kewenangan Klinis Medis', 'KKS 10 EP 3', '2026-06-15', 100, 'Sudah Terpenuhi', 'Ada']);
    $stmtAkreditasi->execute(['SK', 'Prosedur Identifikasi Pasien', 'SKP 1 EP 1', '2026-06-20', 100, 'Dalam Review', 'Ada']);
    $stmtAkreditasi->execute(['PA', 'Panduan Pelayanan Pasien Tahap Terminal', 'PAP 5 EP 2', '2026-06-22', 100, 'Belum Lengkap', 'Tidak Ada']);

    // 7. Seeding manajemen_surat (Surat Masuk & Keluar)
    echo "• Seeding manajemen_surat...\n";
    $pdo->exec("DELETE FROM manajemen_surat");
    $stmtSurat = $pdo->prepare("INSERT INTO manajemen_surat (nomor_surat, kategori, asal_pengirim, perihal, tanggal_surat, status_tindak_lanjut) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtSurat->execute(['001/DINKES/VI/2026', 'Surat Masuk', 'Dinas Kesehatan Kota', 'Undangan Rapat Koordinasi Vaksinasi', date('Y-m-d'), 'Pending']);
    $stmtSurat->execute(['002/BPJS/VI/2026', 'Surat Masuk', 'BPJS Kesehatan Cabang', 'Pemberitahuan Rekonsiliasi Klaim', date('Y-m-d', strtotime('-3 days')), 'Selesai']);
    $stmtSurat->execute(['003/RS-THB/VI/2026', 'Surat Keluar', 'RS Taman Harapan Baru', 'Balasan Surat Permohonan Magang', date('Y-m-d', strtotime('-1 days')), 'Selesai']);
    
    // 8. Seeding dokumen_corsec (KPI Direksi & Risk Management)
    echo "• Seeding dokumen_corsec...\n";
    $pdo->exec("DELETE FROM dokumen_corsec");
    $stmtCorsec = $pdo->prepare("INSERT INTO dokumen_corsec (judul, nomor_dokumen, kategori, tanggal_terbit) VALUES (?, ?, ?, ?)");
    $stmtCorsec->execute(['KPI Direksi TH 2026', 'KPI-DIR-2026', 'KPI Direksi', '2026-01-01']);
    $stmtCorsec->execute(['Laporan Risiko Operasional Q1', 'RM-REP-Q1-2026', 'Risk Management', '2026-04-10']);

    // 9. Seeding approval_documents
    echo "• Seeding approval_documents...\n";
    $pdo->exec("DELETE FROM approval_documents");
    $initialStepStatus = json_encode([
        'km' => 'pending',
        'lg' => 'pending',
        'sk' => 'pending',
        'dk' => 'pending',
        'du' => 'pending'
    ]);
    $stmtApprove = $pdo->prepare("INSERT INTO approval_documents (id, name, proposer, date, step_status, division_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtApprove->execute([1, 'Perjanjian Kerjasama Klinik Sehat Bersama', 'Sarah Amalia', date('Y-m-d'), $initialStepStatus, 1]);
    $stmtApprove->execute([2, 'SOP Tindakan Resusitasi Jantung', 'Dr. Budi Utomo', date('Y-m-d', strtotime('-2 days')), $initialStepStatus, 2]);

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $pdo->commit();
    echo "\n✓ Semua data berhasil disinkronisasikan dan dimasukkan dengan sukses!\n";

} catch (Exception $e) {
    // Re-enable foreign key checks in case of error
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "\n✗ Sinkronisasi Gagal. Error detail: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
?>
