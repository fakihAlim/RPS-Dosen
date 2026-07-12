<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'mailer.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'deactivated') {
    $error = 'Akun Anda dinonaktifkan oleh administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Periksa apakah user dinonaktifkan
                if (isset($user['is_active']) && $user['is_active'] == 0) {
                    logActivity($user['id'], 'LOGIN_FAILED', 'User dinonaktifkan mencoba login');
                    $error = 'Akun Anda dinonaktifkan oleh administrator.';
                } else {
                    // Deteksi Setup Awal Admin (jika email masih bawaan admin@example.com)
                    if ($user['role'] === 'admin' && $user['email'] === 'admin@example.com') {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        logActivity($user['id'], 'LOGIN_SUCCESS', 'Admin masuk via akun default (diperlukan setup email)');
                        header('Location: admin_setup.php');
                        exit;
                    }

                    // Periksa status verifikasi email (kecuali jika email kosong/null untuk backwards-compatibility)
                    if ($user['email'] !== null && $user['is_verified'] != 1) {
                        $_SESSION['verification_email'] = $user['email'];
                        
                        // Generate kode OTP baru jika kosong atau sudah kedaluwarsa
                        if (empty($user['verification_code']) || strtotime($user['verification_expiry']) < time()) {
                            $code = (string)mt_rand(100000, 999999);
                            $expiry = date('Y-m-d H:i:s', time() + 900); // 15 menit
                            
                            $updateStmt = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expiry = ? WHERE id = ?");
                            $updateStmt->execute([$code, $expiry, $user['id']]);
                            
                            // Kirim email verifikasi via SMTP
                            sendVerificationEmail($user['email'], $user['username'], $code);
                            
                            $_SESSION['last_verification_code'] = $code;
                        } else {
                            $_SESSION['last_verification_code'] = $user['verification_code'];
                        }
                        
                        header('Location: verify.php');
                        exit;
                    }

                    // Set session jika terverifikasi dan aktif
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'] ?? 'user';
                    
                    logActivity($user['id'], 'LOGIN_SUCCESS', 'Berhasil masuk aplikasi');
                    
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                logActivity(null, 'LOGIN_FAILED', 'Gagal masuk untuk username: ' . $username);
                $error = 'Username atau password salah.';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RPS Generator AI</title>
    <link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
    <div class="app-container">
        <div class="card auth-card">
            <div class="auth-header">
                <a href="#" class="logo" style="justify-content: center; margin-bottom: 1rem;">
                    RPS Generator AI
                </a>
                <h2>Masuk Akun Dosen</h2>
                <p>Silakan masuk untuk mengelola RPS Anda</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php 
            $session_success = $_SESSION['success_message'] ?? '';
            unset($_SESSION['success_message']);
            if (!empty($session_success)): 
            ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($session_success) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" type="text" id="username" name="username" placeholder="Masukkan username" required value="<?= isset($username) ? htmlspecialchars($username) : '' ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" placeholder="Masukkan password" required>
                </div>
                
                <div style="text-align: right; margin-bottom: 1.5rem; font-size: 0.85rem;">
                    <a href="forgot_password.php" style="color: var(--text-muted); text-decoration: none;">Lupa Password?</a>
                </div>

                <button class="btn btn-primary" type="submit" style="width: 100%;">Masuk</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
                Belum punya akun? <a href="register.php" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Daftar disini</a>
            </div>
        </div>
    </div>
</body>
</html>
