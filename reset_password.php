<?php
require_once 'db.php';
require_once 'auth.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = '';
$user = null;

if (empty($token)) {
    $error = 'Token reset tidak valid atau tidak ditemukan.';
} else {
    try {
        // Cari token di database dan pastikan belum kedaluwarsa
        $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expiry >= ?");
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Tautan reset kata sandi tidak valid atau telah kedaluwarsa. Silakan ajukan permintaan lupa password baru.';
        }
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Kedua kolom password wajib diisi.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password minimal harus 6 karakter.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            
            // Update password dan bersihkan token
            $updateStmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL, is_verified = 1 WHERE id = ?");
            $updateStmt->execute([$hashed, $user['id']]);

            // Catat log aktivitas
            logActivity($user['id'], 'RESET_PASSWORD_SUCCESS', 'Kata sandi berhasil diperbarui melalui token reset');

            $_SESSION['success_message'] = 'Kata sandi Anda berhasil diubah. Silakan masuk kembali dengan password baru Anda.';
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan kata sandi baru: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - RPS Generator AI</title>
    <link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
    <div class="app-container">
        <div class="card auth-card">
            <div class="auth-header">
                <a href="login.php" class="logo" style="justify-content: center; margin-bottom: 1rem;">
                    RPS Generator AI
                </a>
                <h2>Reset Kata Sandi</h2>
                <p>Masukkan kata sandi baru untuk mengamankan akun Anda</p>
            </div>

            <?php if (!empty($error) && !$user): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
                <div style="text-align: center;">
                    <a href="forgot_password.php" class="btn btn-secondary btn-sm" style="display: inline-block;">Minta Tautan Reset Baru</a>
                </div>
            <?php else: ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="reset_password.php" method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="form-group" style="margin-bottom: 1.2rem;">
                        <label class="form-label" for="new_password">Kata Sandi Baru</label>
                        <input class="form-control" type="password" id="new_password" name="new_password" placeholder="Minimal 6 karakter" required minlength="6">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.8rem;">
                        <label class="form-label" for="confirm_password">Konfirmasi Kata Sandi</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi kata sandi baru" required>
                    </div>

                    <button class="btn btn-primary" type="submit" style="width: 100%;">Perbarui Kata Sandi</button>
                </form>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
                Kembali ke <a href="login.php" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Halaman Login</a>
            </div>
        </div>
    </div>
</body>
</html>
