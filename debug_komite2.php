<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = ['id'=>1,'nama'=>'Test','nama_role'=>'Super Admin'];
}

// Coba load file line by line untuk cari error
$file = file_get_contents('komite-medik.php');
$tokens = token_get_all($file);
echo "<p>File loaded OK, " . strlen($file) . " bytes</p>";

// Test syntax
$result = @eval('?>' . $file);
if ($result === false) {
    echo "<p style='color:red'>❌ Eval error</p>";
}

// Check for specific issues
$lines = explode("\n", $file);
echo "<p>Total lines: " . count($lines) . "</p>";

// Find potential issues
foreach ($lines as $num => $line) {
    // Check for unmatched braces
    if (preg_match('/\$jabatanKeperawatanOptions/', $line)) {
        echo "<p style='color:orange'>Line " . ($num+1) . ": " . htmlspecialchars(trim($line)) . "</p>";
    }
}

echo "<hr><h3>Cek variabel yang mungkin undefined</h3>";
// Test query yang sama dengan komite-medik
try {
    $stmt = $pdo->prepare("SELECT * FROM tenaga_medis WHERE tipe_form = 'komite-medik' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row) {
        echo "<p style='color:green'>✅ Data ada, sample: " . htmlspecialchars($row['nama_lengkap']) . "</p>";
        // Check jenis_berkas column
        echo "<p>jenis_berkas value: " . var_export($row['jenis_berkas'] ?? 'COLUMN NOT EXIST', true) . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
