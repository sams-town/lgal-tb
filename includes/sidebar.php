<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$page_param = isset($_GET['page']) ? $_GET['page'] : '';
$type_param = isset($_GET['type']) ? $_GET['type'] : '';
?>
<!-- Sidebar -->
<aside class="w-64 bg-gradient-to-b from-emerald-800 to-emerald-950 text-white shadow-xl min-h-screen">
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
        <!-- Dashboard -->
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'dashboard.php' ? 'bg-emerald-700 text-white font-semibold shadow-inner' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
            <span class="text-xl">📊</span>
            <span>Dashboard</span>
        </a>

        <!-- Legal -->
        <div class="space-y-1">
            <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'legal.php' ? 'bg-emerald-900/50 text-white font-medium border-l-4 border-emerald-400' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
                <div class="flex items-center gap-3">
                    <span class="text-xl">📑</span>
                    <span>Legal</span>
                </div>
                <span class="text-xs">▼</span>
            </button>
            <div class="ml-2 pl-2 border-l border-emerald-700/40 space-y-1 py-1">
                <a href="legal.php?page=pks" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'legal.php' && ($page_param === 'pks' || $page_param === '')) ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    Perjanjian Kerjasama (PKS)
                </a>
                <a href="legal.php?page=regulasi" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'legal.php' && $page_param === 'regulasi') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Regulasi
                </a>
                <a href="legal.php?page=perizinan" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'legal.php' && $page_param === 'perizinan') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Perizinan
                </a>
                <a href="legal.php?page=legal-arsip" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'legal.php' && $page_param === 'legal-arsip') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Arsip Dokumen
                </a>
            </div>
        </div>

        <!-- Sekretariat -->
        <div class="space-y-1">
            <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'sekretariat.php' ? 'bg-emerald-900/50 text-white font-medium border-l-4 border-emerald-400' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
                <div class="flex items-center gap-3">
                    <span class="text-xl">✉️</span>
                    <span>Sekretariat</span>
                </div>
                <span class="text-xs">▼</span>
            </button>
            <div class="ml-2 pl-2 border-l border-emerald-700/40 space-y-1 py-1">
                <a href="sekretariat.php?type=surat-masuk" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'sekretariat.php' && ($type_param === 'surat-masuk' || $type_param === '')) ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    Surat Masuk
                </a>
                <a href="sekretariat.php?type=surat-keluar" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'sekretariat.php' && $type_param === 'surat-keluar') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    Surat Keluar
                </a>
            </div>
        </div>

        <!-- Akreditasi & Mutu -->
        <a href="akreditasi.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'akreditasi.php' ? 'bg-emerald-700 text-white font-semibold shadow-inner' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
            <span class="text-xl">🏅</span>
            <span>Akreditasi & Mutu</span>
        </a>

        <!-- Persetujuan & E-Sign -->
        <a href="approval.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'approval.php' ? 'bg-emerald-700 text-white font-semibold shadow-inner' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
            <span class="text-xl">✍️</span>
            <span>Persetujuan & E-Sign</span>
        </a>

        <!-- SOP & SDM -->
        <a href="sop.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'sop.php' ? 'bg-emerald-700 text-white font-semibold shadow-inner' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
            <span class="text-xl">📚</span>
            <span>SOP & SDM</span>
        </a>

        <!-- Komite / Tenaga Medis -->
        <div class="space-y-1">
            <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'tenaga_medis.php' ? 'bg-emerald-900/50 text-white font-medium border-l-4 border-emerald-400' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
                <div class="flex items-center gap-3">
                    <span class="text-xl">👨‍⚕️</span>
                    <span>Komite</span>
                </div>
                <span class="text-xs">▼</span>
            </button>
            <div class="ml-2 pl-2 border-l border-emerald-700/40 space-y-1 py-1">
                <a href="tenaga_medis.php?page=komite-medik" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'tenaga_medis.php' && ($page_param === 'komite-medik' || $page_param === '')) ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Komite Medik
                </a>
                <a href="tenaga_medis.php?page=komite-keperawatan" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'tenaga_medis.php' && $page_param === 'komite-keperawatan') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Komite Keperawatan
                </a>
                <a href="tenaga_medis.php?page=komite-nakes" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'tenaga_medis.php' && $page_param === 'komite-nakes') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Komite Nakes
                </a>
                <a href="tenaga_medis.php?page=komite-tenaga-kesehatan-lainnya" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'tenaga_medis.php' && $page_param === 'komite-tenaga-kesehatan-lainnya') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Komite Tenaga Kesehatan Lainnya
                </a>
            </div>
        </div>

        <!-- Corporate Secretary -->
        <a href="corsec.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'corsec.php' ? 'bg-emerald-700 text-white font-semibold shadow-inner' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
            <span class="text-xl">🏛️</span>
            <span>Corporate Secretary</span>
        </a>

        <!-- Audit Trail -->
        <a href="audit_trail.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'audit_trail.php' ? 'bg-emerald-700 text-white font-semibold shadow-inner' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
            <span class="text-xl">🔍</span>
            <span>Audit Trail</span>
        </a>

        <!-- Pengaturan -->
        <a href="setting.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $current_page === 'setting.php' ? 'bg-emerald-700 text-white font-semibold shadow-inner' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
            <span class="text-xl">⚙️</span>
            <span>Pengaturan</span>
        </a>
    </nav>
</aside>
