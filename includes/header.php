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
<header class="bg-white shadow-sm px-8 py-4 flex justify-between items-center">
    <div class="flex items-center gap-4 flex-1 max-w-md">
        <div class="relative flex-1">
            <input 
                type="text" 
                placeholder="Cari semua modul..." 
                class="w-full pl-10 pr-4 py-2 bg-gray-100 border-0 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
            >
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
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
        ?>
            <button id="openModalBtn" onclick="handleOpenModal()" class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-medium transition-colors shadow-sm hover:shadow-md">
                <?php if ($current_page === 'pks.php'): ?>
                    <span class="text-xl">+</span>
                    <span>FORMULIR PENGAJUAN KERJASAMA</span>
                <?php elseif ($current_page === 'pengajuan.php'): ?>
                    <span class="text-xl">+</span>
                    <span>Tambah Pengajuan</span>
                <?php else: ?>
                    <span class="text-xl">+</span>
                    <span>Tambah Dokumen</span>
                <?php endif; ?>
            </button>
        <?php endif; ?>
        
        <!-- Notification Bell -->
        <div class="relative" id="notificationContainer">
            <button 
                onclick="toggleNotifications()" 
                class="text-gray-500 hover:text-emerald-600 transition-colors text-xl relative"
            >
                🔔
                <?php if ($unread_count > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                        <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                    </span>
                <?php endif; ?>
            </button>
            
            <!-- Notification Dropdown -->
            <div id="notificationDropdown" class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden hidden z-50">
                <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Notifikasi</h3>
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" class="inline">
                            <button type="submit" name="mark_all_read" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">
                                Tandai semua dibaca
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="max-h-96 overflow-y-auto">
                    <?php if (empty($notifications)): ?>
                        <div class="px-4 py-6 text-center text-gray-500">
                            <p class="text-sm">Tidak ada notifikasi</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors <?php echo $notif['is_read'] ? 'opacity-60' : 'bg-emerald-50'; ?>">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        <p class="text-xs text-gray-400 mt-1"><?php echo date('d M Y, H:i', strtotime($notif['created_at'])); ?></p>
                                    </div>
                                    <?php if (!$notif['is_read']): ?>
                                        <form method="POST" class="flex-shrink-0">
                                            <input type="hidden" name="mark_read_id" value="<?php echo $notif['id']; ?>">
                                            <button type="submit" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">
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
        
        <div class="flex items-center gap-3 bg-emerald-50 px-4 py-2 rounded-xl">
            <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-full flex items-center justify-center text-white font-bold text-lg">
                <?php echo htmlspecialchars(substr($user_name, 0, 1)); ?>
            </div>
            <div class="text-left">
                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($user_name); ?></p>
                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user_role); ?></p>
            </div>
            <a href="logout.php" class="text-sm text-red-600 hover:text-red-700 font-medium">Logout</a>
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
