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
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate secure random reset token
                $token = bin2hex(random_bytes(16));
                $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 jam

                $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
                $updateStmt->execute([$token, $expiry, $user['id']]);

                // Buat tautan reset dinamis
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $reset_link = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

                // Format Email HTML
                $subject = "Reset Kata Sandi Anda - RPS Generator AI";
                $htmlContent = "
                <html>
                <body style='font-family: Tahoma, sans-serif; background-color: #f8fafc; color: #0f172a; padding: 20px;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 30px;'>
                        <h2 style='color: #2563eb; margin-top: 0;'>Reset Kata Sandi Akun Anda</h2>
                        <p>Halo, <strong>" . htmlspecialchars($user['username']) . "</strong>!</p>
                        <p>Kami menerima permintaan untuk mereset kata sandi akun Anda di RPS Generator AI.</p>
                        <p>Silakan klik tombol di bawah ini untuk mengatur ulang kata sandi Anda. Tautan ini hanya berlaku selama 1 jam:</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='" . $reset_link . "' style='background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block;'>Reset Kata Sandi</a>
                        </p>
                        <p style='font-size: 0.85rem; color: #475569;'>Jika tombol tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:</p>
                        <p style='font-size: 0.85rem; color: #2563eb; word-break: break-all;'>" . $reset_link . "</p>
                        <hr style='border: none; border-top: 1px solid #cbd5e1; margin: 20px 0;'>
                        <p style='font-size: 0.85rem; color: #475569; margin-bottom: 0;'>Jika Anda tidak meminta perubahan ini, Anda dapat mengabaikan email ini dengan aman.</p>
                    </div>
                </body>
                </html>
                ";

                $textContent = "Halo, " . $user['username'] . "!\n\n" .
                               "Kami menerima permintaan untuk mereset kata sandi akun Anda di RPS Generator AI.\n" .
                               "Klik tautan berikut untuk mereset kata sandi Anda (berlaku 1 jam):\n" .
                               $reset_link . "\n\n" .
                               "Terima kasih.";

                $sent = false;
                
                // Kirim lewat SMTP
                $mailer = new SmtpMailer();
                $sent = $mailer->send($email, $subject, $htmlContent, $textContent);

                // Catat log email
                $log_id = null;
                try {
                    $stmtLog = $pdo->prepare("INSERT INTO email_logs (to_email, subject, body, verification_code, status) VALUES (?, ?, ?, ?, ?)");
                    $stmtLog->execute([$email, $subject, $htmlContent, $token, 'sending']);
                    $log_id = $pdo->lastInsertId();
                } catch (PDOException $e) {}

                if ($sent) {
                    $success = 'Tautan reset kata sandi telah dikirim ke email Anda.';
                    logActivity($user['id'], 'FORGOT_PASSWORD_REQUEST', 'Meminta tautan reset sandi (Email terkirim)');
                } else {
                    $success = 'Koneksi SMTP tidak siap. Sebagai simulasi lokal, tautan reset dicatat di database.';
                    $_SESSION['mail_fallback_active'] = true;
                    logActivity($user['id'], 'FORGOT_PASSWORD_REQUEST', 'Meminta tautan reset sandi (SMTP Gagal, dicatat di log DB)');
                }

                if ($log_id) {
                    try {
                        $updateLog = $pdo->prepare("UPDATE email_logs SET status = ? WHERE id = ?");
                        $updateLog->execute([$sent ? 'sent' : 'failed', $log_id]);
                    } catch (PDOException $e) {}
                }
            } else {
                // Jangan bocorkan apakah email terdaftar demi keamanan, tapi beri pesan ramah
                $success = 'Jika email terdaftar di sistem, tautan reset kata sandi akan dikirim ke email tersebut.';
                logActivity(null, 'FORGOT_PASSWORD_REQUEST', 'Permintaan reset sandi email tidak terdaftar: ' . $email);
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
    <title>Lupa Password - RPS Generator AI</title>
    <link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
    <div class="app-container">
        <div class="card auth-card">
            <div class="auth-header">
                <a href="login.php" class="logo" style="justify-content: center; margin-bottom: 1rem;">
                    RPS Generator AI
                </a>
                <h2>Lupa Kata Sandi?</h2>
                <p>Masukkan email terdaftar Anda untuk mendapatkan tautan reset password</p>
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

            <?php
            // Fallback untuk localhost jika SMTP belum diatur
            $smtp_fallback = false;
            if (isset($_SESSION['mail_fallback_active'])) {
                $smtp_fallback = true;
                unset($_SESSION['mail_fallback_active']);
            }
            if ($smtp_fallback): 
                // Cari token terakhir untuk email input
                try {
                    $stmtToken = $pdo->prepare("SELECT reset_token FROM users WHERE email = ?");
                    $stmtToken->execute([$email]);
                    $latest_token = $stmtToken->fetchColumn();
                } catch (PDOException $e) {
                    $latest_token = '';
                }
                
                if ($latest_token):
                    $simulated_link = "reset_password.php?token=" . $latest_token;
            ?>
                <div class="dev-notice" style="background-color: rgba(245, 158, 11, 0.1); border: 1px solid #cbd5e1; color: var(--warning); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; line-height: 1.4;">
                    <strong>[Koneksi SMTP Belum Siap]</strong><br>
                    Email nyata gagal terkirim karena kredensial SMTP belum diatur.<br>
                    Tautan Reset Lokal: <a href="<?= htmlspecialchars($simulated_link) ?>" style="color: var(--accent-primary); font-weight: bold; text-decoration: underline;">Klik di sini untuk Reset Sandi</a>
                </div>
            <?php endif; endif; ?>

            <form action="forgot_password.php" method="POST">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" for="email">Alamat Email</label>
                    <input class="form-control" type="email" id="email" name="email" placeholder="Contoh: dosen@kampus.ac.id" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
                </div>

                <button class="btn btn-primary" type="submit" style="width: 100%;">Kirim Tautan Reset</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
                Kembali ke <a href="login.php" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Halaman Login</a>
            </div>
        </div>
    </div>
</body>
</html>
