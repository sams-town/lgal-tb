<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOP & SDM - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-gradient-to-b from-emerald-800 to-emerald-900 text-white shadow-xl">
        <div class="p-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center text-emerald-800 font-bold text-xl">
                    🏥
                </div>
                <div>
                    <h1 class="text-lg font-bold">RS. Taman Harapan Baru</h1>
                    <p class="text-xs text-emerald-200">Legal & Corporate Secretary</p>
                </div>
            </div>
        </div>
        
        <nav class="p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">📊</span>
                <span>Dashboard</span>
            </a>
            <div class="space-y-1">
                <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📑</span>
                        <span>Legal</span>
                    </div>
                    <span>▼</span>
                </button>
                <div class="ml-4 space-y-1">
                    <a href="pks.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        Perjanjian Kerjasama (PKS)
                    </a>
                    <a href="regulasi.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        › Regulasi
                    </a>
                    <a href="perizinan.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        › Perizinan
                    </a>
                </div>
            </div>
            <div class="space-y-1">
                <button class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">✉️</span>
                        <span>Sekretariat</span>
                    </div>
                    <span>▼</span>
                </button>
                <div class="ml-4 space-y-1">
                    <a href="surat-masuk.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        Surat Masuk
                    </a>
                    <a href="surat-keluar.php" class="block px-4 py-2 rounded-lg text-sm transition-colors text-emerald-100 hover:bg-emerald-700">
                        Surat Keluar
                    </a>
                </div>
            </div>
            <a href="akreditasi.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">🏅</span>
                <span>Akreditasi & Mutu</span>
            </a>
            <a href="sop.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors bg-emerald-700">
                <span class="text-xl">📚</span>
                <span>SOP & SDM</span>
            </a>
            <a href="tenaga_medis.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">👨‍⚕️</span>
                <span>Komite</span>
            </a>
            <a href="corsec.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">🏛️</span>
                <span>Corporate Secretary</span>
            </a>
            <a href="audit_trail.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">🔍</span>
                <span>Audit Trail</span>
            </a>
            <a href="setting.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
                <span class="text-xl">⚙️</span>
                <span>Pengaturan</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
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
                <button class="text-gray-500 hover:text-emerald-600 transition-colors text-xl">🔔</button>
                <div class="flex items-center gap-3 bg-emerald-50 px-4 py-2 rounded-xl">
                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-full flex items-center justify-center text-white font-bold text-lg">
                        <?php 
                        $user_name = $user['name'] ?? $user['nama'] ?? 'Guest';
                        echo htmlspecialchars(substr($user_name, 0, 1)); 
                        ?>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-semibold text-gray-800"><?php 
                        $user_name = $user['name'] ?? $user['nama'] ?? 'Guest';
                        echo htmlspecialchars($user_name); 
                        ?></p>
                        <p class="text-xs text-gray-500"><?php 
                        $user_role = $user['role'] ?? $user['nama_role'] ?? 'Guest';
                        echo htmlspecialchars($user_role); 
                        ?></p>
                    </div>
                    <a href="logout.php" class="text-sm text-red-600 hover:text-red-700 font-medium">Logout</a>
                </div>
            </div>
        </header>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">SOP & SDM</h1>
                    <p class="text-gray-600 mt-2">Dokumen Standar Operasional Prosedur dan Manajemen SDM</p>
                </div>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                    <div class="text-9xl mb-4 opacity-30">📚</div>
                    <h3 class="text-2xl font-semibold text-gray-700 mb-2">Modul SOP & SDM</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Fitur manajemen dokumen SOP dan data SDM akan ditambahkan di sini.</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
