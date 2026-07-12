<?php
// Script Diagnostik Login, Session & Konfigurasi PHP di Server
echo "<h2>DIAGNOSTIK SERVER LENGKAP</h2>";

// ============ BAGIAN 1: INFO PHP ============
echo "<h3>1. Info PHP & Session</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Session Save Path (default): " . ini_get('session.save_path') . "<br>";
echo "Session Handler: " . ini_get('session.save_handler') . "<br>";
echo "open_basedir: " . (ini_get('open_basedir') ?: '(tidak diset)') . "<br>";

// ============ BAGIAN 2: CEK FOLDER SESSIONS ============
echo "<h3>2. Folder Sessions</h3>";
$sessDir = __DIR__ . '/sessions';
echo "Path: " . $sessDir . "<br>";
echo "Folder Ada: " . (file_exists($sessDir) ? '<span style="color:green">YA</span>' : '<span style="color:red">TIDAK</span>') . "<br>";
if (file_exists($sessDir)) {
    echo "Writable: " . (is_writable($sessDir) ? '<span style="color:green">YA</span>' : '<span style="color:red">TIDAK</span>') . "<br>";
    $perms = substr(sprintf('%o', fileperms($sessDir)), -4);
    echo "Permissions: " . $perms . "<br>";
}

// ============ BAGIAN 3: TES SESSION MANUAL ============
echo "<h3>3. Tes Session</h3>";

// Coba set session save path
$customPath = __DIR__ . '/sessions';
if (file_exists($customPath) && is_writable($customPath)) {
    ini_set('session.save_path', $customPath);
    echo "session.save_path diubah ke: " . $customPath . "<br>";
} else {
    echo '<span style="color:red">Folder sessions tidak writable, menggunakan default</span><br>';
}

echo "Session Save Path (aktif): " . session_save_path() . "<br>";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . " (2 = aktif)<br>";

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($step === 1) {
    $_SESSION['test_val'] = 'session_works_' . time();
    echo "Menyimpan data sesi: " . $_SESSION['test_val'] . "<br>";
    
    // Cek apakah file session terbuat
    $sessFile = session_save_path() . '/sess_' . session_id();
    echo "File session path: " . $sessFile . "<br>";
    
    // Force write
    session_write_close();
    
    echo "File session ada setelah write_close: " . (file_exists($sessFile) ? '<span style="color:green">YA</span>' : '<span style="color:red">TIDAK - ini penyebab gagal login!</span>') . "<br>";
    
    // List isi folder sessions
    if (file_exists($customPath)) {
        $files = scandir($customPath);
        echo "Isi folder sessions: " . implode(', ', $files) . "<br>";
    }
    
    echo "<br><a href='check_login.php?step=2' style='display:inline-block; background:#007bff; color:#fff; padding:8px 16px; text-decoration:none; border-radius:4px;'>Klik untuk Verifikasi Session</a>";
} elseif ($step === 2) {
    if (isset($_SESSION['test_val']) && strpos($_SESSION['test_val'], 'session_works_') === 0) {
        echo '<span style="color:green; font-weight:bold; font-size:16px;">[PASSED] Session berfungsi!</span><br>';
    } else {
        echo '<span style="color:red; font-weight:bold; font-size:16px;">[FAILED] Session masih gagal.</span><br>';
        echo "Isi \$_SESSION: <pre>" . print_r($_SESSION, true) . "</pre>";
    }
    echo "<br><a href='check_login.php'>Uji Ulang</a>";
}

// ============ BAGIAN 4: CEK DATABASE ============
echo "<h3>4. Database</h3>";
try {
    require_once 'db.php';
    $stmt = $pdo->prepare("SELECT id, username, email, role, is_active, is_verified FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    if ($admin) {
        echo '<span style="color:green">[OK] Admin ditemukan</span><br>';
        echo "<pre>" . print_r($admin, true) . "</pre>";
        echo "Password cocok: " . (password_verify('admin123', $pdo->query("SELECT password FROM users WHERE username='admin'")->fetchColumn()) ? '<span style="color:green">YA</span>' : '<span style="color:red">TIDAK</span>') . "<br>";
    } else {
        echo '<span style="color:red">[ERROR] Admin tidak ada di database</span>';
    }
} catch (Exception $e) {
    echo '<span style="color:red">[ERROR] ' . $e->getMessage() . '</span>';
}
