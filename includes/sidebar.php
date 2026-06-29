<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$page_param = isset($_GET['page']) ? $_GET['page'] : '';
$type_param = isset($_GET['type']) ? $_GET['type'] : '';
?>
<!-- Sidebar -->
<aside class="w-64 bg-gradient-to-b from-emerald-800 to-emerald-950 text-white shadow-xl h-screen sticky top-0 overflow-y-auto flex-shrink-0">
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
        <?php 
        $is_legal_active = in_array($current_page, ['pks.php', 'legal-arsip.php', 'regulasi.php', 'perizinan.php']); 
        ?>
        <div class="space-y-1">
            <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $is_legal_active ? 'bg-emerald-900/50 text-white font-medium border-l-4 border-emerald-400' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
                <div class="flex items-center gap-3">
                    <span class="text-xl">📑</span>
                    <span>Legal</span>
                </div>
                <span class="text-xs">▼</span>
            </button>
            <div class="ml-2 pl-2 border-l border-emerald-700/40 space-y-1 py-1">
                <a href="pks.php" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'pks.php') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    Perjanjian Kerjasama (PKS)
                </a>
                <a href="legal-arsip.php" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'legal-arsip.php') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Arsip PKS
                </a>
                <a href="regulasi.php" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'regulasi.php') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Regulasi
                </a>
                <a href="perizinan.php" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'perizinan.php') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Perizinan
                </a>
            </div>
        </div>

        <!-- Sekretariat -->
        <?php 
        $is_sekretariat_active = in_array($current_page, ['surat-masuk.php', 'surat-keluar.php']); 
        ?>
        <div class="space-y-1">
            <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $is_sekretariat_active ? 'bg-emerald-900/50 text-white font-medium border-l-4 border-emerald-400' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
                <div class="flex items-center gap-3">
                    <span class="text-xl">✉️</span>
                    <span>Sekretariat</span>
                </div>
                <span class="text-xs">▼</span>
            </button>
            <div class="ml-2 pl-2 border-l border-emerald-700/40 space-y-1 py-1">
                <a href="surat-masuk.php" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'surat-masuk.php') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    Surat Masuk
                </a>
                <a href="surat-keluar.php" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'surat-keluar.php') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
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
        <?php 
        $is_komite_active = in_array($current_page, ['komite-medik.php', 'komite-keperawatan.php', 'komite-tenaga-kesehatan-lainnya.php']); 
        ?>
        <div class="space-y-1">
            <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $is_komite_active ? 'bg-emerald-900/50 text-white font-medium border-l-4 border-emerald-400' : 'text-emerald-100 hover:bg-emerald-700/60'; ?>">
                <div class="flex items-center gap-3">
                    <span class="text-xl">👨‍⚕️</span>
                    <span>Komite</span>
                </div>
                <span class="text-xs">▼</span>
            </button>
            <div class="ml-2 pl-2 border-l border-emerald-700/40 space-y-1 py-1">
                <a href="komite-medik.php" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'komite-medik.php') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Komite Medik
                </a>
                <a href="komite-keperawatan.php" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'komite-keperawatan.php') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Komite Keperawatan
                </a>
                <a href="komite-tenaga-kesehatan-lainnya.php" class="block px-4 py-2 rounded-lg text-sm transition-colors <?php echo ($current_page === 'komite-tenaga-kesehatan-lainnya.php') ? 'bg-emerald-600 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    › Komite Kesehatan Lainnya
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

