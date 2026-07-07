<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is Super Admin
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$userRole = $_SESSION['user']['nama_role'] ?? $_SESSION['user']['role'] ?? '';
if ($userRole !== 'Super Admin') {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

// Initialize tables if not exists
if (isset($isLocal) && $isLocal) {
    try {
        // Create users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(100) NOT NULL,
                status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Create roles table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama_role VARCHAR(100) NOT NULL UNIQUE,
                deskripsi TEXT NULL,
                permissions JSON NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Insert sample roles if none
        $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
        if (false && $stmt->fetchColumn() == 0) {
            $sampleRoles = [
                ['name' => 'Super Admin', 'permissions' => '["dashboard", "legal", "sekretariat", "akreditasi", "corporate-secretary", "sop", "tenaga-medis", "audit-trail", "setting"]'],
                ['name' => 'Staf Legal', 'permissions' => '["dashboard", "legal", "akreditasi"]'],
                ['name' => 'Staf Sekretariat', 'permissions' => '["dashboard", "sekretariat"]'],
                ['name' => 'Direktur', 'permissions' => '["dashboard", "legal", "sekretariat", "akreditasi", "corporate-secretary", "sop", "tenaga-medis", "audit-trail"]']
            ];

            $stmt = $pdo->prepare("INSERT INTO roles (nama_role, permissions) VALUES (?, ?)");
            foreach ($sampleRoles as $role) {
                $stmt->execute([$role['name'], $role['permissions']]);
            }
        }

        // Insert sample admin if none
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if (false && $stmt->fetchColumn() == 0) {
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['Super Admin', 'admin@thb.id', $password, 'Super Admin', 'Aktif']);
        }
    } catch (PDOException $e) {
        // Continue if tables already exist
    }
}

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $pin = !empty($_POST['pin']) ? password_hash($_POST['pin'], PASSWORD_DEFAULT) : null;
    $role_id = $_POST['role_id'];
    $status = $_POST['status'] === 'Aktif' ? 1 : 0;

    $tandaTanganPath = null;
    if (isset($_FILES['tanda_tangan']) && $_FILES['tanda_tangan']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/tanda_tangan/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES['tanda_tangan']['name']));
        $targetFile = $uploadDir . $fileName;
        
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['png', 'jpg', 'jpeg'];
        
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['tanda_tangan']['tmp_name'], $targetFile)) {
                $tandaTanganPath = $targetFile;
            }
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users (nama, email, password, pin, tanda_tangan, role_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nama, $email, $password, $pin, $tandaTanganPath, $role_id, $status]);
        $newUserId = $pdo->lastInsertId();
        
        // Send notification to the new user
        // Note: createNotification expects role_name, so we fetch it
        $stmtRole = $pdo->prepare("SELECT nama_role FROM roles WHERE id = ?");
        $stmtRole->execute([$role_id]);
        $roleInfo = $stmtRole->fetch();
        $roleName = $roleInfo ? $roleInfo['nama_role'] : 'Unknown';
        
        createNotification(
            "Akun Baru Diberikan",
            "Halo $nama, akun Anda di sistem RS Taman Harapan Baru telah dibuat dengan role $roleName.",
            $roleName,
            $newUserId
        );
        
        $success = "User berhasil ditambahkan";
    } catch (PDOException $e) {
        $error = "Gagal menambahkan user: " . $e->getMessage();
    }
}

// Handle delete user
if (isset($_GET['delete_user'])) {
    $id = $_GET['delete_user'];
    try {
        // Get user info before deleting
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $deletedUser = $stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success = "User berhasil dihapus";
    } catch (PDOException $e) {
        $error = "Gagal menghapus user: " . $e->getMessage();
    }
}

// Handle add divisi (role)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_divisi'])) {
    $namaDivisi = trim($_POST['nama_divisi']);
    try {
        $stmt = $pdo->prepare("INSERT INTO roles (nama_role, permissions) VALUES (?, '[]')");
        $stmt->execute([$namaDivisi]);
        $success = "Divisi berhasil ditambahkan.";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            $error = "Gagal menambahkan: Divisi '$namaDivisi' sudah ada.";
        } else {
            $error = "Gagal menambahkan divisi: " . $e->getMessage();
        }
    }
}

