CREATE DATABASE IF NOT EXISTS `rps_generator`;
USE `rps_generator`;

-- 1. Tabel users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) UNIQUE DEFAULT NULL,
  `verification_code` VARCHAR(10) DEFAULT NULL,
  `verification_expiry` DATETIME DEFAULT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `role` VARCHAR(20) DEFAULT 'user',
  `is_active` TINYINT(1) DEFAULT 1,
  `reset_token` VARCHAR(64) DEFAULT NULL,
  `reset_expiry` DATETIME DEFAULT NULL,
  `api_key` VARCHAR(255) DEFAULT NULL,
  `ai_model` VARCHAR(50) DEFAULT 'gemini-3.1-flash-lite',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel rps
CREATE TABLE IF NOT EXISTS `rps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `kode_mk` VARCHAR(50) NOT NULL,
  `nama_mk` VARCHAR(100) NOT NULL,
  `sks` INT NOT NULL,
  `semester` INT NOT NULL,
  `program_studi` VARCHAR(100) NOT NULL,
  `cpl` TEXT DEFAULT NULL,
  `cpmk` TEXT DEFAULT NULL,
  `sub_cpmk` TEXT DEFAULT NULL,
  `deskripsi_mk` TEXT DEFAULT NULL,
  `no_dokumen` VARCHAR(100) DEFAULT 'F-M2.STD-PD-3.6',
  `revisi` VARCHAR(50) DEFAULT '02',
  `tanggal_penyusunan` VARCHAR(100) DEFAULT '30 November 2023',
  `prasyarat` VARCHAR(255) DEFAULT '-',
  `bahan_kajian` TEXT DEFAULT NULL,
  `referensi_utama` TEXT DEFAULT NULL,
  `referensi_pendukung` TEXT DEFAULT NULL,
  `sarana_umum` TEXT DEFAULT NULL,
  `sarana_khusus` TEXT DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel rps_meetings
CREATE TABLE IF NOT EXISTS `rps_meetings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rps_id` INT NOT NULL,
  `pertemuan_ke` INT NOT NULL,
  `sub_cpmk` TEXT DEFAULT NULL,
  `bahan_kajian` TEXT DEFAULT NULL,
  `estimasi_waktu` VARCHAR(100) DEFAULT NULL,
  `bobot_penilaian` DECIMAL(5,2) DEFAULT 0.00,
  `metode_luring` TEXT DEFAULT NULL,
  `metode_daring` TEXT DEFAULT NULL,
  `indikator_penilaian` TEXT DEFAULT NULL,
  `bentuk_penilaian` TEXT DEFAULT NULL,
  FOREIGN KEY (`rps_id`) REFERENCES `rps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel email_logs
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `to_email` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `verification_code` VARCHAR(10) DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel activity_logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `activity` VARCHAR(255) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
