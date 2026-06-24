<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';

// Step Configuration
$stepConfig = [
    ['id' => 'km', 'name' => 'Komite Medik (KM)', 'pin' => '000000', 'role' => 'Super Admin'],
    ['id' => 'lg', 'name' => 'Legal (LG)', 'pin' => '222222', 'role' => 'Staf Legal'],
    ['id' => 'sk', 'name' => 'Sekretariat (SK)', 'pin' => '123456', 'role' => 'Staf Sekretariat'],
    ['id' => 'dk', 'name' => 'Direktur Keuangan (DK)', 'pin' => '222222', 'role' => 'Direktur Keuangan'],
    ['id' => 'du', 'name' => 'Direktur Utama (DU)', 'pin' => '111111', 'role' => 'Direktur Utama']
];

// Initialize sample documents data if table doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS approval_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            proposer VARCHAR(255) NOT NULL,
            date DATE NOT NULL,
            step_status JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert sample data if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM approval_documents");
    if (false && $stmt->fetchColumn() == 0) {
        $initialStepStatus = json_encode([
            'km' => 'pending',
            'lg' => 'pending',
            'sk' => 'pending',
            'dk' => 'pending',
            'du' => 'pending'
        ]);
        
        $stmt = $pdo->prepare("
            INSERT INTO approval_documents (name, proposer, date, step_status)
            VALUES 
                ('Perjanjian Kerjasama Mitra Klinik Utama', 'Dr. Andi Wijaya', '2026-06-05', ?),
                ('SOP Penanganan Pasien Gawat Darurat', 'Nurse Ratna', '2026-06-08', ?),
                ('Pengadaan Alat Medis Laboratorium', 'Divisi Keuangan', '2026-06-09', ?)
        ");
        $stmt->execute([$initialStepStatus, $initialStepStatus, $initialStepStatus]);
    }
} catch (PDOException $e) {
    // Continue, sample data not critical
}

// Handle PIN submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pin'])) {
    $documentId = (int)$_POST['document_id'];
    $enteredPin = $_POST['pin'];
    $stepId = $_POST['step_id'];

    // Find current step to check PIN
    $validPin = false;
    foreach ($stepConfig as $step) {
        if ($step['id'] === $stepId && $step['pin'] === $enteredPin) {
            $validPin = true;
            break;
        }
    }

    if ($validPin) {
        // Get current statuses
        $stmt = $pdo->prepare("SELECT step_status FROM approval_documents WHERE id = ?");
        $stmt->execute([$documentId]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            $stepStatus = json_decode($doc['step_status'], true);
            $stepStatus[$stepId] = 'approved';
            
            $stmt = $pdo->prepare("
                UPDATE approval_documents 
                SET step_status = ? 
                WHERE id = ?
            ");
            $stmt->execute([json_encode($stepStatus), $documentId]);
            
            $message = '<div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700">Dokumen berhasil ditandatangani!</div>';
        }
    } else {
        $message = '<div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">PIN tidak valid!</div>';
    }
}

// Get all documents
$documents = [];
try {
    $stmt = $pdo->query("SELECT * FROM approval_documents ORDER BY created_at DESC");
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    // No problem if table not ready yet
}

