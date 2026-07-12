<?php
// Script Diagnostik Login & Session di Server
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h3>=== DIAGNOSTIK KEADAAN SERVER ===</h3>";

// 1. Cek Koneksi Database & Data Admin
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        echo "<span style='color:green;'>[OK] User 'admin' ditemukan di database.</span><br>";
        echo "Email: " . $admin['email'] . "<br>";
        echo "Role: " . $admin['role'] . "<br>";
        echo "Status Aktif: " . ($admin['is_active'] == 1 ? 'Aktif' : 'Nonaktif') . "<br>";
        echo "Status Verifikasi: " . ($admin['is_verified'] == 1 ? 'Terverifikasi' : 'Belum') . "<br>";

        // Uji coba verifikasi password
        $test_pass = password_verify('admin123', $admin['password']);
        if ($test_pass) {
            echo "<span style='color:green;'>[OK] Password 'admin123' cocok dengan hash di database.</span><br>";
        } else {
            echo "<span style='color:red;'>[ERROR] Password 'admin123' TIDAK cocok dengan hash di database!</span><br>";
        }
    } else {
        echo "<span style='color:red;'>[ERROR] User 'admin' tidak ditemukan di database! Silakan jalankan query INSERT.</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color:red;'>[ERROR] Gagal membaca database: " . $e->getMessage() . "</span><br>";
}

// 2. Cek Apakah PHP Session Berfungsi
echo "<h3>=== DIAGNOSTIK SESSION PHP ===</h3>";

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($step === 1) {
    $_SESSION['test_session_active'] = "Sesi Berfungsi Dengan Baik!";
    echo "Mencoba menyimpan data sesi...<br>";
    echo "<a href='check_login.php?step=2' style='display:inline-block; background:#007bff; color:#fff; padding:6px 12px; text-decoration:none; border-radius:4px; margin-top:10px;'>Klik disini untuk Verifikasi Sesi</a>";
} elseif ($step === 2) {
    if (isset($_SESSION['test_session_active']) && $_SESSION['test_session_active'] === "Sesi Berfungsi Dengan Baik!") {
        echo "<span style='color:green; font-weight:bold;'>[PASSED] PHP Session berfungsi dengan sempurna di server Anda!</span><br>";
        unset($_SESSION['test_session_active']);
    } else {
        echo "<span style='color:red; font-weight:bold;'>[FAILED] PHP Session GAGAL berfungsi di server Anda! Data sesi hilang setelah berpindah halaman. Silakan hubungi hosting Anda untuk memperbaiki izin folder session (session.save_path).</span><br>";
    }
    echo "<a href='check_login.php' style='display:inline-block; background:#6c757d; color:#fff; padding:6px 12px; text-decoration:none; border-radius:4px; margin-top:10px;'>Uji Ulang</a>";
}
echo "<br><br><a href='login.php'>Kembali ke Login</a>";
