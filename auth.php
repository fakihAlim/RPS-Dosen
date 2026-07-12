<?php
/**
 * Authentication & Session Management (Production)
 * Menggunakan Database-based Session Handler untuk kompatibilitas penuh dengan shared hosting.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Inisialisasi database session handler sebelum session_start()
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/session_handler.php';

    // Pastikan tabel php_sessions ada
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `php_sessions` (
            `id` VARCHAR(128) NOT NULL PRIMARY KEY,
            `data` TEXT NOT NULL,
            `expires_at` DATETIME NOT NULL,
            INDEX `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // Tabel mungkin sudah ada, abaikan
    }

    $handler = new DbSessionHandler($pdo);
    session_set_save_handler($handler, true);

    // Konfigurasi cookie session untuk keamanan produksi
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly'  => true,
        'samesite'  => 'Lax'
    ]);

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
        require_once __DIR__ . '/db.php';
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