// Function to render step indicator
function renderStepIndicator($stepId, $stepStatus, $currentStep = null) {
    $status = $stepStatus[$stepId] ?? 'pending';
    $icon = '';
    $colorClass = '';
    
    if ($status === 'approved') {
        $icon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>';
        $colorClass = 'bg-emerald-500 text-white border-emerald-500';
    } elseif ($status === 'rejected') {
        $icon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>';
        $colorClass = 'bg-red-500 text-white border-red-500';
    } else {
        $icon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        $colorClass = 'bg-amber-500 text-white border-amber-500';
    }

    return '<div class="w-8 h-8 flex items-center justify-center rounded-full border-2 ' . $colorClass . '">' . $icon . '</div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan & E-Signature - RS Taman Harapan Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal-overlay { background-color: rgba(0,0,0,0.5); }
    </style>
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
            <a href="approval.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors bg-emerald-700">
                <span class="text-xl">✍️</span>
                <span>Persetujuan & E-Sign</span>
            </a>
            <a href="sop.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-emerald-100 hover:bg-emerald-700">
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
                        <?php echo htmlspecialchars(substr($user['name'], 0, 1)); ?>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['role']); ?></p>
                    </div>
                    <a href="logout.php" class="text-sm text-red-600 hover:text-red-700 font-medium">Logout</a>
                </div>
            </div>
        </header>
        
        <!-- Page Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Persetujuan & E-Signature Dokumen</h1>
                        <p class="text-gray-600 mt-2">Alur tanda tangan berjenjang untuk dokumen rumah sakit</p>
                    </div>
                </div>

                <?php echo $message; ?>

                <!-- Table of Approvals -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">No</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Nama Dokumen/Pengajuan</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Pengusul</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Tanggal</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Progress Alur Tanda Tangan</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 border-b">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        Belum ada dokumen yang memerlukan persetujuan
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documents as $index => $doc): ?>
                                    <?php $stepStatus = json_decode($doc['step_status'], true); ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-gray-600"><?php echo $index + 1; ?></td>
                                        <td class="px-6 py-4">
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['name']); ?></p>
                                        </td>
                                        <td class="px-6 py-4 text-gray-700"><?php echo htmlspecialchars($doc['proposer']); ?></td>
                                        <td class="px-6 py-4 text-gray-700"><?php echo date('d/m/Y', strtotime($doc['date'])); ?></td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <?php foreach ($stepConfig as $i => $step): ?>
                                                    <?php echo renderStepIndicator($step['id'], $stepStatus); ?>
                                                    <?php if ($i < count($stepConfig) - 1): ?>
                                                        <div class="w-8 h-0.5 bg-gray-300"></div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="flex items-center gap-2 mt-2 text-xs text-gray-500">
                                                <?php foreach ($stepConfig as $step): ?>
                                                    <span class="text-center w-10 truncate"><?php echo strtoupper($step['id']); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <button 
                                                onclick="openModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($doc['proposer'], ENT_QUOTES); ?>', '<?php echo $doc['date']; ?>')"
                                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-medium transition-colors"
                                            >
                                                Review & Sign
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Review & Sign Modal -->
    <div id="signModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0" onclick="closeModal()"></div>
        <div class="relative z-10 flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden">
                <div class="p-8 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="text-2xl font-bold text-gray-900">Review & E-Signature Dokumen</h2>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button>
                </div>
                <form id="signForm" method="POST" class="p-8 space-y-6">
                    <input type="hidden" name="document_id" id="modalDocId">
                    
                    <!-- Document Details -->
                    <div class="bg-gray-50 rounded-2xl p-6 space-y-4">
                        <div>
                            <p class="text-sm text-gray-500">Nama Dokumen</p>
                            <p id="modalDocName" class="font-semibold text-gray-900 text-lg"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Pengusul</p>
                            <p id="modalProposer" class="font-medium text-gray-800"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Tanggal Pengajuan</p>
                            <p id="modalDate" class="font-medium text-gray-800"></p>
                        </div>
                    </div>

                    <!-- Step Selection -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Pilih Langkah Tanda Tangan</label>
                        <select name="step_id" id="modalStepId" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                            <?php foreach ($stepConfig as $step): ?>
                                <option value="<?php echo $step['id']; ?>">
                                    <?php echo $step['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- PIN Input -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">PIN Keamanan (6 Digit Angka)</label>
                        <input 
                            type="password" 
                            name="pin" 
                            id="modalPin" 
                            maxlength="6" 
                            pattern="[0-9]{6}"
                            required 
                            placeholder="Masukkan 6 digit PIN"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all text-xl tracking-widest"
                        >
                    </div>

                    <!-- Demo PIN Info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700">
                        <p class="font-semibold mb-2">📌 PIN Demo untuk Testing:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Super Admin / Komite Medik (KM): <strong>000000</strong></li>
                            <li>Legal (LG) / Direktur Keuangan (DK): <strong>222222</strong></li>
                            <li>Direktur Utama (DU): <strong>111111</strong></li>
                        </ul>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-100">
                        <button type="button" onclick="closeModal()" class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-medium transition-colors">
                            Batal
                        </button>
                        <button type="submit" name="submit_pin" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-medium transition-colors">
                            Verifikasi & Tanda Tangan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal(docId, docName, proposer, date) {
            document.getElementById('modalDocId').value = docId;
            document.getElementById('modalDocName').textContent = docName;
            document.getElementById('modalProposer').textContent = proposer;
            document.getElementById('modalDate').textContent = new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            document.getElementById('signModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('signModal').classList.add('hidden');
        }
    </script>
</body>
</html>
