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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
    /* Theme global overrides */
    body {
        font-family: 'Inter', sans-serif !important;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%) !important;
        color: #2d3748 !important;
    }
    
    /* Modernizing buttons */
    a, button {
        border-radius: 12px !important;
    }
    
    /* Form inputs styling */
    input[type="text"], input[type="email"], input[type="password"], input[type="search"], select, textarea {
        border-radius: 12px !important;
        border: 1px solid #e2e8f0 !important;
        padding: 8px 12px !important;
        font-size: 14px !important;
        transition: all 0.2s ease-in-out !important;
        outline: none !important;
        color: #2d3748 !important;
    }
    input:focus, select:focus, textarea:focus {
        border-color: #0d9488 !important; /* teal-600 */
        box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15) !important;
    }
    
    /* Table modern styling */
    table {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        width: 100% !important;
    }
    thead th {
        background-color: #f8fafc !important;
        color: #475569 !important;
        font-weight: 700 !important;
        font-size: 11px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        border-bottom: 2px solid #f1f5f9 !important;
        padding: 14px 24px !important;
    }
    tbody td {
        padding: 16px 24px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        color: #2d3748 !important;
        font-size: 13.5px !important;
    }
    tbody tr:hover {
        background-color: #f8fafc !important;
    }
    
    /* Cards styling with diffused shadow */
    .card, [class*="bg-white"][class*="rounded-2xl"] {
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.02), 0 8px 10px -6px rgba(0, 0, 0, 0.02) !important;
    }
    
    /* Modal styling */
    [class*="bg-white"][class*="rounded-2xl"][class*="max-w-"] {
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.04) !important;
        border-radius: 20px !important;
    }
</style>

<!-- Sidebar -->
<aside class="w-64 bg-slate-900 text-white shadow-xl h-screen sticky top-0 overflow-y-auto flex-shrink-0">
    <div class="p-5">
        <div class="flex flex-col items-center gap-2 bg-slate-800/40 p-4 rounded-2xl border border-slate-700/30">
            <img src="assets/logo.png" alt="Logo RS Taman Harapan Baru" class="w-24 h-auto drop-shadow-md">
            <div class="text-center mt-1">
                <h1 class="text-sm font-extrabold tracking-wide text-white">RS. Taman Harapan Baru</h1>
                <p class="text-[10px] text-slate-400 font-semibold tracking-wider uppercase">Legal & Corporate Secretary</p>
            </div>
        </div>
    </div>
    
    <nav class="p-4 space-y-2">
        <!-- Dashboard -->
        <?php if (hasPermission('dashboard_view')): ?>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'dashboard.php' ? 'bg-teal-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
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
                <button onclick="toggleAccordion('legal-submenu', this)" class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $is_legal_active ? 'bg-slate-800/60 text-white font-medium' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="scale" class="w-5 h-5"></i>
                        <span>Legal</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 arrow-icon transition-transform duration-300 <?php echo $is_legal_active ? 'rotate-180' : ''; ?>"></i>
                </button>
                <div id="legal-submenu" class="<?php echo $is_legal_active ? '' : 'hidden'; ?> space-y-1 py-1 pl-8">
                    <a href="pks.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'pks.php') ? 'bg-teal-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                        Perjanjian Kerjasama (PKS)
                    </a>
                    <a href="legal-arsip.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'legal-arsip.php') ? 'bg-teal-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                        Arsip PKS
                    </a>
                    <a href="regulasi.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'regulasi.php') ? 'bg-teal-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                        Regulasi
                    </a>
                    <a href="perizinan.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'perizinan.php') ? 'bg-teal-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
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
                <button onclick="toggleAccordion('sekretariat-submenu', this)" class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $is_sekretariat_active ? 'bg-slate-800/60 text-white font-medium' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="mail" class="w-5 h-5"></i>
                        <span>Sekretariat</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 arrow-icon transition-transform duration-300 <?php echo $is_sekretariat_active ? 'rotate-180' : ''; ?>"></i>
                </button>
                <div id="sekretariat-submenu" class="<?php echo $is_sekretariat_active ? '' : 'hidden'; ?> space-y-1 py-1 pl-8">
                    <a href="surat-masuk.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'surat-masuk.php') ? 'bg-teal-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                        Surat Masuk
                    </a>
                    <a href="surat-keluar.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'surat-keluar.php') ? 'bg-teal-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                        Surat Keluar
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Akreditasi & Mutu -->
        <?php if (hasPermission('akreditasi_view')): ?>
        <a href="akreditasi.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'akreditasi.php' ? 'bg-teal-600 text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            <i data-lucide="award" class="w-5 h-5"></i>
            <span>Akreditasi & Mutu</span>
        </a>
        <?php endif; ?>

        <!-- Persetujuan & E-Sign -->
        <?php if (hasPermission('approval_view')): ?>
        <a href="approval.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'approval.php' ? 'bg-teal-600 text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            <i data-lucide="pen-tool" class="w-5 h-5"></i>
            <span>Persetujuan & E-Sign</span>
        </a>
        <?php endif; ?>

        <!-- SOP & SDM -->
        <?php if (hasPermission('sop_view')): ?>
        <a href="sop.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'sop.php' ? 'bg-teal-600 text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
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
                <button onclick="toggleAccordion('komite-submenu', this)" class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $is_komite_active ? 'bg-slate-800/60 text-white font-medium' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="users" class="w-5 h-5"></i>
                        <span>Komite</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 arrow-icon transition-transform duration-200 <?php echo $is_komite_active ? 'rotate-180' : ''; ?>"></i>
                </button>
                <div id="komite-submenu" class="<?php echo $is_komite_active ? '' : 'hidden'; ?> space-y-1 py-1 pl-8">
                    <a href="komite-medik.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'komite-medik.php') ? 'bg-teal-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                        › Komite Medik
                    </a>
                    <a href="komite-keperawatan.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'komite-keperawatan.php') ? 'bg-teal-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                        › Komite Keperawatan
                    </a>
                    <a href="komite-tenaga-kesehatan-lainnya.php" class="block px-4 py-2 rounded-lg text-sm transition-all <?php echo ($current_page === 'komite-tenaga-kesehatan-lainnya.php') ? 'bg-teal-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                        › Komite Kesehatan Lainnya
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Corporate Secretary -->
        <?php if (hasPermission('corsec_view')): ?>
        <a href="corsec.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'corsec.php' ? 'bg-teal-600 text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            <i data-lucide="building" class="w-5 h-5"></i>
            <span>Corporate Secretary</span>
        </a>
        <?php endif; ?>

        <!-- Audit Trail -->
        <?php if (hasPermission('audit_view')): ?>
        <a href="audit_trail.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'audit_trail.php' ? 'bg-teal-600 text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            <i data-lucide="activity" class="w-5 h-5"></i>
            <span>Audit Trail</span>
        </a>
        <?php endif; ?>

        <!-- Pengaturan -->
        <?php if (($_SESSION['user']['nama_role'] ?? $_SESSION['user']['role'] ?? '') === 'Super Admin'): ?>
        <a href="setting.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $current_page === 'setting.php' ? 'bg-teal-600 text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            <i data-lucide="settings" class="w-5 h-5"></i>
            <span>Pengaturan</span>
        </a>
        <?php endif; ?>
    </nav>
