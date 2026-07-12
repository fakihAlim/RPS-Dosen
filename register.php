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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Username, email, dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        if (should_email_be_blocked($email)) {
            $error = 'Email dari penyedia domain sementara (disposable email) atau tidak valid tidak diperbolehkan.';
        } elseif (strlen($password) < 6) {
            $error = 'Password harus minimal 6 karakter.';
        } elseif ($password !== $confirm_password) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
        try {
            // Cek apakah username sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username sudah digunakan.';
            } else {
                // Cek apakah email sudah terdaftar
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email sudah digunakan.';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    
                    // Generate OTP 6-digit
                    $code = (string)mt_rand(100000, 999999);
                    $expiry = date('Y-m-d H:i:s', time() + 900); // 15 menit
                    
                    // Simpan ke database dengan is_verified = 0
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, verification_code, verification_expiry, is_verified) VALUES (?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$username, $email, $hashed_password, $code, $expiry]);
                    $new_user_id = $pdo->lastInsertId();
                    
                    // Catat aktivitas pendaftaran
                    logActivity($new_user_id, 'REGISTER', 'User berhasil mendaftar akun baru');
                    
                    // Kirim email verifikasi via SMTP
                    sendVerificationEmail($email, $username, $code);
                    
                    // Simpan email ke session untuk verifikasi
                    $_SESSION['verification_email'] = $email;
                    $_SESSION['last_verification_code'] = $code; // helper dev
                    
                    header('Location: verify.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Dosen - RPS Generator AI</title>
    <link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
    <div class="app-container">
        <div class="card auth-card">
            <div class="auth-header">
                <a href="#" class="logo" style="justify-content: center; margin-bottom: 1rem;">
                    RPS Generator AI
                </a>
                <h2>Daftar Akun Dosen</h2>
                <p>Mulai membuat RPS terstandarisasi dengan bantuan AI</p>
            </div>

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

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" type="text" id="username" name="username" placeholder="Masukkan username" required value="<?= isset($username) ? htmlspecialchars($username) : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" placeholder="Masukkan email aktif" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" placeholder="Masukkan password (min. 6 karakter)" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Konfirmasi Password</label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
                </div>

                <button class="btn btn-primary" type="submit" style="width: 100%; margin-top: 1rem;">Daftar Sekarang</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
                Sudah punya akun? <a href="login.php" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Login disini</a>
            </div>
        </div>
    </div>
</body>
</html>
