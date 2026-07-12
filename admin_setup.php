<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'mailer.php';

// Memastikan hanya admin yang bisa masuk
requireAdmin();

// Mengambil email admin saat ini
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$admin = $stmt->fetch();

// Jika email admin sudah diubah dari bawaan, tidak perlu setup lagi
if ($admin['email'] !== 'admin@example.com') {
    header('Location: admin_settings.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Email aktif wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        if (should_email_be_blocked($email)) {
            $error = 'Email dari penyedia domain sementara (disposable email) atau tidak valid tidak diperbolehkan.';
        } elseif ($email === 'admin@example.com') {
            $error = 'Anda harus menggunakan alamat email asli yang berbeda dari email bawaan.';
        } else {
        try {
            // Periksa apakah email sudah digunakan oleh user lain
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Email ini sudah terdaftar oleh pengguna lain.';
            } else {
                // Generate secure random readable password
                $chars = 'abcdefghjklmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $password_raw = '';
                for ($i = 0; $i < 10; $i++) {
                    $password_raw .= $chars[mt_rand(0, strlen($chars) - 1)];
                }

                $hashed = password_hash($password_raw, PASSWORD_BCRYPT);

                // Format Email HTML
                $subject = "Penyetelan Akun Administrator - RPS Generator AI";
                $htmlContent = "
                <html>
                <body style='font-family: Tahoma, sans-serif; background-color: #f8fafc; color: #0f172a; padding: 20px;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 30px;'>
                        <h2 style='color: #2563eb; margin-top: 0;'>Penyetelan Akun Admin Berhasil</h2>
                        <p>Halo, <strong>Admin</strong>!</p>
                        <p>Email aktif Anda telah berhasil dikonfigurasi pada sistem RPS Generator AI.</p>
                        <p>Berikut adalah kredensial login baru Anda untuk masuk ke panel administrasi:</p>
                        <div style='background-color: #f1f5f9; border: 1px solid #cbd5e1; padding: 15px; border-radius: 6px; margin: 20px 0; font-family: monospace; font-size: 1rem; color: #0f172a;'>
                            <strong>Username:</strong> " . htmlspecialchars($admin['username']) . "<br>
                            <strong>Email:</strong> " . htmlspecialchars($email) . "<br>
                            <strong>Password Baru:</strong> <span style='color: #dc2626; font-weight: bold;'>" . htmlspecialchars($password_raw) . "</span>
                        </div>
                        <p style='color: #ef4444; font-weight: bold;'>PENTING: Harap simpan kata sandi ini dengan aman. Anda dapat masuk kembali menggunakan username atau email Anda.</p>
                        <hr style='border: none; border-top: 1px solid #cbd5e1; margin: 20px 0;'>
                        <p style='font-size: 0.85rem; color: #475569; margin-bottom: 0;'>Pesan ini dihasilkan secara otomatis oleh sistem keamanan RPS Generator AI.</p>
                    </div>
                </body>
                </html>
                ";

                $textContent = "Halo Admin!\n\n" .
                               "Setup email admin berhasil dilakukan. Berikut adalah kredensial login baru Anda:\n" .
                               "Username: " . $admin['username'] . "\n" .
                               "Email: " . $email . "\n" .
                               "Password Baru: " . $password_raw . "\n\n" .
                               "Harap simpan kredensial ini dengan aman.";

                // Kirim via SMTP
                $mailer = new SmtpMailer();
                $sent = $mailer->send($email, $subject, $htmlContent, $textContent);

                // Catat log email
                $log_id = null;
                try {
                    $stmtLog = $pdo->prepare("INSERT INTO email_logs (to_email, subject, body, verification_code, status) VALUES (?, ?, ?, ?, ?)");
                    $stmtLog->execute([$email, $subject, $htmlContent, $password_raw, 'sending']);
                    $log_id = $pdo->lastInsertId();
                } catch (PDOException $e) {}

                if ($sent) {
                    $success = 'Setup berhasil! Password baru telah dikirim ke email Anda.';
                } else {
                    $success = 'Koneksi SMTP tidak siap. Sebagai simulasi lokal, password baru dicatat di log database.';
                    $_SESSION['mail_fallback_active'] = true;
                }

                if ($log_id) {
                    try {
                        $updateLog = $pdo->prepare("UPDATE email_logs SET status = ? WHERE id = ?");
                        $updateLog->execute([$sent ? 'sent' : 'failed', $log_id]);
                    } catch (PDOException $e) {}
                }

                // Update data admin di database (set email, password, is_verified = 1)
                $updateAdmin = $pdo->prepare("UPDATE users SET email = ?, password = ?, is_verified = 1 WHERE id = ?");
                $updateAdmin->execute([$email, $hashed, $user_id]);

                // Catat log aktivitas
                logActivity($user_id, 'ADMIN_SETUP_COMPLETED', 'Menyelesaikan setup awal email admin');

                // Hancurkan session dan arahkan kembali ke login dengan petunjuk
                $_SESSION = [];
                if (session_status() !== PHP_SESSION_NONE) {
                    session_destroy();
                }
                
                // Mulai session baru hanya untuk menyimpan status/pesan sukses
                session_start();
                $_SESSION['success_message'] = 'Penyetelan admin berhasil! Kata sandi baru telah dikirim ke email ' . $email . '. Silakan masuk kembali.';
                header('Location: login.php');
                exit;
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
    <title>Setup Awal Admin - RPS Generator AI</title>
    <link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
    <div class="app-container">
        <div class="card auth-card">
            <div class="auth-header">
                <a href="#" class="logo" style="justify-content: center; margin-bottom: 1rem;">
                    RPS Generator AI
                </a>
                <h2>Penyetelan Akun Admin</h2>
                <p>Silakan masukkan alamat email aktif Anda. Kata sandi baru yang aman akan dikirimkan ke email tersebut.</p>
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
            // Fallback simulasi untuk localhost
            $smtp_fallback = false;
            if (isset($_SESSION['mail_fallback_active'])) {
                $smtp_fallback = true;
                unset($_SESSION['mail_fallback_active']);
            }
            if ($smtp_fallback):
                // Ambil log email terakhir untuk mendapatkan kata sandi buatan
                try {
                    $stmtPass = $pdo->prepare("SELECT verification_code FROM email_logs WHERE to_email = ? ORDER BY id DESC LIMIT 1");
                    $stmtPass->execute([$email]);
                    $latest_pass = $stmtPass->fetchColumn();
                } catch (PDOException $e) {
                    $latest_pass = '';
                }
                
                if ($latest_pass):
            ?>
                <div class="dev-notice" style="background-color: rgba(245, 158, 11, 0.1); border: 1px solid #cbd5e1; color: var(--warning); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; line-height: 1.4;">
                    <strong>[Koneksi SMTP Belum Siap]</strong><br>
                    Email gagal terkirim secara nyata.<br>
                    Kata sandi baru admin hasil simulasi: <span style="font-family: monospace; font-weight: bold; color: var(--error); font-size: 1.1rem; background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($latest_pass) ?></span><br>
                    Silakan gunakan kata sandi ini untuk login kembali.
                </div>
            <?php endif; endif; ?>

            <form action="admin_setup.php" method="POST">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" for="email">Email Aktif Admin</label>
                    <input class="form-control" type="email" id="email" name="email" placeholder="Contoh: admin@kampus.ac.id" required>
                </div>

                <button class="btn btn-primary" type="submit" style="width: 100%;">Set & Kirim Sandi Baru</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
                Akan keluar dari sesi saat ini setelah setup selesai.
            </div>
        </div>
    </div>
</body>
</html>
