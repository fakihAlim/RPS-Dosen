<?php
$page_title = 'Dashboard';
$active_page = 'dashboard';
require_once 'db.php';
require_once 'auth.php';

// Proteksi login
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Proses Hapus RPS jika diminta
if (isset($_GET['delete'])) {
    $rps_id = (int)$_GET['delete'];
    try {
        // Pastikan RPS ini milik user yang sedang login
        // Ambil nama_mk sebelum dihapus untuk dimasukkan ke log
        $stmtName = $pdo->prepare("SELECT nama_mk FROM rps WHERE id = ? AND user_id = ?");
        $stmtName->execute([$rps_id, $user_id]);
        $nama_mk = $stmtName->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM rps WHERE id = ? AND user_id = ?");
        $stmt->execute([$rps_id, $user_id]);
        if ($stmt->rowCount() > 0) {
            $success = 'RPS berhasil dihapus.';
            logActivity($user_id, 'DELETE_RPS', 'Menghapus dokumen RPS ' . ($nama_mk ? $nama_mk : '(ID: ' . $rps_id . ')'));
        } else {
            $error = 'RPS tidak ditemukan atau Anda tidak memiliki akses.';
        }
    } catch (PDOException $e) {
        $error = 'Gagal menghapus RPS: ' . $e->getMessage();
    }
}

// Cek apakah API Key sudah di-setup oleh Dosen
$api_key_configured = false;
try {
    $stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!empty($user['api_key'])) {
        $api_key_configured = true;
    }
} catch (PDOException $e) {
    $error = 'Gagal memverifikasi akun Anda: ' . $e->getMessage();
}

// Load daftar RPS milik Dosen yang login
$rps_list = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM rps WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $rps_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Gagal memuat daftar RPS: ' . $e->getMessage();
}

require_once 'header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h1>Dashboard RPS Anda</h1>
        <p>Kelola dokumen Rencana Pembelajaran Semester (RPS) Anda di sini.</p>
    </div>
    <div>
        <a href="create_rps.php" class="btn btn-primary <?= !$api_key_configured ? 'btn-disabled' : '' ?>" <?= !$api_key_configured ? 'title="Silakan atur API Key di Pengaturan terlebih dahulu"' : '' ?>>
            Buat RPS Baru
        </a>
    </div>
</div>

<?php if (!$api_key_configured): ?>
    <div class="alert alert-danger" style="margin-bottom: 2rem;">
        <div>
            <strong>API Key Belum Dikonfigurasi!</strong> Anda harus mengatur Gemini API Key terlebih dahulu di menu 
            <a href="settings.php" style="color: inherit; text-decoration: underline; font-weight: 700;">Pengaturan</a> 
            sebelum dapat membuat RPS menggunakan bantuan AI.
        </div>
    </div>
<?php endif; ?>

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

<?php if (empty($rps_list)): ?>
    <div class="empty-state">
        <h3 style="margin-top: 1rem;">Belum ada dokumen RPS</h3>
        <p style="margin-bottom: 1.5rem; max-width: 450px; margin-left: auto; margin-right: auto;">
            Anda belum pernah membuat Rencana Pembelajaran Semester. Mulai buat sekarang dengan panduan asisten AI.
        </p>
        <?php if ($api_key_configured): ?>
            <a href="create_rps.php" class="btn btn-primary">Buat RPS Pertama</a>
        <?php else: ?>
            <a href="settings.php" class="btn btn-secondary">Atur API Key Dulu</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="grid-rps">
        <?php foreach ($rps_list as $rps): ?>
            <div class="rps-card">
                <div>
                    <div class="rps-meta">
                        <span>Kode: <?= htmlspecialchars($rps['kode_mk']) ?></span>
                        <span><?= htmlspecialchars($rps['program_studi']) ?></span>
                        <?php if (($rps['status'] ?? 'draft') === 'draft'): ?>
                            <span style="background-color: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">Draft</span>
                        <?php else: ?>
                            <span style="background-color: rgba(16, 185, 129, 0.15); color: #10b981; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">Final</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="rps-name"><?= htmlspecialchars($rps['nama_mk']) ?></h3>
                    <div class="rps-details">
                        SKS: <strong><?= (int)$rps['sks'] ?></strong> &bull; 
                        Semester: <strong><?= (int)$rps['semester'] ?></strong>
                    </div>
                </div>
                <div class="rps-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="create_rps.php?edit=<?= $rps['id'] ?>" class="btn btn-secondary btn-sm" style="flex: 1; min-width: 60px;">
                        Edit
                    </a>
                    <a href="print_rps.php?id=<?= $rps['id'] ?>" target="_blank" class="btn btn-primary btn-sm" style="flex: 1; min-width: 60px;">
                        Cetak
                    </a>
                    <a href="export_word.php?id=<?= $rps['id'] ?>" class="btn btn-secondary btn-sm" style="flex: 1; min-width: 60px; background-color: #2b579a; color: white;" title="Ekspor MS Word">
                        Word
                    </a>
                    <a href="dashboard.php?delete=<?= $rps['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus RPS ini beserta seluruh rincian pertemuannya?')" class="btn btn-danger btn-sm" style="flex: 1; min-width: 60px;" title="Hapus RPS">
                        Hapus
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
