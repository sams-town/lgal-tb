<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<aside class="w-64 bg-gradient-to-b from-emerald-800 to-emerald-900 text-white shadow-xl">
    <div class="p-6">
        <div class="flex flex-col items-center gap-3">
            <img src="assets/logo.png" alt="Logo RS Taman Harapan Baru" class="w-40 h-auto">
            <div class="text-center">
                <h1 class="text-lg font-bold">RS. Taman Harapan Baru</h1>
                <p class="text-xs text-emerald-200">Legal & Corporate Secretary</p>
            </div>
        </div>
    </div>
    
    <nav class="p-4 space-y-2">
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $current_page === 'dashboard.php' ? 'bg-emerald-700' : ''; ?>">
            <span class="text-xl">📊</span>
            <span>Dashboard</span>
        </a>
        <div class="space-y-1">
            <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $current_page === 'legal.php' ? 'bg-emerald-700' : ''; ?>">
                <div class="flex items-center gap-3">
                    <span class="text-xl">📑</span>
                    <span>Legal</span>
                </div>
                <span>▼</span>
            </button>
            <div class="ml-4 space-y-1">
                <a href="legal.php?page=pks" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    Perjanjian Kerjasama (PKS)
                </a>
                <a href="legal.php?page=regulasi" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    › Regulasi
                </a>
                <a href="legal.php?page=perizinan" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    › Perizinan
                </a>
                <a href="legal.php?page=legal-arsip" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    › Arsip Dokumen
                </a>
            </div>
        </div>
        <div class="space-y-1">
            <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <div class="flex items-center gap-3">
                    <span class="text-xl">✉️</span>
                    <span>Sekretariat</span>
                </div>
                <span>▼</span>
            </button>
            <div class="ml-4 space-y-1">
                <a href="surat-masuk.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    Surat Masuk
                </a>
                <a href="surat-keluar.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    Surat Keluar
                </a>
            </div>
        </div>
        <a href="akreditasi.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $current_page === 'akreditasi.php' ? 'bg-emerald-700' : ''; ?>">
            <span class="text-xl">🏅</span>
            <span>Akreditasi & Mutu</span>
        </a>
        <a href="approval.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $current_page === 'approval.php' ? 'bg-emerald-700' : ''; ?>">
            <span class="text-xl">✍️</span>
            <span>Persetujuan & E-Sign</span>
        </a>
        <a href="sop.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $current_page === 'sop.php' ? 'bg-emerald-700' : ''; ?>">
            <span class="text-xl">📚</span>
            <span>SOP & SDM</span>
        </a>
        <div class="space-y-1">
            <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $current_page === 'tenaga_medis.php' ? 'bg-emerald-700' : ''; ?>">
                <div class="flex items-center gap-3">
                    <span class="text-xl">👨‍⚕️</span>
                    <span>Komite</span>
                </div>
                <span>▼</span>
            </button>
            <div class="ml-4 space-y-1">
                <a href="tenaga_medis.php?page=komite-medik" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    › Komite Medik
                </a>
                <a href="tenaga_medis.php?page=komite-keperawatan" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    › Komite Keperawatan
                </a>
                <a href="tenaga_medis.php?page=komite-nakes" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    › Komite Nakes
                </a>
                <a href="tenaga_medis.php?page=komite-tenaga-kesehatan-lainnya" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                    › Komite Tenaga Kesehatan Lainnya
                </a>
            </div>
        </div>
        <a href="corsec.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $current_page === 'corsec.php' ? 'bg-emerald-700' : ''; ?>">
            <span class="text-xl">🏛️</span>
            <span>Corporate Secretary</span>
        </a>
        <a href="audit_trail.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $current_page === 'audit_trail.php' ? 'bg-emerald-700' : ''; ?>">
            <span class="text-xl">🔍</span>
            <span>Audit Trail</span>
        </a>
        <a href="setting.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700 <?php echo $current_page === 'setting.php' ? 'bg-emerald-700' : ''; ?>">
            <span class="text-xl">⚙️</span>
            <span>Pengaturan</span>
        </a>
    </nav>
</aside>
