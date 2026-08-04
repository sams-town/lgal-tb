<?php
session_start();
require_once 'config/database.php';

require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!hasPermission('sop_view')) {
    header("Location: dashboard.php");
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
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-3xl mx-auto space-y-6">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h1 class="text-2xl font-bold text-gray-900 mb-1">SOP & Template Dokumen</h1>
                    <p class="text-gray-500 text-sm">Cetak template dokumen Standar Prosedur Operasional (SPO)</p>
                </div>

                <!-- Cetak SPO kosong -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h2 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i>
                        Cetak Template Form SPO
                    </h2>
                    <form method="GET" action="cetak_spo.php" target="_blank" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Judul / Nama SPO</label>
                                <input type="text" name="judul" placeholder="Contoh: SPO Cuci Tangan" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No. Dokumen</label>
                                <input type="text" name="no_dokumen" placeholder="Contoh: SPO/001/2024" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No. Revisi</label>
                                <input type="text" name="no_revisi" placeholder="00" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Halaman</label>
                                <input type="text" name="halaman" value="1/1" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Terbit</label>
                                <input type="date" name="tgl_terbit" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unit Pembuat</label>
                                <input type="text" name="unit_pembuat" placeholder="Contoh: Instalasi Rawat Inap" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unit Terkait</label>
                                <input type="text" name="unit_terkait" placeholder="Contoh: Semua unit" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Direktur</label>
                                <input type="text" name="direktur" value="Dr. Andara Dwike, MARS, M.H., FISQua" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pengertian</label>
                            <textarea name="pengertian" rows="2" placeholder="Pengertian / definisi SPO..." class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tujuan</label>
                            <textarea name="tujuan" rows="2" placeholder="Tujuan dibuat SPO ini..." class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kebijakan</label>
                            <textarea name="kebijakan" rows="2" placeholder="Dasar kebijakan..." class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prosedur</label>
                            <textarea name="prosedur" rows="4" placeholder="Langkah-langkah prosedur..." class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="flex items-center gap-2 bg-blue-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:bg-blue-700 transition-colors text-sm shadow-md">
                                <i data-lucide="printer" class="w-4 h-4"></i> Buat & Cetak Template SPO
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Cetak kosong langsung -->
                <div class="bg-gray-50 rounded-2xl border border-gray-200 p-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Cetak Template SPO Kosong</p>
                        <p class="text-xs text-gray-500">Template dengan semua field kosong, isi manual setelah dicetak</p>
                    </div>
                    <a href="cetak_spo.php" target="_blank"
                       class="flex items-center gap-2 bg-white border border-blue-300 text-blue-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-blue-50 transition-colors">
                        <i data-lucide="file-plus" class="w-4 h-4"></i> Cetak Kosong
                    </a>
                </div>
            </div>
        </div>
    </main>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
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
