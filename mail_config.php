<?php
/**
 * Konfigurasi SMTP untuk Pengiriman Email Nyata
 * Memuat variabel sensitif dari berkas .env
 */

// Host SMTP Server (Gmail: smtp.gmail.com, Brevo: smtp-relay.brevo.com)
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');

// Port SMTP (465 untuk SSL, 587 untuk TLS)
define('SMTP_PORT', intval($_ENV['SMTP_PORT'] ?? 587));

// Username / Email Akun SMTP Anda (contoh: emailanda@gmail.com)
define('SMTP_USER', $_ENV['SMTP_USER'] ?? 'your-email@gmail.com');

// Password / App Password (Sandi Aplikasi) 16-digit khusus dari Google Account
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? 'your-app-password-here');

// Tipe Enkripsi: 'ssl' (port 465) atau 'tls' (port 587)
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'tls');

// Alamat Email Pengirim (biasanya disamakan dengan SMTP_USER)
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? 'your-email@gmail.com');

// Nama Pengirim yang tampil di inbox penerima
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'RPS Generator AI');

// Mode Debug: jika true, akan menuliskan error koneksi SMTP ke log lokal
define('SMTP_DEBUG', filter_var($_ENV['SMTP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN));