// Handle edit permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    $roleId = $_POST['role_id'];
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    $permissionsJson = json_encode($permissions);

    try {
        // Get role name before updating
        $stmt = $pdo->prepare("SELECT nama_role FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();
        $roleName = $role['nama_role'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE roles SET permissions = ? WHERE id = ?");
        $stmt->execute([$permissionsJson, $roleId]);
        
        // Send notification to all users with this role
        createNotification(
            "Perubahan Hak Akses",
            "Perubahan hak akses untuk role $roleName telah diperbarui oleh admin.",
            $roleName
        );
        
        $success = "Permissions berhasil diupdate";
    } catch (PDOException $e) {
        $error = "Gagal update permissions: " . $e->getMessage();
    }
}

// Handle update integrasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_integration'])) {
    $id = $_POST['integration_id'];
    $apiKey = $_POST['api_key'];
    $apiSecret = $_POST['api_secret'];
    $endpointUrl = $_POST['endpoint_url'];
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("UPDATE system_integrations SET api_key = ?, api_secret = ?, endpoint_url = ?, status = ? WHERE id = ?");
        $stmt->execute([$apiKey, $apiSecret, $endpointUrl, $status, $id]);
        $success = "Konfigurasi integrasi sistem berhasil diperbarui.";
    } catch (PDOException $e) {
        $error = "Gagal memperbarui integrasi: " . $e->getMessage();
    }
}

// Handle add divisi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_divisi'])) {
    $namaDivisi = trim($_POST['nama_divisi']);
    try {
        $stmt = $pdo->prepare("INSERT INTO divisi (nama_divisi) VALUES (?)");
        $stmt->execute([$namaDivisi]);
        $success = "Divisi berhasil ditambahkan.";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            $error = "Gagal menambahkan: Divisi '$namaDivisi' sudah ada.";
        } else {
            $error = "Gagal menambahkan divisi: " . $e->getMessage();
        }
    }
}

// Handle edit divisi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_divisi_submit'])) {
    $id = $_POST['divisi_id'];
    $namaDivisi = trim($_POST['nama_divisi']);
    try {
        $stmt = $pdo->prepare("UPDATE divisi SET nama_divisi = ? WHERE id = ?");
        $stmt->execute([$namaDivisi, $id]);
        $success = "Divisi berhasil diperbarui.";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Gagal memperbarui: Divisi '$namaDivisi' sudah ada.";
        } else {
            $error = "Gagal memperbarui divisi: " . $e->getMessage();
        }
    }
}

