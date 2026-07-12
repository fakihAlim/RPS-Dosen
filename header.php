<?php
require_once 'auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>RPS Generator AI</title>
    <link rel="stylesheet" href="assets/css/style.css?v=6">
    <!-- Icon library (Lucide or similar) via SVG or standard emojis for a modern and ultra-fast look -->
</head>
<body>
    <header>
        <div class="navbar">
            <a href="dashboard.php" class="logo">
                RPS Generator AI
            </a>
            <nav class="nav-links">
                <a href="dashboard.php" class="<?= ($active_page ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="create_rps.php" class="<?= ($active_page ?? '') === 'create_rps' ? 'active' : '' ?>">Buat RPS Baru</a>
                <a href="settings.php" class="<?= ($active_page ?? '') === 'settings' ? 'active' : '' ?>">Pengaturan</a>
                <?php if (isAdmin()): ?>
                    <a href="admin_settings.php" class="<?= ($active_page ?? '') === 'admin_settings' ? 'active' : '' ?>">Pengaturan Admin</a>
                <?php endif; ?>
                <span style="border-left: 1px solid var(--border-color); height: 20px; margin: 0 0.5rem;"></span>
                <span style="color: var(--text-primary); font-size: 0.9rem;">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <a href="logout.php" class="btn btn-danger btn-sm" style="padding: 0.4rem 0.8rem;">Keluar</a>
            </nav>
        </div>
    </header>
    <main class="app-container">
