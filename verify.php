<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'mailer.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$email = $_SESSION['verification_email'] ?? trim($_GET['email'] ?? '');
if (empty($email)) {
    header('Location: register.php');
    exit;
}

// Cek apakah akun terdaftar
try {
    $stmt = $pdo->prepare("SELECT username, is_verified, verification_code, verification_expiry FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: register.php');
        exit;
    }
    
    // Jika sudah diverifikasi, langsung arahkan ke login
    if ($user['is_verified'] == 1) {
        $_SESSION['success_message'] = 'Akun Anda sudah terverifikasi. Silakan login.';
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    die('Kesalahan Sistem: ' . $e->getMessage());
}

$error = '';
$success = '';

// Proses Verifikasi Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $error = 'Silakan masukkan kode verifikasi.';
    } else {
        $db_code = $user['verification_code'] ?? '';
        $expiry = $user['verification_expiry'] ?? '';
        
        if ($db_code === $code && strtotime($expiry) >= time()) {
            try {
                // Update status verifikasi
                $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, verification_expiry = NULL WHERE email = ?");
                $stmt->execute([$email]);
                
                // Hapus data penolong di session
                unset($_SESSION['verification_email']);
                unset($_SESSION['last_verification_code']);
                
                $_SESSION['success_message'] = 'Akun Anda berhasil diverifikasi! Silakan masuk.';
                header('Location: login.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Gagal memproses verifikasi: ' . $e->getMessage();
            }
        } else {
            if ($db_code !== $code) {
                $error = 'Kode verifikasi yang Anda masukkan salah.';
            } else {
                $error = 'Kode verifikasi telah kedaluwarsa. Silakan kirim ulang kode.';
            }
        }
    }
}

// Proses Kirim Ulang Kode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $code = (string)mt_rand(100000, 999999);
    $expiry = date('Y-m-d H:i:s', time() + 900); // 15 menit
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expiry = ? WHERE email = ? AND is_verified = 0");
        $stmt->execute([$code, $expiry, $email]);
        
        if ($stmt->rowCount() > 0) {
            $username = $user['username'] ?? 'Dosen';
            
            // Kirim email verifikasi via SMTP
            sendVerificationEmail($email, $username, $code);
            
            // Simpan ke session untuk helper dan refresh data user di memori
            $_SESSION['last_verification_code'] = $code;
            
            // Perbarui data user lokal agar pengecekan instan POST setelah resend valid
            $user['verification_code'] = $code;
            $user['verification_expiry'] = $expiry;
            
            $success = 'Kode verifikasi baru telah dikirim ke email Anda.';
        } else {
            $error = 'Gagal mengirim ulang kode.';
        }
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    }
}

// Cek apakah diakses dari localhost untuk helper
$is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || strpos($_SERVER['HTTP_HOST'], '.local') !== false;
// Tampilkan helper kode di layar localhost HANYA jika SMTP belum diatur atau gagal terkirim
$is_smtp_configured = (SMTP_USER !== 'your-email@gmail.com' && SMTP_PASS !== 'your-app-password-here' && !isset($_SESSION['mail_fallback_active']));
$show_helper = $is_localhost && !$is_smtp_configured;
$helper_code = $_SESSION['last_verification_code'] ?? $user['verification_code'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - RPS Generator AI</title>
    <link rel="stylesheet" href="assets/css/style.css?v=6">
    <style>
        .code-input {
            letter-spacing: 0.5rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .dev-notice {
            background-color: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.2);
            color: #818cf8;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="card auth-card">
            <div class="auth-header">
                <a href="#" class="logo" style="justify-content: center; margin-bottom: 1rem;">
                    RPS Generator AI
                </a>
                <h2>Verifikasi Email</h2>
                <p>Kami telah mengirimkan kode OTP 6-digit ke email:</p>
                <p style="color: var(--text-primary); font-weight: 600; margin-top: 0.25rem;">
                    <?= htmlspecialchars($email) ?>
                </p>
            </div>

            <?php if ($show_helper && !empty($helper_code)): ?>
                <div class="dev-notice">
                    <strong>[Dev Helper Notice]</strong><br>
                    Kode verifikasi Anda adalah: <span style="font-size: 1.1rem; color: #fff; font-weight: bold; background: rgba(0,0,0,0.2); padding: 0.1rem 0.5rem; border-radius: 4px;"><?= htmlspecialchars($helper_code) ?></span><br>
                    <span style="font-size: 0.75rem; opacity: 0.8;">(Hanya muncul di localhost untuk mempermudah pengujian)</span>
                </div>
            <?php endif; ?>

            <?php 
            $smtp_fallback = false;
            if (isset($_SESSION['mail_fallback_active'])) {
                $smtp_fallback = true;
                unset($_SESSION['mail_fallback_active']);
            }
            if ($smtp_fallback): 
            ?>
                <div class="dev-notice" style="background-color: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); color: var(--warning);">
                    <strong>[Koneksi SMTP Belum Siap]</strong><br>
                    Email nyata gagal terkirim karena kredensial SMTP belum diatur di <b>[mail_config.php](file:///c:/laragon/www/RPS%20Generator/mail_config.php)</b>.<br>
                    Silakan gunakan kode di atas atau periksa tabel database <b>`email_logs`</b>.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form action="verify.php" method="POST" style="margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" for="code" style="text-align: center;">Masukkan Kode OTP</label>
                    <input class="form-control code-input" type="text" id="code" name="code" maxlength="6" pattern="\d{6}" placeholder="------" required autocomplete="one-time-code">
                </div>

                <button class="btn btn-primary" type="submit" name="verify" style="width: 100%; margin-top: 0.5rem;">Verifikasi Akun</button>
            </form>

            <div style="text-align: center; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">Tidak menerima kode?</p>
                <form action="verify.php" method="POST">
                    <button class="btn btn-secondary btn-sm" type="submit" name="resend" style="width: 100%;">Kirim Ulang Kode</button>
                </form>
            </div>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
                Kembali ke <a href="register.php" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Pendaftaran</a> atau <a href="login.php" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Login</a>
            </div>
        </div>
    </div>
</body>
</html>
