<?php
/**
 * Simple Native .env Loader for PHP
 * Loads variables from .env file into $_ENV, $_SERVER, and putenv()
 */

function loadEnv($filePath = null) {
    if ($filePath === null) {
        $filePath = __DIR__ . '/.env';
    }

    if (!file_exists($filePath)) {
        return false;
    }

    if (!is_readable($filePath)) {
        return false;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Abaikan baris komentar
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Cari posisi karakter '=' pertama
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        // Bersihkan tanda kutip ganda atau tunggal di awal dan akhir nilai jika ada
        if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
            $value = $matches[2];
        }

        // Simpan ke environment variables
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    return true;
}

// Jalankan otomatis saat di-include
loadEnv();
