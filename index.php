<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === 'irsad@thb.id' && $password === '123456') {
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Irsad Super Admin',
            'nama' => 'Irsad Super Admin',
            'email' => $email,
            'role' => 'Super Admin',
            'nama_role' => 'Super Admin',
            'department' => 'IT'
        ];
        header('Location: dashboard.php');
        exit;
    } else {
        // Check database
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, r.nama_role 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.email = ?
            ");
            $stmt->execute([$email]);
            $db_user = $stmt->fetch();

            if ($db_user && password_verify($password, $db_user['password'])) {
                if ($db_user['is_active'] == 1) {
                    $_SESSION['user'] = [
                        'id' => $db_user['id'],
                        'name' => $db_user['nama'],
                        'nama' => $db_user['nama'],
                        'email' => $db_user['email'],
                        'role' => $db_user['nama_role'],
                        'nama_role' => $db_user['nama_role'],
                        'role_id' => $db_user['role_id'],
                        'department' => $db_user['nama_role']
                    ];
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Akun Anda tidak aktif!';
                }
            } else {
                $error = 'Email atau password salah!';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 p-10">
            <div class="text-center mb-8">
                <img src="assets/logo.png" alt="Logo RS Taman Harapan Baru" class="w-64 h-auto mx-auto mb-6">
                <h1 class="text-3xl font-extrabold text-gray-900 mb-2">RS Taman Harapan Baru</h1>
                <p class="text-gray-600 text-lg">Sistem Manajemen Internal</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-center">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" autocomplete="off">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3" for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        class="w-full px-5 py-4 border border-gray-300 rounded-xl text-gray-900 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all text-lg"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3" for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        class="w-full px-5 py-4 border border-gray-300 rounded-xl text-gray-900 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all text-lg"
                    >
                </div>

                <button 
                    type="submit"
                    class="w-full bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-bold py-4 px-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 text-xl"
                >
                    Masuk ke Sistem
                </button>
            </form>
        </div>
    </div>
</body>
</html>
