<?php
$page_title = 'Log Aktivitas';
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

$error = '';

// Paginasi Log
$limit = 15; // Jumlah log per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_logs = 0;
$total_pages = 1;
$logs_list = [];

try {
    // Hitung total log
    $countStmt = $pdo->query("SELECT COUNT(*) FROM activity_logs");
    $total_logs = $countStmt->fetchColumn();
    $total_pages = ceil($total_logs / $limit);
    if ($total_pages < 1) $total_pages = 1;
    if ($page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Ambil data log dengan LIMIT & OFFSET
    $stmt = $pdo->prepare("SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT ? OFFSET ?");
    // Bind parameters secara bertipe integer untuk mencegah syntax error di PDO MySQL emulasi
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Gagal memuat log aktivitas: ' . $e->getMessage();
}

require_once 'header.php';
?>

<div style="max-width: 950px; margin: 2rem auto;">
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
        <a href="admin_settings.php" class="btn btn-secondary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">SMTP Settings</a>
        <a href="admin_users.php" class="btn btn-secondary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">Daftar Pengguna</a>
        <a href="admin_logs.php" class="btn btn-primary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">Log Aktivitas</a>
    </div>

    <div class="card">
        <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">
            Log Aktivitas Pengguna
        </h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Pantau seluruh riwayat aktivitas dan pengoperasian sistem oleh pengguna di dalam aplikasi.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div style="overflow-x: auto; margin-bottom: 1.5rem;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); background-color: var(--bg-tertiary);">
                        <th style="padding: 10px; font-weight: 700; width: 150px;">Waktu Kejadian</th>
                        <th style="padding: 10px; font-weight: 700; width: 120px;">Username</th>
                        <th style="padding: 10px; font-weight: 700; width: 180px;">Jenis Aktivitas</th>
                        <th style="padding: 10px; font-weight: 700;">Detail Aktivitas</th>
                        <th style="padding: 10px; font-weight: 700; width: 120px; text-align: center;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs_list)): ?>
                        <tr>
                            <td colspan="5" style="padding: 20px; text-align: center; color: var(--text-muted);">Belum ada log aktivitas yang tercatat.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs_list as $l): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px; color: var(--text-muted); white-space: nowrap;">
                                    <?= htmlspecialchars($l['created_at']) ?>
                                </td>
                                <td style="padding: 10px; font-weight: 600; color: var(--text-primary);">
                                    <?= htmlspecialchars($l['username'] ?? 'Tamu / Umum') ?>
                                </td>
                                <td style="padding: 10px;">
                                    <span style="font-family: monospace; font-weight: bold; background-color: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; border: 1px solid var(--border-color); color: var(--accent-primary);">
                                        <?= htmlspecialchars($l['activity']) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px; color: var(--text-primary); line-height: 1.4;">
                                    <?= htmlspecialchars($l['details'] ?? '-') ?>
                                </td>
                                <td style="padding: 10px; text-align: center; font-family: monospace; color: var(--text-muted);">
                                    <?= htmlspecialchars($l['ip_address'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Navigasi Paginasi Flat -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 1rem;">
                <!-- Halaman Sebelumnya -->
                <?php if ($page > 1): ?>
                    <a href="admin_logs.php?page=<?= $page - 1 ?>" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-weight: 600;">Sebelumnya</a>
                <?php else: ?>
                    <span class="btn btn-secondary btn-sm btn-disabled" style="padding: 4px 10px; opacity: 0.5;">Sebelumnya</span>
                <?php endif; ?>

                <!-- Keterangan Halaman -->
                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                    Halaman <strong><?= $page ?></strong> dari <strong><?= $total_pages ?></strong> (Total: <?= $total_logs ?> log)
                </span>

                <!-- Halaman Selanjutnya -->
                <?php if ($page < $total_pages): ?>
                    <a href="admin_logs.php?page=<?= $page + 1 ?>" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-weight: 600;">Selanjutnya</a>
                <?php else: ?>
                    <span class="btn btn-secondary btn-sm btn-disabled" style="padding: 4px 10px; opacity: 0.5;">Selanjutnya</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
