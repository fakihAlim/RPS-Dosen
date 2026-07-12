<?php
$page_title = 'Pengaturan Admin';
$active_page = 'admin_settings';

require_once 'db.php';
require_once 'auth.php';

// Memastikan hanya Admin yang dapat mengakses halaman ini
requireAdmin();

// Proteksi setup awal (jika email masih default, arahkan ke admin_setup.php)
$user_id = $_SESSION['user_id'];
$stmtSetupCheck = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtSetupCheck->execute([$user_id]);
$currentAdminEmail = $stmtSetupCheck->fetchColumn();
if ($currentAdminEmail === 'admin@example.com') {
    header('Location: admin_setup.php');
    exit;
}

$success = '';
$error = '';

// Fungsi helper untuk menulis kembali ke file .env secara aman
function updateEnvFile($data) {
    $envPath = __DIR__ . '/.env';
    
    // Jika file .env tidak ada, buat baru
    if (!file_exists($envPath)) {
        $content = "# Database & SMTP Configuration\n";
        foreach ($data as $key => $value) {
            $content .= "{$key}={$value}\n";
        }
        return file_put_contents($envPath, $content) !== false;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    $newLines = [];
    $updatedKeys = [];

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        // Lewati atau simpan komentar/baris kosong apa adanya
        if (empty($trimmedLine) || strpos($trimmedLine, '#') === 0) {
            $newLines[] = $line;
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos !== false) {
            $key = trim(substr($line, 0, $pos));
            if (array_key_exists($key, $data)) {
                // Update nilai dengan data baru
                $newLines[] = "{$key}=" . $data[$key];
                $updatedKeys[] = $key;
            } else {
                $newLines[] = $line;
            }
        } else {
            $newLines[] = $line;
        }
    }

    // Tambahkan key baru jika sebelumnya tidak ada di file .env
    foreach ($data as $key => $value) {
        if (!in_array($key, $updatedKeys)) {
            $newLines[] = "{$key}={$value}";
        }
    }

    return file_put_contents($envPath, implode("\n", $newLines) . "\n") !== false;
}

// Proses form submit jika Admin menyimpan konfigurasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = trim($_POST['smtp_port'] ?? '587');
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = trim($_POST['smtp_pass'] ?? '');
    $smtp_secure = trim($_POST['smtp_secure'] ?? 'tls');
    $smtp_from = trim($_POST['smtp_from'] ?? '');
    $smtp_from_name = trim($_POST['smtp_from_name'] ?? 'RPS Generator AI');
    $smtp_debug = isset($_POST['smtp_debug']) ? 'true' : 'false';
    $check_mail_api_key = trim($_POST['check_mail_api_key'] ?? '');

    // Validasi dasar
    if (empty($smtp_host) || empty($smtp_user) || empty($smtp_pass) || empty($smtp_from)) {
        $error = 'Host SMTP, Username, Password, dan Email Pengirim wajib diisi.';
    } elseif (!filter_var($smtp_from, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format Email Pengirim tidak valid.';
    } elseif (!is_numeric($smtp_port)) {
        $error = 'Port SMTP harus berupa angka.';
    } else {
        $dataToUpdate = [
            'SMTP_HOST' => $smtp_host,
            'SMTP_PORT' => $smtp_port,
            'SMTP_USER' => $smtp_user,
            'SMTP_PASS' => $smtp_pass,
            'SMTP_SECURE' => $smtp_secure,
            'SMTP_FROM' => $smtp_from,
            'SMTP_FROM_NAME' => $smtp_from_name,
            'SMTP_DEBUG' => $smtp_debug,
            'CHECK_MAIL_API_KEY' => $check_mail_api_key
        ];

        if (updateEnvFile($dataToUpdate)) {
            $success = 'Konfigurasi SMTP berhasil disimpan dan diperbarui di berkas .env!';
            
            // Catat log aktivitas admin
            logActivity($user_id, 'ADMIN_SMTP_UPDATE', 'Memperbarui konfigurasi SMTP aplikasi');
            
            // Reload variabel lingkungan di memori saat ini
            foreach ($dataToUpdate as $key => $val) {
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
                putenv("{$key}={$val}");
            }
        } else {
            $error = 'Gagal menyimpan konfigurasi. Periksa izin menulis (write permission) berkas .env.';
        }
    }
}

