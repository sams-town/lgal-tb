<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Deteksi Otomatis: Apakah web dibuka di Laptop atau Server Hosting cPanel
if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['HTTP_HOST'] == 'localhost') {
    // 💻 SETELAN UNTUK LAPTOP ANDA (Laragon/XAMPP)
    $host = 'localhost';
    $dbname = 'new_legal';
    $username = 'root';
    $password = '';
} else {
    // 🌐 SETELAN UNTUK SERVER CPANEL ONLINE (Menggunakan garis bawah '_' bukan '-')
    $host = 'localhost';
    $dbname = 'rsthbid_admin_legal'; 
    $username = 'rsthbid_user_legal';  
    $password = 'samboja90';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}