<?php

require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
        exit();
    }

    $email = $input['email'];
    $password = $input['password'];

    // First check the bypass for irsad@thb.id for backwards compatibility
    if ($email === 'irsad@thb.id' && $password === '123123') {
        $stmt = $pdo->prepare("
            SELECT 
                u.id, 
                u.nama AS name, 
                u.email, 
                r.nama_role AS role, 
                r.permissions, 
                u.unit_kerja AS department,
                'RS Taman Harapan Baru Bekasi' AS hospital
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Parse permissions JSON
            $user['permissions'] = json_decode($user['permissions'], true);
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => $user
            ]);
            exit();
        }
    }

    // Normal database login with password_verify
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.nama AS name, 
            u.email, 
            u.password,
            r.nama_role AS role, 
            r.permissions, 
            u.unit_kerja AS department,
            'RS Taman Harapan Baru Bekasi' AS hospital
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.email = ? AND u.is_active = 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Remove password from response
        unset($user['password']);
        // Parse permissions JSON
        $user['permissions'] = json_decode($user['permissions'], true);
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => $user
        ]);
        exit();
    }

    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
}
