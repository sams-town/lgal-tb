<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Buat notifikasi baru dan simpan ke database
 * @param string $title Judul notifikasi
 * @param string $message Isi pesan notifikasi
 * @param string|null $target_role Nama role target (opsional)
 * @param int|null $user_id ID user target (opsional)
 * @return bool Status keberhasilan
 */
function createNotification($title, $message, $target_role = null, $user_id = null)
{
    global $pdo;
    
    try {
        // Pastikan tabel notifications ada, jika tidak buat
        ensureNotificationsTableExists();
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, target_role, user_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $title,
            $message,
            $target_role,
            $user_id
        ]);
        
        return true;
    } catch (PDOException $e) {
        // Handle error
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get roles that have a specific permission
 * @param string $permission
 * @return array List of role names
 */
function getRolesByPermission($permission) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT nama_role FROM roles WHERE JSON_CONTAINS(permissions, ?)");
        $stmt->execute(['"' . $permission . '"']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Buat notifikasi untuk semua role yang memiliki permission tertentu
 * @param string $title Judul notifikasi
 * @param string $message Isi pesan notifikasi
 * @param string $permission Key permission yang ditarget
 * @param int|null $user_id ID user pengecualian/tambahan (opsional)
 * @return bool
 */
function notifyByPermission($title, $message, $permission, $user_id = null) {
    $roles = getRolesByPermission($permission);
    $success = true;
    foreach ($roles as $role) {
        if (!createNotification($title, $message, $role, $user_id)) {
            $success = false;
        }
    }
    return $success;
}

/**
 * Pastikan tabel notifications ada di database, buat jika tidak ada
 */
function ensureNotificationsTableExists()
{
    global $pdo;
    
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT(11) NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                target_role VARCHAR(100) NULL,
                user_id INT(11) NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY target_role (target_role),
                KEY user_id (user_id),
                KEY is_read (is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        // Table already exists or error
        error_log("Error ensuring notifications table: " . $e->getMessage());
    }
}

/**
 * Dapatkan notifikasi untuk user yang sedang login
 * @param int $limit Batas jumlah notifikasi yang diambil
 * @return array Daftar notifikasi
 */
function getNotificationsForCurrentUser($limit = 10)
{
    global $pdo;
    
    if (!isset($_SESSION['user'])) {
        return [];
    }
    
    $user = $_SESSION['user'];
    $user_id = $user['id'] ?? null;
    $user_role = $user['nama_role'] ?? $user['role'] ?? null;
    
    try {
        ensureNotificationsTableExists();
        
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE (user_id = ? OR target_role = ? OR (user_id IS NULL AND target_role IS NULL))
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $stmt->execute([$user_id, $user_role, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Hitung jumlah notifikasi yang belum dibaca untuk user yang sedang login
 * @return int Jumlah notifikasi belum dibaca
 */
function countUnreadNotifications()
{
    global $pdo;
    
    if (!isset($_SESSION['user'])) {
        return 0;
    }
    
    $user = $_SESSION['user'];
    $user_id = $user['id'] ?? null;
    $user_role = $user['nama_role'] ?? $user['role'] ?? null;
    
    try {
        ensureNotificationsTableExists();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS count FROM notifications 
            WHERE is_read = 0
            AND (user_id = ? OR target_role = ? OR (user_id IS NULL AND target_role IS NULL))
        ");
        
        $stmt->execute([$user_id, $user_role]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['count'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error counting unread notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Tandai notifikasi sebagai sudah dibaca
 * @param int $notification_id ID notifikasi
 * @return bool Status keberhasilan
 */
function markNotificationAsRead($notification_id)
{
    global $pdo;
    
    try {
        ensureNotificationsTableExists();
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ?
        ");
        
        $stmt->execute([$notification_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Tandai semua notifikasi untuk user saat ini sebagai sudah dibaca
 * @return bool Status keberhasilan
 */
function markAllNotificationsAsRead()
{
    global $pdo;
    
    if (!isset($_SESSION['user'])) {
        return false;
    }
    
    $user = $_SESSION['user'];
    $user_id = $user['id'] ?? null;
    $user_role = $user['nama_role'] ?? $user['role'] ?? null;
    
    try {
        ensureNotificationsTableExists();
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE is_read = 0
            AND (user_id = ? OR target_role = ? OR (user_id IS NULL AND target_role IS NULL))
        ");
        
        $stmt->execute([$user_id, $user_role]);
        return true;
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Format tanggal dari Y-m-d ke format Indonesia (d F Y)
 * @param string|null $date Tanggal dalam format Y-m-d
 * @return string Tanggal terformat atau '-' jika kosong/invalid
 */
if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (!$date) return '-';
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        try {
            $d = new DateTime($date);
            return $d->format('d') . ' ' . $months[$d->format('n')] . ' ' . $d->format('Y');
        } catch (Exception $e) {
            return '-';
        }
    }
}
?>