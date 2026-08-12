<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>Debug Komite Medik</h2>";

// Test 1: Database connection
try {
    require_once 'config/database.php';
    echo "<p style='color:green'>✅ Database OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Database Error: " . $e->getMessage() . "</p>";
    exit;
}

// Test 2: Functions
try {
    require_once 'includes/functions.php';
    echo "<p style='color:green'>✅ Functions OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Functions Error: " . $e->getMessage() . "</p>";
    exit;
}

// Test 3: Query tenaga_medis
try {
    $stmt = $pdo->prepare("SELECT * FROM tenaga_medis WHERE tipe_form = 'komite-medik' ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $data = $stmt->fetchAll();
    echo "<p style='color:green'>✅ Query OK - " . count($data) . " baris</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Query Error: " . $e->getMessage() . "</p>";
}

// Test 4: Cek kolom tabel tenaga_medis
try {
    $cols = $pdo->query("SHOW COLUMNS FROM tenaga_medis")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p style='color:green'>✅ Kolom tersedia: " . implode(', ', $cols) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Kolom Error: " . $e->getMessage() . "</p>";
}

// Test 5: Cek apakah ada syntax error dengan include file
echo "<hr><h3>Test include komite-medik.php</h3>";
ob_start();
try {
    // Simulate session
    session_start();
    if (!isset($_SESSION['user'])) {
        $_SESSION['user'] = ['id'=>1,'nama'=>'Test','nama_role'=>'Super Admin'];
    }
    // Check for parse errors by compiling
    $code = file_get_contents('komite-medik.php');
    $result = eval('return true; ?>' . substr($code, 5)); // skip <?php
    echo "<p style='color:green'>✅ No parse error</p>";
} catch (ParseError $e) {
    echo "<p style='color:red'>❌ Parse Error: " . $e->getMessage() . " on line " . $e->getLine() . "</p>";
} catch (Error $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . " on line " . $e->getLine() . "</p>";
}
ob_end_clean();

echo "<hr><p>Debug selesai. <a href='komite-medik.php'>Coba buka Komite Medik</a></p>";
?>
