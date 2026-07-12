<?php
$page_title = 'Manajemen Pengguna';
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

// 1. Aksi Toggle Status Aktif (Suspens / Aktifkan)
if (isset($_GET['toggle'])) {
    $target_id = (int)$_GET['toggle'];
    
    // Jangan izinkan admin menonaktifkan dirinya sendiri
    if ($target_id === $user_id) {
        $error = 'Anda tidak dapat menonaktifkan akun Anda sendiri.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT username, is_active FROM users WHERE id = ?");
            $stmt->execute([$target_id]);
            $target_user = $stmt->fetch();
            
            if ($target_user) {
                $new_status = $target_user['is_active'] == 1 ? 0 : 1;
                $update = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $update->execute([$new_status, $target_id]);
                
                $status_txt = $new_status == 1 ? 'mengaktifkan' : 'menonaktifkan';
                $success = 'Berhasil ' . $status_txt . ' user: ' . htmlspecialchars($target_user['username']);
                
                // Catat log aktivitas admin
                logActivity($user_id, 'ADMIN_USER_TOGGLE', 'Mengubah status aktif user ' . $target_user['username'] . ' menjadi ' . ($new_status == 1 ? 'Aktif' : 'Nonaktif'));
            } else {
                $error = 'Pengguna tidak ditemukan.';
            }
        } catch (PDOException $e) {
            $error = 'Gagal memproses aksi: ' . $e->getMessage();
        }
    }
}

// 2. Aksi Hapus Pengguna
if (isset($_GET['delete'])) {
    $target_id = (int)$_GET['delete'];
    
    // Jangan izinkan admin menghapus dirinya sendiri
    if ($target_id === $user_id) {
        $error = 'Anda tidak dapat menghapus akun Anda sendiri.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$target_id]);
            $target_user = $stmt->fetch();
            
            if ($target_user) {
                $delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delete->execute([$target_id]);
                
                $success = 'Berhasil menghapus secara permanen user: ' . htmlspecialchars($target_user['username']);
                
                // Catat log aktivitas admin
                logActivity($user_id, 'ADMIN_USER_DELETE', 'Menghapus user ' . $target_user['username'] . ' secara permanen');
            } else {
                $error = 'Pengguna tidak ditemukan.';
            }
        } catch (PDOException $e) {
            $error = 'Gagal menghapus pengguna: ' . $e->getMessage();
        }
    }
}

// 3. Ambil filter pencarian
$search = trim($_GET['search'] ?? '');
$users_list = [];

try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT id, username, email, role, is_verified, is_active, created_at FROM users WHERE id != ? AND (username LIKE ? OR email LIKE ?) ORDER BY created_at DESC");
        $stmt->execute([$user_id, '%' . $search . '%', '%' . $search . '%']);
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email, role, is_verified, is_active, created_at FROM users WHERE id != ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
    }
    $users_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Gagal memuat daftar pengguna: ' . $e->getMessage();
}

require_once 'header.php';
?>

<div style="max-width: 950px; margin: 2rem auto;">
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
        <a href="admin_settings.php" class="btn btn-secondary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">SMTP Settings</a>
        <a href="admin_users.php" class="btn btn-primary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">Daftar Pengguna</a>
        <a href="admin_logs.php" class="btn btn-secondary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">Log Aktivitas</a>
    </div>

    <div class="card">
        <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">
            Daftar Pengguna Aplikasi
        </h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Kelola, nonaktifkan, atau hapus akun dosen terdaftar di dalam aplikasi ini.</p>

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

        <!-- Form Pencarian -->
        <form action="admin_users.php" method="GET" style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
            <input class="form-control" type="text" name="search" placeholder="Cari berdasarkan username atau email..." value="<?= htmlspecialchars($search) ?>" style="flex: 1;">
            <button class="btn btn-primary" type="submit" style="padding: 0.6rem 1.5rem;">Cari</button>
            <?php if (!empty($search)): ?>
                <a href="admin_users.php" class="btn btn-secondary" style="padding: 0.6rem 1.5rem; display: flex; align-items: center; justify-content: center;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); background-color: var(--bg-tertiary);">
                        <th style="padding: 12px; font-weight: 700;">Username</th>
                        <th style="padding: 12px; font-weight: 700;">Email</th>
                        <th style="padding: 12px; font-weight: 700;">Role</th>
                        <th style="padding: 12px; font-weight: 700; text-align: center;">Verifikasi</th>
                        <th style="padding: 12px; font-weight: 700; text-align: center;">Status</th>
                        <th style="padding: 12px; font-weight: 700; text-align: center; width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users_list)): ?>
                        <tr>
                            <td colspan="6" style="padding: 20px; text-align: center; color: var(--text-muted);">Tidak ada pengguna ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $u): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px; font-weight: 600; color: var(--text-primary);">
                                    <?= htmlspecialchars($u['username']) ?>
                                </td>
                                <td style="padding: 12px; color: var(--text-muted);">
                                    <?= htmlspecialchars($u['email'] ?? '-') ?>
                                </td>
                                <td style="padding: 12px; text-transform: capitalize; font-weight: 500;">
                                    <?= htmlspecialchars($u['role']) ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <?php if ($u['is_verified'] == 1): ?>
                                        <span style="color: var(--success); font-weight: bold; background: rgba(5, 150, 105, 0.1); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">Terverifikasi</span>
                                    <?php else: ?>
                                        <span style="color: var(--warning); font-weight: bold; background: rgba(217, 119, 6, 0.1); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <?php if ($u['is_active'] == 1): ?>
                                        <span style="color: var(--success); font-weight: bold; background: rgba(5, 150, 105, 0.1); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">Aktif</span>
                                    <?php else: ?>
                                        <span style="color: var(--error); font-weight: bold; background: rgba(220, 38, 38, 0.1); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; text-align: center; display: flex; gap: 8px; justify-content: center;">
                                    <?php if ($u['is_active'] == 1): ?>
                                        <a href="admin_users.php?toggle=<?= $u['id'] ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center; padding: 4px 8px; background-color: var(--bg-tertiary); color: var(--error); border-color: var(--border-color);" title="Nonaktifkan User">
                                            Suspend
                                        </a>
                                    <?php else: ?>
                                        <a href="admin_users.php?toggle=<?= $u['id'] ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center; padding: 4px 8px; background-color: var(--bg-tertiary); color: var(--success); border-color: var(--border-color);" title="Aktifkan User">
                                            Aktifkan
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="admin_users.php?delete=<?= $u['id'] ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus user <?= htmlspecialchars($u['username']) ?> secara permanen? Seluruh dokumen RPS milik user ini juga akan terhapus.')" class="btn btn-danger btn-sm" style="flex: 1; text-align: center; padding: 4px 8px;" title="Hapus User">
                                        Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