// Handle delete divisi
if (isset($_GET['delete_divisi'])) {
    $id = $_GET['delete_divisi'];
    try {
        $stmt = $pdo->prepare("DELETE FROM divisi WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Divisi berhasil dihapus.";
    } catch (PDOException $e) {
        $error = "Gagal menghapus divisi: " . $e->getMessage();
    }
}

// Get current tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'user';

// Get users
try {
    $stmt = $pdo->query("
        SELECT u.*, r.nama_role 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Get roles
try {
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY created_at DESC");
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    $roles = [];
}

// Get system integrations
try {
    $stmt = $pdo->query("SELECT * FROM system_integrations ORDER BY provider_name ASC");
    $integrations = $stmt->fetchAll();
} catch (PDOException $e) {
    $integrations = [];
}

// Get divisi
try {
    $stmt = $pdo->query("SELECT * FROM divisi ORDER BY nama_divisi ASC");
    $divisiList = $stmt->fetchAll();
} catch (PDOException $e) {
    $divisiList = [];
}

// Calculate stats
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalUsers = 0;
}

// Calculate uploads directory size relative to a 2 GB quota
function getUploadsSize($dir = 'uploads') {
    $size = 0;
    if (!is_dir($dir)) return 0;
    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    } catch (Exception $e) {
        // Fallback
    }
    return $size;
}

$uploadsSize = getUploadsSize(__DIR__ . '/uploads');
$quota = 2 * 1024 * 1024 * 1024; // 2 GB quota
$diskUsedPercent = min(100, max(0, round(($uploadsSize / $quota) * 100)));

$permissionList = [
    'dashboard' => 'Dashboard',
    'legal' => 'Legal',
    'sekretariat' => 'Sekretariat',
    'akreditasi' => 'Akreditasi & Mutu',
    'approval' => 'Persetujuan & E-Sign',
    'sop' => 'SOP & SDM',
    'tenaga-medis' => 'Komite',
    'corporate-secretary' => 'Corporate Secretary',
    'audit-trail' => 'Audit Trail',
    'setting' => 'Pengaturan'
];

// Helper for status badge
function getStatusBadgeClass($status) {
    return $status === 'Aktif' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-red-100 text-red-800 border-red-200';
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">


    <!-- Sidebar -->
    <?php require 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <!-- Header -->
        <?php require 'includes/header.php'; ?>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Pengaturan Sistem</h1>
                        <p class="text-gray-600 mt-2">Kelola pengguna, role, dan konfigurasi sistem</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Pengguna</p>
                                <h3 class="text-3xl font-bold text-emerald-600"><?php echo $totalUsers; ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-3xl">👥</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Sistem Aktif / Aman</p>
                                <h3 class="text-3xl font-bold text-green-600">✅</h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl flex items-center justify-center text-3xl">🛡️</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Perlu Sinkronisasi</p>
                                <h3 class="text-3xl font-bold text-blue-600">0</h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-3xl">🔄</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Status Storage</p>
                                <h3 class="text-3xl font-bold text-amber-600"><?php echo $diskUsedPercent; ?>%</h3>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">💾</div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex gap-2 border-b border-gray-200">
                    <a href="setting.php?tab=user" class="px-6 py-3 font-medium transition-colors <?php echo $tab === 'user' ? 'text-emerald-600 border-b-2 border-emerald-600' : 'text-gray-600 hover:text-gray-900'; ?>">
                        User Management
                    </a>
                    <a href="setting.php?tab=role" class="px-6 py-3 font-medium transition-colors <?php echo $tab === 'role' ? 'text-emerald-600 border-b-2 border-emerald-600' : 'text-gray-600 hover:text-gray-900'; ?>">
                        Manajemen Divisi & Hak Akses
                    </a>
                    <a href="setting.php?tab=integrasi" class="px-6 py-3 font-medium transition-colors <?php echo $tab === 'integrasi' ? 'text-emerald-600 border-b-2 border-emerald-600' : 'text-gray-600 hover:text-gray-900'; ?>">
                        Integrasi Sistem
                    </a>
                </div>

                <!-- Tab Content -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <?php if ($tab === 'user'): ?>
                        <!-- User Management -->
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Kelola Pengguna</h2>
                            <button onclick="openModal('tambahUserModal')" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                                <span>+</span>
                                <span>Tambah Pengguna</span>
                            </button>
                        </div>
                        
                        <?php if (isset($success)): ?>
                            <div class="mb-4 p-4 bg-emerald-100 text-emerald-800 rounded-xl">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                            <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-xl">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nama</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Email</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Divisi & Hak Akses</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                                Belum ada pengguna yang terdaftar
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $index => $u): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($u['name'] ?? $u['nama'] ?? ''); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo htmlspecialchars($u['email'] ?? ''); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm"><?php echo htmlspecialchars($u['nama_role'] ?? 'Unknown'); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php $status = (isset($u['is_active']) && $u['is_active'] == 1) ? 'Aktif' : 'Nonaktif'; ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo getStatusBadgeClass($status); ?>">
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <button class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                                                            Edit
                                                        </button>
                                                        <a href="setting.php?delete_user=<?php echo $u['id']; ?>&tab=user" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                                            Hapus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($tab === 'role'): ?>
                        <!-- Divisi & Hak Akses Management -->
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Divisi & Hak Akses</h2>
                            <button onclick="openModal('tambahDivisiModal')" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                                <span>+</span>
                                <span>Tambah Divisi</span>
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nama Divisi</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Hak Akses (Permissions)</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($roles)): ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                                Belum ada role yang terdaftar
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($roles as $index => $role): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($role['nama_role'] ?? $role['name'] ?? ''); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <?php 
                                                    $permissions = json_decode($role['permissions'], true) ?: [];
                                                    $permissionNames = array_map(function($perm) use ($permissionList) {
                                                        return $permissionList[$perm] ?? $perm;
                                                    }, $permissions);
                                                    ?>
                                                    <div class="flex flex-wrap gap-1">
                                                        <?php foreach ($permissionNames as $pName): ?>
                                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                                                <?php echo htmlspecialchars($pName); ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <button onclick='openEditPermissionModal(<?php echo json_encode($role); ?>)' class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                                                        Edit Permission
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($tab === 'integrasi'): ?>
                        <!-- Integrasi Sistem -->
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Integrasi Sistem Eksternal</h2>
                        </div>
                        
                        <?php if (isset($success) && $tab === 'integrasi'): ?>
                            <div class="mb-4 p-4 bg-emerald-100 text-emerald-800 rounded-xl">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error) && $tab === 'integrasi'): ?>
                            <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-xl">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Provider</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Endpoint URL</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Status</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($integrations)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                                Belum ada konfigurasi integrasi sistem
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($integrations as $index => $integ): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-medium"><?php echo $index + 1; ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($integ['provider_name']); ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-gray-700">
                                                    <p class="text-sm font-mono truncate max-w-xs" title="<?php echo htmlspecialchars($integ['endpoint_url']); ?>"><?php echo htmlspecialchars($integ['endpoint_url']); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php $integStatus = $integ['status']; ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $integStatus === 'Aktif' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-red-100 text-red-800 border-red-200'; ?>">
                                                        <?php echo htmlspecialchars($integStatus); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <button onclick='openEditIntegrationModal(<?php echo json_encode($integ); ?>)' class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                                                        Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Tambah User -->
    <div id="tambahUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Tambah Pengguna Baru</h2>
                <button onclick="closeModal('tambahUserModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap</label>
                    <input type="text" name="nama" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                
                <!-- Input PIN -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">PIN Persetujuan (6 Digit)</label>
                    <div class="relative">
                        <input type="password" name="pin" maxlength="6" pattern="\d{6}" placeholder="Contoh: 123456" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono tracking-widest">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs text-gray-400">
                            Angka Saja
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Digunakan untuk security approval atau tanda tangan digital.</p>
                </div>

                <!-- Input File Tanda Tangan -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanda Tangan Digital (Gambar)</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl hover:border-emerald-500 transition-colors bg-gray-50 relative group">
                        <div class="space-y-1 text-center">
                            <div class="text-4xl text-gray-400 mb-2">✍️</div>
                            <div class="flex text-sm text-gray-600 justify-center">
                                <label for="tanda_tangan" class="relative cursor-pointer bg-white rounded-md font-medium text-emerald-600 hover:text-emerald-500 focus-within:outline-none">
                                    <span>Upload file</span>
                                    <input id="tanda_tangan" name="tanda_tangan" type="file" accept="image/png, image/jpeg, image/jpg" class="sr-only">
                                </label>
                                <p class="pl-1">atau drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PNG, JPG up to 2MB (Direkomendasikan PNG Transparan)</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Divisi</label>
                    <select name="role_id" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">Pilih Divisi...</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['nama_role'] ?? $role['name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('tambahUserModal')" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_user" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Permission -->
    <div id="editPermissionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Edit Permissions</h2>
                <button onclick="closeModal('editPermissionModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="role_id" id="edit_role_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-4">Pilih Hak Akses</label>
                    <div class="grid grid-cols-2 gap-4">
                        <?php foreach ($permissionList as $permKey => $permName): ?>
                            <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($permKey); ?>" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($permName); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('editPermissionModal')" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="update_permissions" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Integrasi -->
    <div id="editIntegrationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Edit Konfigurasi <span id="integ_provider_title"></span></h2>
                <button onclick="closeModal('editIntegrationModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="integration_id" id="edit_integ_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                    <input type="text" name="api_key" id="edit_integ_api_key" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">API Secret (Opsional)</label>
                    <input type="password" name="api_secret" id="edit_integ_api_secret" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Endpoint URL</label>
                    <input type="url" name="endpoint_url" id="edit_integ_endpoint" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="edit_integ_status" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('editIntegrationModal')" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="update_integration" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Tambah Divisi -->
    <div id="tambahDivisiModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Tambah Divisi Baru</h2>
                <button onclick="closeModal('tambahDivisiModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Divisi</label>
                    <input type="text" name="nama_divisi" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeModal('tambahDivisiModal')" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="tambah_divisi" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
        }

        function openEditPermissionModal(role) {
            document.getElementById('edit_role_id').value = role.id;
            
            // Reset all checkboxes
            const checkboxes = document.querySelectorAll('#editPermissionModal input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);
            
            // Check the permissions
            const permissions = JSON.parse(role.permissions || '[]');
            permissions.forEach(perm => {
                const checkbox = document.querySelector(`#editPermissionModal input[value="${perm}"]`);
                if (checkbox) checkbox.checked = true;
            });
            
            openModal('editPermissionModal');
        }

        function openEditIntegrationModal(integ) {
            document.getElementById('edit_integ_id').value = integ.id;
            document.getElementById('integ_provider_title').innerText = integ.provider_name;
            document.getElementById('edit_integ_api_key').value = integ.api_key || '';
            document.getElementById('edit_integ_api_secret').value = integ.api_secret || '';
            document.getElementById('edit_integ_endpoint').value = integ.endpoint_url || '';
            document.getElementById('edit_integ_status').value = integ.status || 'Aktif';
            openModal('editIntegrationModal');
        }
    </script>
</body>
</html>
