<?php
/**
 * Database Connection & Utility Functions (Production)
 * Koneksi langsung ke database yang telah dikonfigurasi di .env.
 * Tidak ada auto-migration atau CREATE DATABASE di sini.
 */

require_once __DIR__ . '/env_loader.php';

$host    = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$db      = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'rps_generator';
$user    = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$pass    = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Pesan error generik untuk produksi — tidak mengekspos detail koneksi
    error_log("Database connection failed: " . $e->getMessage());
    die("Terjadi kesalahan koneksi ke server. Silakan hubungi administrator.");
}

// ============================================================
// Utility Functions
// ============================================================

function encryptApiKey($key) {
    if (empty($key)) return null;
    $cipher = "AES-256-CBC";
    $secret = $_ENV['ENCRYPTION_KEY'] ?? $_SERVER['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY') ?: 'fallback_secret_key_32_characters_long_!!!';
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext_raw = openssl_encrypt($key, $cipher, $secret, OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $ciphertext_raw, $secret, true);
    return base64_encode($iv . $hmac . $ciphertext_raw);
}

function decryptApiKey($encrypted) {
    if (empty($encrypted)) return '';
    
    $cipher = "AES-256-CBC";
    $secret = $_ENV['ENCRYPTION_KEY'] ?? $_SERVER['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY') ?: 'fallback_secret_key_32_characters_long_!!!';
    $c = base64_decode($encrypted, true);
    
    if ($c !== false) {
        $ivlen = openssl_cipher_iv_length($cipher);
        if (strlen($c) > $ivlen + 32) {
            $iv = substr($c, 0, $ivlen);
            $hmac = substr($c, $ivlen, 32);
            $ciphertext_raw = substr($c, $ivlen + 32);
            $calcmac = hash_hmac('sha256', $ciphertext_raw, $secret, true);
            if (hash_equals($hmac, $calcmac)) {
                $decrypted = openssl_decrypt($ciphertext_raw, $cipher, $secret, OPENSSL_RAW_DATA, $iv);
                if ($decrypted !== false) {
                    return $decrypted;
                }
            }
        }
    }
    
    // Fallback ke legacy AES-128-ECB (untuk kompatibilitas data lama)
    $legacy_cipher = "AES-128-ECB";
    $legacy_key = 'rps_generator_secure_key_12984029472093847';
    $decrypted_legacy = openssl_decrypt($encrypted, $legacy_cipher, $legacy_key);
    return $decrypted_legacy !== false ? $decrypted_legacy : '';
}

function logActivity($user_id, $activity, $details = null) {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $activity, $details, $ip]);
    } catch (PDOException $e) {
        // Silently continue — logging failure should not break the app
    }
}
