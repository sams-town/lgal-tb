<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
    markNotificationAsRead((int)$_POST['mark_read_id']);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    markAllNotificationsAsRead();
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$user = $_SESSION['user'] ?? ['nama' => 'Guest', 'nama_role' => 'Guest'];
$user_name = $user['nama'] ?? 'Guest';
$user_role = $user['nama_role'] ?? 'Guest';
$current_page = basename($_SERVER['PHP_SELF']);
$unread_count = countUnreadNotifications();
$notifications = getNotificationsForCurrentUser(10);
?>
<!-- Header -->
<header class="bg-white/80 backdrop-blur-md border-b border-gray-100 px-8 py-4 flex justify-between items-center sticky top-0 z-40">
    <div class="flex items-center gap-4 flex-1 max-w-md">
        <div class="relative flex-1">
            <input 
                type="text" 
                placeholder="Cari semua modul..." 
                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:bg-white transition-all duration-300"
            >
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <i data-lucide="search" class="w-4 h-4"></i>
            </span>
        </div>
    </div>
    
    <div class="flex items-center gap-4">
        <?php 
        $add_button_pages = [
            'pks.php', 'regulasi.php', 'perizinan.php', 'legal-arsip.php', 'corsec.php', 
            'surat-masuk.php', 'surat-keluar.php', 'komite-medik.php', 'komite-keperawatan.php', 
            'komite-nakes.php', 'komite-tenaga-kesehatan-lainnya.php', 'sip-dokter.php', 
            'str-nakes.php', 'tambah-tenaga-medis.php', 'akreditasi.php', 'sop.php',
            'pengajuan.php'
        ];
        if (in_array($current_page, $add_button_pages)): 
            $can_add = false;
            $is_legal_page = in_array($current_page, ['pks.php', 'legal-arsip.php', 'regulasi.php', 'perizinan.php']);
            
            if ($is_legal_page || $current_page === 'pengajuan.php') {
                $can_add = hasPermission('legal_add');
            } elseif ($current_page === 'surat-masuk.php' || $current_page === 'surat-keluar.php') {
                $can_add = hasPermission('sekretariat_add');
            } elseif (in_array($current_page, ['komite-medik.php', 'komite-keperawatan.php', 'komite-nakes.php', 'komite-tenaga-kesehatan-lainnya.php', 'sip-dokter.php', 'str-nakes.php', 'tambah-tenaga-medis.php'])) {
                $can_add = hasPermission('komite_add');
            } elseif ($current_page === 'corsec.php') {
                $can_add = hasPermission('corsec_add');
            } elseif ($current_page === 'akreditasi.php') {
                $can_add = hasPermission('akreditasi_add');
            } elseif ($current_page === 'sop.php') {
                $can_add = hasPermission('sop_add');
            }
            
            if ($can_add):
        ?>
            <button id="openModalBtn" onclick="handleOpenModal()" class="flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <?php if ($current_page === 'pks.php'): ?>
                    <span>FORMULIR PENGAJUAN KERJASAMA</span>
                <?php elseif ($current_page === 'pengajuan.php'): ?>
                    <span>Tambah Pengajuan</span>
                <?php else: ?>
                    <span>Tambah Dokumen</span>
                <?php endif; ?>
            </button>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Notification Bell -->
        <div class="relative" id="notificationContainer">
            <button 
                onclick="toggleNotifications()" 
                class="w-10 h-10 flex items-center justify-center bg-gray-50 border border-gray-200 rounded-xl text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 transition-all duration-300 relative"
            >
                <i data-lucide="bell" class="w-5 h-5"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center ring-2 ring-white">
                        <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                    </span>
                <?php endif; ?>
            </button>
            
            <!-- Notification Dropdown -->
            <div id="notificationDropdown" class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden hidden z-50">
                <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h3 class="font-semibold text-gray-800 text-sm">Notifikasi</h3>
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" class="inline">
                            <button type="submit" name="mark_all_read" class="text-xs text-emerald-600 hover:text-emerald-700 font-semibold">
                                Tandai semua dibaca
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="max-h-96 overflow-y-auto">
                    <?php if (empty($notifications)): ?>
                        <div class="px-4 py-8 text-center text-gray-500">
                            <i data-lucide="bell-off" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                            <p class="text-xs">Tidak ada notifikasi baru</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors <?php echo $notif['is_read'] ? 'opacity-65' : 'bg-emerald-50/30'; ?>">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1">
                                        <p class="text-xs font-bold text-gray-800"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-xs text-gray-600 mt-0.5 leading-relaxed"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        <p class="text-[10px] text-gray-400 mt-1 font-medium"><?php echo date('d M Y, H:i', strtotime($notif['created_at'])); ?></p>
                                    </div>
                                    <?php if (!$notif['is_read']): ?>
                                        <form method="POST" class="flex-shrink-0">
                                            <input type="hidden" name="mark_read_id" value="<?php echo $notif['id']; ?>">
                                            <button type="submit" class="w-6 h-6 flex items-center justify-center bg-white border border-gray-200 hover:bg-emerald-50 hover:text-emerald-700 rounded-lg text-xs font-bold shadow-sm transition-colors">
                                                ✓
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- User Profile Dropdown / Card -->
        <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 pl-3 pr-4 py-1.5 rounded-xl shadow-sm">
            <div class="w-8 h-8 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-inner">
                <?php echo htmlspecialchars(substr($user_name, 0, 1)); ?>
            </div>
            <div class="text-left leading-tight">
                <p class="text-xs font-bold text-gray-800"><?php echo htmlspecialchars($user_name); ?></p>
                <p class="text-[10px] text-gray-500 font-medium"><?php echo htmlspecialchars($user_role); ?></p>
            </div>
            <div class="w-[1px] h-6 bg-gray-200 mx-1"></div>
            <a href="logout.php" class="text-xs text-red-500 hover:text-red-700 font-bold transition-colors">Logout</a>
        </div>
    </div>
</header>

<script>
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('hidden');
}

function handleOpenModal() {
    const currentPage = '<?php echo $current_page; ?>';
    
    const standardModalPages = [
        'pks.php', 'regulasi.php', 'perizinan.php', 'legal-arsip.php', 'corsec.php', 
        'surat-masuk.php', 'surat-keluar.php', 'komite-medik.php', 'komite-keperawatan.php', 
        'komite-nakes.php', 'komite-tenaga-kesehatan-lainnya.php', 'sip-dokter.php', 
        'str-nakes.php', 'tambah-tenaga-medis.php', 'akreditasi.php', 'sop.php',
        'pengajuan.php'
    ];
    
    if (standardModalPages.includes(currentPage)) {
        openModal('modal');
    }
}

function openModal(modalId = 'modal') {
    const element = document.getElementById(modalId);
    if (element) {
        element.classList.remove('hidden');
        element.classList.add('flex');
    }
}

function closeModal(modalId = 'modal') {
    const element = document.getElementById(modalId);
    if (element) {
        element.classList.add('hidden');
        element.classList.remove('flex');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const container = document.getElementById('notificationContainer');
    if (!container.contains(e.target)) {
        const dropdown = document.getElementById('notificationDropdown');
        if (!dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
        }
    }
});
</script>
