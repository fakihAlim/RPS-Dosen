<?php
$page_title = 'Pengaturan';
$active_page = 'settings';
require_once 'db.php';
require_once 'auth.php';

// Memastikan user sudah login
requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Load data dosen saat ini
try {
    $stmt = $pdo->prepare("SELECT api_key, ai_model FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    
    $decrypted_key = decryptApiKey($user_data['api_key'] ?? '');
    $current_model = $user_data['ai_model'] ?? 'gemini-3.5-flash';
} catch (PDOException $e) {
    $error = 'Gagal memuat data: ' . $e->getMessage();
}

// Simpan data jika ada post request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_key = trim($_POST['api_key'] ?? '');
    $ai_model = trim($_POST['ai_model'] ?? 'gemini-3.5-flash');
    
    // Daftar model valid
    $valid_models = ['gemini-3.5-flash', 'gemini-3.1-flash'];
    if (!in_array($ai_model, $valid_models)) {
        $ai_model = 'gemini-3.5-flash';
    }

    try {
        $encrypted_key = encryptApiKey($api_key);
        
        $stmt = $pdo->prepare("UPDATE users SET api_key = ?, ai_model = ? WHERE id = ?");
        $stmt->execute([$encrypted_key, $ai_model, $user_id]);
        
        $success = 'Pengaturan berhasil diperbarui!';
        $decrypted_key = $api_key;
        $current_model = $ai_model;
    } catch (PDOException $e) {
        $error = 'Gagal memperbarui pengaturan: ' . $e->getMessage();
    }
}

// Include header
require_once 'header.php';
?>

<div style="max-width: 650px; margin: 2rem auto;">
    <div class="card">
        <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            Pengaturan Profil Dosen
        </h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Konfigurasikan API Key Google AI Studio Anda untuk mengaktifkan fitur kecerdasan buatan.</p>

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

        <form action="settings.php" method="POST">
            <div class="form-group">
                <label class="form-label" for="api_key">Google AI Studio (Gemini) API Key</label>
                <div style="position: relative; display: flex; align-items: center;">
                    <input class="form-control" type="password" id="api_key" name="api_key" placeholder="Masukkan API Key Anda (AIzaSy...)" value="<?= htmlspecialchars($decrypted_key) ?>" style="padding-right: 6rem;" required>
                    <button type="button" id="toggle-key-btn" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">
                        Tampilkan
                    </button>
                </div>
                <small style="display: block; color: var(--text-muted); margin-top: 0.5rem; font-size: 0.8rem;">
                    Dapatkan API Key gratis di <a href="https://aistudio.google.com/" target="_blank" style="color: var(--accent-primary); text-decoration: none;">Google AI Studio</a>. API Key Anda akan disimpan secara terenkripsi di database kami.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="ai_model">Model AI Default</label>
                <select class="form-control" id="ai_model" name="ai_model" style="background-image: none; cursor: pointer;">
                    <option value="gemini-3.5-flash" <?= $current_model === 'gemini-3.5-flash' ? 'selected' : '' ?>>Gemini 3.5 Flash</option>
                    <option value="gemini-3.1-flash" <?= $current_model === 'gemini-3.1-flash' ? 'selected' : '' ?>>Gemini 3.1 Flash</option>
                </select>
                <small style="display: block; color: var(--text-muted); margin-top: 0.5rem; font-size: 0.8rem;">
                    Model default ini akan digunakan secara otomatis saat Anda melakukan generate CPL, CPMK, maupun rincian 16 pertemuan.
                </small>
            </div>

            <button class="btn btn-primary" type="submit" style="width: 100%; margin-top: 1.5rem;">Simpan Pengaturan</button>
        </form>
    </div>
</div>

<script>
document.getElementById('toggle-key-btn').addEventListener('click', function() {
    const keyInput = document.getElementById('api_key');
    if (keyInput.type === 'password') {
        keyInput.type = 'text';
        this.textContent = 'Sembunyikan';
    } else {
        keyInput.type = 'password';
        this.textContent = 'Tampilkan';
    }
});
</script>

<?php
require_once 'footer.php';
?>
