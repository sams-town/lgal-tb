<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
$current_page = basename($_SERVER['PHP_SELF']);
$page_param = isset($_GET['page']) ? $_GET['page'] : '';
$type_param = isset($_GET['type']) ? $_GET['type'] : '';
?>
<!-- Fonts & Icons Library -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
    body {
        font-family: 'Plus Jakarta Sans', sans-serif !important;
    }
</style>

<!-- Sidebar -->
<aside class="w-64 bg-gradient-to-b from-emerald-850 to-emerald-950 text-white shadow-xl h-screen sticky top-0 overflow-y-auto flex-shrink-0">
    <div class="p-6">
        <div class="flex flex-col items-center gap-3">
            <img src="assets/logo.png" alt="Logo RS Taman Harapan Baru" class="w-36 h-auto drop-shadow-md">
            <div class="text-center">
                <h1 class="text-base font-extrabold tracking-wide">RS. Taman Harapan Baru</h1>
                <p class="text-xs text-emerald-300 font-medium">Legal & Corporate Secretary</p>
            </div>
        </div>
    </div>
    
    <nav class="p-4 space-y-2">
        <!-- Dashboard -->
        <?php if (hasPermission('dashboard_view')): ?>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'dashboard.php' ? 'bg-emerald-700/80 text-white font-semibold shadow-md translate-x-1' : 'text-emerald-100 hover:bg-emerald-700/40 hover:translate-x-1'; ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            <span>Dashboard</span>
        </a>
        <?php endif; ?>

        <!-- Legal -->
        <?php if (hasPermission('legal_view')): ?>
            <?php 
            $is_legal_active = in_array($current_page, ['pks.php', 'legal-arsip.php', 'regulasi.php', 'perizinan.php']); 
            ?>
            <div class="space-y-1">
                <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $is_legal_active ? 'bg-emerald-900/40 text-white font-medium border-l-4 border-emerald-400' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="scale" class="w-5 h-5"></i>
                        <span>Legal</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200 <?php echo $is_legal_active ? 'rotate-180' : ''; ?>"></i>
                </button>
                <div class="ml-2 pl-2 border-l border-emerald-800/60 space-y-1 py-1">
                    <a href="pks.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'pks.php') ? 'bg-emerald-700 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40 hover:pl-5'; ?>">
                        Perjanjian Kerjasama (PKS)
                    </a>
                    <a href="legal-arsip.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'legal-arsip.php') ? 'bg-emerald-700 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40 hover:pl-5'; ?>">
                        Arsip PKS
                    </a>
                    <a href="regulasi.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'regulasi.php') ? 'bg-emerald-700 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40 hover:pl-5'; ?>">
                        Regulasi
                    </a>
                    <a href="perizinan.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'perizinan.php') ? 'bg-emerald-700 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40 hover:pl-5'; ?>">
                        Perizinan
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Sekretariat -->
        <?php if (hasPermission('sekretariat_view')): ?>
            <?php 
            $is_sekretariat_active = in_array($current_page, ['surat-masuk.php', 'surat-keluar.php']); 
            ?>
            <div class="space-y-1">
                <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $is_sekretariat_active ? 'bg-emerald-900/40 text-white font-medium border-l-4 border-emerald-400' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="mail" class="w-5 h-5"></i>
                        <span>Sekretariat</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200 <?php echo $is_sekretariat_active ? 'rotate-180' : ''; ?>"></i>
                </button>
                <div class="ml-2 pl-2 border-l border-emerald-800/60 space-y-1 py-1">
                    <a href="surat-masuk.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'surat-masuk.php') ? 'bg-emerald-700 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40 hover:pl-5'; ?>">
                        Surat Masuk
                    </a>
                    <a href="surat-keluar.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'surat-keluar.php') ? 'bg-emerald-700 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40 hover:pl-5'; ?>">
                        Surat Keluar
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Akreditasi & Mutu -->
        <?php if (hasPermission('akreditasi_view')): ?>
        <a href="akreditasi.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'akreditasi.php' ? 'bg-emerald-700/80 text-white font-semibold shadow-md translate-x-1' : 'text-emerald-100 hover:bg-emerald-700/40 hover:translate-x-1'; ?>">
            <i data-lucide="award" class="w-5 h-5"></i>
            <span>Akreditasi & Mutu</span>
        </a>
        <?php endif; ?>

        <!-- Persetujuan & E-Sign -->
        <?php if (hasPermission('approval_view')): ?>
        <a href="approval.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'approval.php' ? 'bg-emerald-700/80 text-white font-semibold shadow-md translate-x-1' : 'text-emerald-100 hover:bg-emerald-700/40 hover:translate-x-1'; ?>">
            <i data-lucide="pen-tool" class="w-5 h-5"></i>
            <span>Persetujuan & E-Sign</span>
        </a>
        <?php endif; ?>

        <!-- SOP & SDM -->
        <?php if (hasPermission('sop_view')): ?>
        <a href="sop.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'sop.php' ? 'bg-emerald-700/80 text-white font-semibold shadow-md translate-x-1' : 'text-emerald-100 hover:bg-emerald-700/40 hover:translate-x-1'; ?>">
            <i data-lucide="book-open" class="w-5 h-5"></i>
            <span>SOP & SDM</span>
        </a>
        <?php endif; ?>

        <!-- Komite / Tenaga Medis -->
        <?php if (hasPermission('komite_view')): ?>
            <?php 
            $is_komite_active = in_array($current_page, ['komite-medik.php', 'komite-keperawatan.php', 'komite-tenaga-kesehatan-lainnya.php']); 
            ?>
            <div class="space-y-1">
                <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $is_komite_active ? 'bg-emerald-900/40 text-white font-medium border-l-4 border-emerald-400' : 'text-emerald-100 hover:bg-emerald-700/40'; ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="users" class="w-5 h-5"></i>
                        <span>Komite</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200 <?php echo $is_komite_active ? 'rotate-180' : ''; ?>"></i>
                </button>
                <div class="ml-2 pl-2 border-l border-emerald-800/60 space-y-1 py-1">
                    <a href="komite-medik.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'komite-medik.php') ? 'bg-emerald-700 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40 hover:pl-5'; ?>">
                        Komite Medik
                    </a>
                    <a href="komite-keperawatan.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'komite-keperawatan.php') ? 'bg-emerald-700 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40 hover:pl-5'; ?>">
                        Komite Keperawatan
                    </a>
                    <a href="komite-tenaga-kesehatan-lainnya.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'komite-tenaga-kesehatan-lainnya.php') ? 'bg-emerald-700 text-white font-semibold shadow-sm' : 'text-emerald-100 hover:bg-emerald-700/40 hover:pl-5'; ?>">
                        Komite Kesehatan Lainnya
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Corporate Secretary -->
        <?php if (hasPermission('corsec_view')): ?>
        <a href="corsec.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'corsec.php' ? 'bg-emerald-700/80 text-white font-semibold shadow-md translate-x-1' : 'text-emerald-100 hover:bg-emerald-700/40 hover:translate-x-1'; ?>">
            <i data-lucide="building" class="w-5 h-5"></i>
            <span>Corporate Secretary</span>
        </a>
        <?php endif; ?>

        <!-- Audit Trail -->
        <?php if (hasPermission('audit_view')): ?>
        <a href="audit_trail.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'audit_trail.php' ? 'bg-emerald-700/80 text-white font-semibold shadow-md translate-x-1' : 'text-emerald-100 hover:bg-emerald-700/40 hover:translate-x-1'; ?>">
            <i data-lucide="activity" class="w-5 h-5"></i>
            <span>Audit Trail</span>
        </a>
        <?php endif; ?>

        <!-- Pengaturan -->
        <?php if (($_SESSION['user']['nama_role'] ?? $_SESSION['user']['role'] ?? '') === 'Super Admin'): ?>
        <a href="setting.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'setting.php' ? 'bg-emerald-700/80 text-white font-semibold shadow-md translate-x-1' : 'text-emerald-100 hover:bg-emerald-700/40 hover:translate-x-1'; ?>">
            <i data-lucide="settings" class="w-5 h-5"></i>
            <span>Pengaturan</span>
        </a>
        <?php endif; ?>
    </nav>
</aside>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        lucide.createIcons();
    });
</script>

