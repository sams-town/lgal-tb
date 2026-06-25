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
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        <?php include 'includes/header.php'; ?>
        
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