// Muat nilai konfigurasi SMTP saat ini
$current_host = $_ENV['SMTP_HOST'] ?? '';
$current_port = $_ENV['SMTP_PORT'] ?? '587';
$current_user = $_ENV['SMTP_USER'] ?? '';
$current_pass = $_ENV['SMTP_PASS'] ?? '';
$current_secure = $_ENV['SMTP_SECURE'] ?? 'tls';
$current_from = $_ENV['SMTP_FROM'] ?? '';
$current_from_name = $_ENV['SMTP_FROM_NAME'] ?? 'RPS Generator AI';
$current_debug = filter_var($_ENV['SMTP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN);
$current_check_mail_key = $_ENV['CHECK_MAIL_API_KEY'] ?? '';

require_once 'header.php';
?>

<div style="max-width: 750px; margin: 2rem auto;">
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
        <a href="admin_settings.php" class="btn btn-primary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">SMTP Settings</a>
        <a href="admin_users.php" class="btn btn-secondary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">Daftar Pengguna</a>
        <a href="admin_logs.php" class="btn btn-secondary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">Log Aktivitas</a>
    </div>

    <div class="card">
        <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            Pengaturan SMTP Server (Admin Only)
        </h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Konfigurasikan detail SMTP gratis Anda (Gmail, Brevo, dll.) untuk pengiriman email verifikasi pendaftaran akun dosen secara nyata.</p>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="admin_settings.php" method="POST">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="smtp_host">SMTP Host</label>
                    <input class="form-control" type="text" id="smtp_host" name="smtp_host" placeholder="smtp.gmail.com atau smtp-relay.brevo.com" value="<?= htmlspecialchars($current_host) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="smtp_port">SMTP Port</label>
                    <input class="form-control" type="number" id="smtp_port" name="smtp_port" placeholder="587 / 465" value="<?= htmlspecialchars($current_port) ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="smtp_user">SMTP Username / Email</label>
                    <input class="form-control" type="text" id="smtp_user" name="smtp_user" placeholder="emailanda@gmail.com" value="<?= htmlspecialchars($current_user) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="smtp_secure">Protokol Keamanan</label>
                    <select class="form-control" id="smtp_secure" name="smtp_secure" style="cursor: pointer;">
                        <option value="tls" <?= $current_secure === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
                        <option value="ssl" <?= $current_secure === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                        <option value="none" <?= $current_secure === 'none' ? 'selected' : '' ?>>Tanpa Enkripsi</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="smtp_pass">SMTP Password / App Password</label>
                <div style="position: relative; display: flex; align-items: center;">
                    <input class="form-control" type="password" id="smtp_pass" name="smtp_pass" placeholder="Masukkan password SMTP atau App Password 16-digit" value="<?= htmlspecialchars($current_pass) ?>" style="padding-right: 6rem;" required>
                    <button type="button" id="toggle-smtp-pass" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">
                        Tampilkan
                    </button>
                </div>
                <small style="display: block; color: var(--text-muted); margin-top: 0.5rem; font-size: 0.8rem;">
                    *Jika menggunakan Gmail, gunakan <strong>App Password 16-digit</strong> yang didapatkan dari Google Account Security Anda.
                </small>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="smtp_from">Email Pengirim (Sender Email)</label>
                    <input class="form-control" type="email" id="smtp_from" name="smtp_from" placeholder="no-reply@domain.com" value="<?= htmlspecialchars($current_from) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="smtp_from_name">Nama Pengirim (Sender Name)</label>
                    <input class="form-control" type="text" id="smtp_from_name" name="smtp_from_name" placeholder="RPS Generator AI" value="<?= htmlspecialchars($current_from_name) ?>" required>
                </div>
            </div>

            <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">Pengaturan Keamanan Validasi Email</h3>
                <p style="color: var(--text-muted); margin-bottom: 1.25rem; font-size: 0.85rem;">Gunakan API Check-Mail.org untuk mendeteksi email sekali pakai (disposable) dan mencegah pendaftaran email palsu.</p>
                <div class="form-group">
                    <label class="form-label" for="check_mail_api_key">Check-Mail.org API Key</label>
                    <input class="form-control" type="text" id="check_mail_api_key" name="check_mail_api_key" placeholder="Masukkan API Key dari check-mail.org" value="<?= htmlspecialchars($current_check_mail_key) ?>">
                    <small style="display: block; color: var(--text-muted); margin-top: 0.5rem; font-size: 0.8rem;">
                        *Dapatkan API Key secara gratis atau berbayar di <a href="https://check-mail.org" target="_blank" style="color: var(--accent-primary); text-decoration: underline;">check-mail.org</a>. Kosongkan jika ingin menonaktifkan pemeriksaan API ini.
                    </small>
                </div>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem; margin-bottom: 2rem;">
                <input type="checkbox" id="smtp_debug" name="smtp_debug" style="width: 18px; height: 18px; cursor: pointer;" <?= $current_debug ? 'checked' : '' ?>>
                <label for="smtp_debug" style="cursor: pointer; font-size: 0.95rem; user-select: none;">Aktifkan Mode Debug (Catat detail koneksi SMTP ke log lokal)</label>
            </div>

            <button class="btn btn-primary" type="submit" style="width: 100%;">Simpan Konfigurasi</button>
        </form>
    </div>
</div>

<script>
document.getElementById('toggle-smtp-pass').addEventListener('click', function() {
    const passInput = document.getElementById('smtp_pass');
    if (passInput.type === 'password') {
        passInput.type = 'text';
        this.textContent = 'Sembunyikan';
    } else {
        passInput.type = 'password';
        this.textContent = 'Tampilkan';
    }
});
</script>

<?php
require_once 'footer.php';
?>
