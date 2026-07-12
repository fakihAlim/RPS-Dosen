<?php
require_once __DIR__ . '/env_loader.php';

$host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?? 'rps_generator';
$user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?? 'root';
$pass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    try {
        // Coba koneksi langsung ke database (standar server produksi / shared hosting)
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Jika database belum ada, coba buat otomatis (hanya bekerja jika memiliki izin CREATE DATABASE)
        if ($e->getCode() == 1049 || stripos($e->getMessage(), 'Unknown database') !== false) {
            try {
                $dsnBase = "mysql:host=$host;charset=$charset";
                $pdo = new PDO($dsnBase, $user, $pass, $options);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$db`");
            } catch (PDOException $createEx) {
                throw new Exception("Koneksi database gagal: " . $createEx->getMessage());
            }
        } else {
            throw new Exception("Koneksi database gagal: " . $e->getMessage());
        }
    }
    
    // Periksa apakah tabel 'users' sudah ada, jika belum impor schema.sql
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() == 0) {
        $schemaPath = __DIR__ . '/schema.sql';
        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            $pdo->exec($sql);
        }
    }
    
    // Migrasi Skema Database: Tambahkan kolom baru jika belum ada
    // 1. Tabel rps
    $checkRps = $pdo->query("SHOW COLUMNS FROM rps LIKE 'no_dokumen'");
    if ($checkRps->rowCount() == 0) {
        $pdo->exec("ALTER TABLE rps ADD COLUMN no_dokumen VARCHAR(100) DEFAULT 'F-M2.STD-PD-3.6'");
        $pdo->exec("ALTER TABLE rps ADD COLUMN revisi VARCHAR(50) DEFAULT '02'");
        $pdo->exec("ALTER TABLE rps ADD COLUMN tanggal_penyusunan VARCHAR(100) DEFAULT '30 November 2023'");
        $pdo->exec("ALTER TABLE rps ADD COLUMN prasyarat VARCHAR(255) DEFAULT '-'");
        $pdo->exec("ALTER TABLE rps ADD COLUMN bahan_kajian TEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE rps ADD COLUMN referensi_utama TEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE rps ADD COLUMN referensi_pendukung TEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE rps ADD COLUMN sarana_umum TEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE rps ADD COLUMN sarana_khusus TEXT DEFAULT NULL");
    }

    $checkStatus = $pdo->query("SHOW COLUMNS FROM rps LIKE 'status'");
    if ($checkStatus->rowCount() == 0) {
        $pdo->exec("ALTER TABLE rps ADD COLUMN status VARCHAR(20) DEFAULT 'draft'");
    }

    $checkDeskripsi = $pdo->query("SHOW COLUMNS FROM rps LIKE 'deskripsi_mk'");
    if ($checkDeskripsi->rowCount() == 0) {
        $pdo->exec("ALTER TABLE rps ADD COLUMN deskripsi_mk TEXT DEFAULT NULL");
    }

    // 2. Tabel rps_meetings
    $checkMeetings = $pdo->query("SHOW COLUMNS FROM rps_meetings LIKE 'metode_luring'");
    if ($checkMeetings->rowCount() == 0) {
        $pdo->exec("ALTER TABLE rps_meetings ADD COLUMN metode_luring TEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE rps_meetings ADD COLUMN metode_daring TEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE rps_meetings ADD COLUMN indikator_penilaian TEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE rps_meetings ADD COLUMN bentuk_penilaian TEXT DEFAULT NULL");
    }

    // 3. Tabel users
    $checkEmail = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
    if ($checkEmail->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) UNIQUE DEFAULT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN verification_code VARCHAR(10) DEFAULT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN verification_expiry DATETIME DEFAULT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
    }

    $checkRole = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($checkRole->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user'");
    }

    $checkActive = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($checkActive->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    }

    $checkResetToken = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
    if ($checkResetToken->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_expiry DATETIME DEFAULT NULL");
    }

    // Seeding admin default jika belum ada admin
    $checkAdmin = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    if ($checkAdmin->rowCount() == 0) {
        $checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkUsername->execute(['admin']);
        $existingUser = $checkUsername->fetch();
        
        if ($existingUser) {
            $pdo->prepare("UPDATE users SET role = 'admin', is_verified = 1 WHERE id = ?")->execute([$existingUser['id']]);
        } else {
            $adminUsername = 'admin';
            $adminEmail = 'admin@example.com';
            $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, is_verified) VALUES (?, ?, ?, 'admin', 1)");
            $stmt->execute([$adminUsername, $adminEmail, $adminPassword]);
        }
    }

    // 4. Tabel email_logs
    $resultLogs = $pdo->query("SHOW TABLES LIKE 'email_logs'");
    if ($resultLogs->rowCount() == 0) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_logs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `to_email` VARCHAR(100) NOT NULL,
          `subject` VARCHAR(255) NOT NULL,
          `body` TEXT NOT NULL,
          `verification_code` VARCHAR(10) DEFAULT NULL,
          `status` VARCHAR(50) DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // 5. Tabel activity_logs
    $resultLogsActivity = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
    if ($resultLogsActivity->rowCount() == 0) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT DEFAULT NULL,
          `activity` VARCHAR(255) NOT NULL,
          `details` TEXT DEFAULT NULL,
          `ip_address` VARCHAR(45) DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
} catch (\PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

function encryptApiKey($key) {
    if (empty($key)) return null;
    $cipher = "AES-256-CBC";
    $secret = $_ENV['ENCRYPTION_KEY'] ?? 'fallback_secret_key_32_characters_long_!!!';
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext_raw = openssl_encrypt($key, $cipher, $secret, OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $ciphertext_raw, $secret, true);
    return base64_encode($iv . $hmac . $ciphertext_raw);
}

function decryptApiKey($encrypted) {
    if (empty($encrypted)) return '';
    
    $cipher = "AES-256-CBC";
    $secret = $_ENV['ENCRYPTION_KEY'] ?? 'fallback_secret_key_32_characters_long_!!!';
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
    
    // Fallback ke legacy AES-128-ECB
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
    } catch (\PDOException $e) {
        // Silently continue
    }
}
