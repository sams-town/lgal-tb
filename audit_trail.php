<?php
/*
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_name VARCHAR(255) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    action VARCHAR(50) NOT NULL,
    module VARCHAR(100) NOT NULL,
    details TEXT NOT NULL,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp),
    INDEX idx_module (module),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

session_start();
require_once 'config/database.php';

// Check if user is logged in and is Super Admin
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if ($_SESSION['user']['role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

// Initialize audit_logs table and insert sample data if empty
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            user_name VARCHAR(255) NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            action VARCHAR(50) NOT NULL,
            module VARCHAR(100) NOT NULL,
            details TEXT NOT NULL,
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_module (module),
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert sample data if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
    if (false && $stmt->fetchColumn() == 0) {
        $sampleLogs = [
            ['user_id' => 1, 'user_name' => 'Irsad Super Admin', 'user_email' => 'irsad@thb.id', 'action' => 'LOGIN', 'module' => 'Sistem', 'details' => 'User berhasil login ke sistem'],
            ['user_id' => 1, 'user_name' => 'Irsad Super Admin', 'user_email' => 'irsad@thb.id', 'action' => 'CREATE', 'module' => 'PKS', 'details' => 'Menambahkan draf PKS baru dengan PT Mitra Sehat Sentosa'],
            ['user_id' => 1, 'user_name' => 'Irsad Super Admin', 'user_email' => 'irsad@thb.id', 'action' => 'UPDATE', 'module' => 'Akreditasi', 'details' => 'Memperbarui status elemen penilaian EP-102 menjadi Sudah Terpenuhi'],
            ['user_id' => 1, 'user_name' => 'Irsad Super Admin', 'user_email' => 'irsad@thb.id', 'action' => 'CREATE', 'module' => 'Surat Masuk', 'details' => 'Menambahkan surat masuk dari Dinas Kesehatan Propinsi'],
            ['user_id' => 1, 'user_name' => 'Irsad Super Admin', 'user_email' => 'irsad@thb.id', 'action' => 'APPROVE', 'module' => 'Persetujuan', 'details' => 'Menyetujui perjanjian kerjasama pada tahap Komite Medik'],
            ['user_id' => 1, 'user_name' => 'Irsad Super Admin', 'user_email' => 'irsad@thb.id', 'action' => 'DELETE', 'module' => 'SOP', 'details' => 'Menghapus dokumen SOP lama yang sudah tidak berlaku'],
            ['user_id' => 1, 'user_name' => 'Irsad Super Admin', 'user_email' => 'irsad@thb.id', 'action' => 'LOGIN', 'module' => 'Sistem', 'details' => 'User berhasil login ke sistem']
        ];

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, user_name, user_email, action, module, details)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($sampleLogs as $log) {
            $stmt->execute([
                $log['user_id'],
                $log['user_name'],
                $log['user_email'],
                $log['action'],
                $log['module'],
                $log['details']
            ]);
        }
    }
} catch (PDOException $e) {
    // Continue if sample data fails to insert
}

// Get statistics
$today = date('Y-m-d');
$stats = [
    'total' => 0,
    'today' => 0,
    'legal' => 0,
    'sekretariat' => 0
];

try {
    // Total logs
    $stmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
    $stats['total'] = $stmt->fetchColumn();

    // Today's logs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE DATE(timestamp) = ?");
    $stmt->execute([$today]);
    $stats['today'] = $stmt->fetchColumn();

    // Legal module logs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE module IN ('PKS', 'Regulasi', 'Perizinan', 'Persetujuan')");
    $stmt->execute();
    $stats['legal'] = $stmt->fetchColumn();

    // Sekretariat module logs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE module IN ('Surat Masuk', 'Surat Keluar')");
    $stmt->execute();
    $stats['sekretariat'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Use default values
}

// Get all logs
$logs = [];
try {
    $stmt = $pdo->query("SELECT * FROM audit_logs ORDER BY timestamp DESC");
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    // No logs yet
}

// Helper function for badge colors
function getActionBadgeClass($action) {
    switch (strtoupper($action)) {
        case 'CREATE':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'UPDATE':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'DELETE':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'LOGIN':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'APPROVE':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// Helper function for Indonesian date/time
function formatDateTime($datetime) {
    if (!$datetime) return '-';
    $date = new DateTime($datetime);
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    $dayName = $days[$date->format('w')];
    $day = $date->format('d');
    $month = $months[$date->format('n')];
    $year = $date->format('Y');
    $time = $date->format('H:i:s');
    
    return "$dayName, $day $month $year - $time";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail / Log Aktivitas - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <?php include 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Audit Trail / Log Aktivitas</h1>
                        <p class="text-gray-600 mt-2">Rekam jejak aktivitas seluruh pengguna sistem</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Aktivitas</p>
                                <h3 class="text-3xl font-bold text-gray-900"><?php echo $stats['total']; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center text-3xl">📊</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Aktivitas Hari Ini</p>
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $stats['today']; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">📅</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Log Modul Legal</p>
                                <h3 class="text-3xl font-bold text-purple-600"><?php echo $stats['legal']; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">📑</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Log Modul Sekretariat</p>
                                <h3 class="text-3xl font-bold text-amber-600"><?php echo $stats['sekretariat']; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">✉️</div>
                        </div>
                    </div>
                </div>

                <!-- Log Timeline Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Waktu Kejadian</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Modul</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Detail Aktivitas</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Pelaku Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada log aktivitas yang tercatat
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-gray-700 whitespace-nowrap">
                                                <p class="text-sm font-medium text-gray-900"><?php echo formatDateTime($log['timestamp']); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo getActionBadgeClass($log['action']); ?>">
                                                    <?php echo htmlspecialchars(strtoupper($log['action'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($log['module']); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700 max-w-md">
                                                <p class="text-sm"><?php echo htmlspecialchars($log['details']); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($log['user_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($log['user_email']); ?></p>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