</aside>

<script>
    window.toggleAccordion = function(submenuId, btn) {
        const submenu = document.getElementById(submenuId);
        const arrow = btn.querySelector('.arrow-icon');
        if (submenu) {
            submenu.classList.toggle('hidden');
            if (arrow) {
                arrow.classList.toggle('rotate-180');
            }
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        // 1. Replace emojis in text nodes dynamically
        const walkTextAndReplaceEmoji = (node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                let val = node.nodeValue;
                
                if (val.includes('📊')) {
                    const icon = document.createElement('i');
                    icon.setAttribute('data-lucide', 'download');
                    icon.className = 'w-4 h-4 inline-block mr-1';
                    node.parentNode.insertBefore(icon, node);
                    node.nodeValue = val.replace('📊', '');
                }
                if (val.includes('📤')) {
                    const icon = document.createElement('i');
                    icon.setAttribute('data-lucide', 'upload');
                    icon.className = 'w-4 h-4 inline-block mr-1';
                    node.parentNode.insertBefore(icon, node);
                    node.nodeValue = val.replace('📤', '');
                }
                if (val.includes('📄')) {
                    const icon = document.createElement('i');
                    icon.setAttribute('data-lucide', 'file-text');
                    icon.className = 'w-4 h-4 inline-block mr-1';
                    node.parentNode.insertBefore(icon, node);
                    node.nodeValue = val.replace('📄', '');
                }
                if (val.includes('📥')) {
                    const icon = document.createElement('i');
                    icon.setAttribute('data-lucide', 'download-cloud');
                    icon.className = 'w-4 h-4 inline-block mr-1';
                    node.parentNode.insertBefore(icon, node);
                    node.nodeValue = val.replace('📥', '');
                }
                if (val.includes('⚙️')) {
                    const icon = document.createElement('i');
                    icon.setAttribute('data-lucide', 'settings');
                    icon.className = 'w-4 h-4 inline-block mr-1';
                    node.parentNode.insertBefore(icon, node);
                    node.nodeValue = val.replace('⚙️', '');
                }
                if (val.includes('➕')) {
                    const icon = document.createElement('i');
                    icon.setAttribute('data-lucide', 'plus');
                    icon.className = 'w-4 h-4 inline-block mr-1';
                    node.parentNode.insertBefore(icon, node);
                    node.nodeValue = val.replace('➕', '');
                }
            } else {
                for (let child of Array.from(node.childNodes)) {
                    walkTextAndReplaceEmoji(child);
                }
            }
        };
        walkTextAndReplaceEmoji(document.body);

        // 2. Add icons to common action buttons (Edit, Hapus, Lihat)
        document.querySelectorAll('a, button').forEach(el => {
            let text = el.innerText.trim().toLowerCase();
            
            if (text === 'edit') {
                el.innerHTML = '<i data-lucide="edit-3" class="w-3.5 h-3.5 inline mr-1"></i>Edit';
                el.className = el.className.replace(/bg-blue-100|text-blue-700/g, '') + ' bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 transition-colors px-2.5 py-1 text-xs font-semibold rounded-lg flex items-center gap-1';
            } else if (text === 'hapus') {
                el.innerHTML = '<i data-lucide="trash-2" class="w-3.5 h-3.5 inline mr-1"></i>Hapus';
                el.className = el.className.replace(/bg-red-100|text-red-700/g, '') + ' bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition-colors px-2.5 py-1 text-xs font-semibold rounded-lg flex items-center gap-1';
            } else if (text === 'lihat') {
                el.innerHTML = '<i data-lucide="eye" class="w-3.5 h-3.5 inline mr-1"></i>Lihat';
                el.className = el.className.replace(/bg-gray-150|bg-emerald-100|text-emerald-700/g, '') + ' bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100 transition-colors px-2.5 py-1 text-xs font-semibold rounded-lg flex items-center gap-1';
            } else if (text.includes('download template')) {
                el.innerHTML = '<i data-lucide="download" class="w-4 h-4 inline mr-1"></i>Download Template';
            } else if (text.includes('formulir pengajuan')) {
                el.innerHTML = '<i data-lucide="file-plus" class="w-4 h-4 inline mr-1"></i>FORMULIR PENGAJUAN';
            }
        });

        // 3. Modernize stat card emoji icons
        document.querySelectorAll('[class*="w-16"][class*="h-16"]').forEach(el => {
            let text = el.innerText.trim();
            if (text === '📄') {
                el.innerHTML = '<i data-lucide="file-text" class="w-8 h-8 text-white"></i>';
                el.className = el.className.replace('text-3xl', '') + ' bg-gradient-to-br from-indigo-500 to-indigo-600 shadow-md';
            } else if (text === '⚠️') {
                el.innerHTML = '<i data-lucide="alert-triangle" class="w-8 h-8 text-white"></i>';
                el.className = el.className.replace('text-3xl', '') + ' bg-gradient-to-br from-amber-500 to-orange-600 shadow-md';
            } else if (text === '👨‍⚕️') {
                el.innerHTML = '<i data-lucide="user-check" class="w-8 h-8 text-white"></i>';
                el.className = el.className.replace('text-3xl', '') + ' bg-gradient-to-br from-teal-500 to-emerald-600 shadow-md';
            } else if (text === '📈') {
                el.innerHTML = '<i data-lucide="trending-up" class="w-8 h-8 text-white"></i>';
                el.className = el.className.replace('text-3xl', '') + ' bg-gradient-to-br from-blue-500 to-indigo-600 shadow-md';
            } else if (text === '✉️') {
                el.innerHTML = '<i data-lucide="mail" class="w-8 h-8 text-white"></i>';
                el.className = el.className.replace('text-3xl', '') + ' bg-gradient-to-br from-teal-500 to-emerald-600 shadow-md';
            } else if (text === '📤') {
                el.innerHTML = '<i data-lucide="send" class="w-8 h-8 text-white"></i>';
                el.className = el.className.replace('text-3xl', '') + ' bg-gradient-to-br from-sky-500 to-blue-600 shadow-md';
            } else if (text === '🎯') {
                el.innerHTML = '<i data-lucide="target" class="w-8 h-8 text-white"></i>';
                el.className = el.className.replace('text-3xl', '') + ' bg-gradient-to-br from-purple-500 to-fuchsia-600 shadow-md';
            } else if (text === '🛡️') {
                el.innerHTML = '<i data-lucide="shield-alert" class="w-8 h-8 text-white"></i>';
                el.className = el.className.replace('text-3xl', '') + ' bg-gradient-to-br from-red-500 to-rose-600 shadow-md';
            }
        });

        // Initialize Lucide Icons
        lucide.createIcons();
    });
</script>

