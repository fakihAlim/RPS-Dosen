<?php
if (session_status() === PHP_SESSION_NONE) {
    // Override folder penyimpanan session ke dalam proyek jika folder temp default di hosting tidak memiliki izin tulis
    $sessionPath = __DIR__ . '/sessions';
    
    // Toleransi jika user membuat folder 'session' (tunggal)
    if (!file_exists($sessionPath) && file_exists(__DIR__ . '/session')) {
        $sessionPath = __DIR__ . '/session';
    }
    
    if (!file_exists($sessionPath)) {
        @mkdir($sessionPath, 0777, true);
    }
    
    if (is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    global $pdo;
    if (!isset($pdo)) {
        require_once 'db.php';
    }

    try {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $is_active = $stmt->fetchColumn();

        if ($is_active === 0 || $is_active === '0') {
            $_SESSION = [];
            if (session_status() !== PHP_SESSION_NONE) {
                session_destroy();
            }
            header('Location: login.php?error=deactivated');
            exit;
        }
    } catch (PDOException $e) {
        // Abaikan jika error database agar tidak merusak alur aplikasi
    }
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}
