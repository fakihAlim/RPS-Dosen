# RPS Generator AI (Rencana Pembelajaran Semester Dosen)

Aplikasi berbasis web untuk membantu dosen dan akademisi menyusun dokumen **Rencana Pembelajaran Semester (RPS)** secara terstruktur dan cepat menggunakan teknologi **Kecerdasan Buatan (AI)**. Aplikasi ini dirancang dengan standar keamanan tinggi untuk mendeteksi ancaman digital, log aktivitas, serta perlindungan privasi data pengguna.

---

## 🚀 Fitur Utama

### 1. Otomatisasi AI & Kurikulum
* **Generate CPL & CPMK**: Menggunakan Gemini AI untuk menyusun Capaian Pembelajaran Lulusan (CPL) dan Capaian Pembelajaran Mata Kuliah (CPMK) berdasarkan deskripsi mata kuliah.
* **Generate Pertemuan (16 Pertemuan)**: Menyusun 16 modul pertemuan pembelajaran lengkap dengan metode (luring/daring), estimasi waktu, indikator, dan bentuk penilaian.
* **Normalisasi Bobot Otomatis**: Menjamin total bobot penilaian (termasuk UTS di pertemuan ke-8 dan UAS di pertemuan ke-16) terakumulasi tepat **100%**.
* **Ekspor PDF**: Cetak dokumen RPS ke format PDF resmi.

### 2. Kemanan Tingkat Tinggi
* **Enkripsi Kunci API**: Kunci API Gemini milik pengguna disimpan di database dengan enkripsi kuat **AES-256-CBC** menggunakan kunci utama dinamis dan HMAC untuk mencegah manipulasi.
* **Proteksi Injection & XSS**: Menggunakan PDO Prepared Statements untuk mencegah *SQL Injection*, serta pembersihan input HTML entities untuk mencegah serangan *Cross-Site Scripting (XSS)*.
* **Validasi Anti-Email Palsu**: Integrasi langsung dengan API **check-mail.org** untuk menyaring dan menolak registrasi menggunakan email sementara sekali pakai (*disposable email*).

### 3. Panel Administrasi & Pengawasan
* **Penyetelan Akun Admin**: Perlindungan akun admin default dengan memaksa konfigurasi email aktif dan pengiriman password baru via email saat login pertama kali.
* **Suspensi Pengguna**: Admin dapat menonaktifkan pengguna secara langsung, yang akan menghancurkan sesi aktif pengguna tersebut secara instan.
* **Log Aktivitas Komprehensif**: Riwayat login, pendaftaran, eksekusi AI, dan modifikasi RPS dicatat secara *real-time* ke database.

---

## 🛠️ Cara Setup & Instalasi

### Prerequisites
* PHP versi 7.4 atau lebih baru (dengan ekstensi `PDO`, `curl`, `openssl` aktif)
* Server Database MySQL/MariaDB
* Web Server (Laragon, XAMPP, Nginx, atau Apache)

### Langkah-langkah Instalasi

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/USERNAME/RPS-Dosen.git
   cd RPS-Dosen
   ```

2. **Setup Konfigurasi (.env)**:
   * Salin file template `.env.example` menjadi `.env`:
     ```bash
     cp .env.example .env
     ```
   * Buka file `.env` dan lengkapi konfigurasi berikut:
     * **Database**: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
     * **SMTP Server**: Berguna untuk mengirim OTP verifikasi dan reset password.
     * **Encryption Key**: Buat kunci enkripsi acak 32-karakter untuk mengamankan API key Gemini milik user.
     * **Check-Mail API Key**: (Opsional) Dapatkan API key dari [check-mail.org](https://check-mail.org) untuk memblokir email palsu.

3. **Import Database**:
   * Buat database baru di MySQL dengan nama `rps_generator` (atau nama pilihan Anda).
   * Impor skema tabel yang ada di dalam berkas **`schema.sql`** ke database tersebut.

4. **Jalankan Aplikasi**:
   * Jika menggunakan Laragon/XAMPP, letakkan folder proyek ini di direktori web root (`www` atau `htdocs`).
   * Atau jalankan PHP Built-in Server di terminal:
     ```bash
     php -S localhost:8000
     ```

5. **Login Akun Admin Pertama Kali**:
   * Gunakan kredensial default admin:
     * **Username**: `admin`
     * **Password**: `admin123`
   * Sistem akan mendeteksi akun default dan langsung mengarahkan Anda ke halaman setup admin untuk memasukkan alamat email asli Anda. Password baru admin akan dikirimkan ke email tersebut.

---

## 📂 Struktur Berkas Penting

* **`db.php`**: Inisialisasi koneksi database PDO & fungsi global log aktivitas.
* **`auth.php`**: Penanganan hak akses session, login/logout, dan validasi status aktif pengguna.
* **`register.php`**: Pendaftaran akun dosen baru (dilengkapi penyaring email palsu via API).
* **`verify.php`**: Verifikasi kode OTP pendaftaran dosen.
* **`forgot_password.php` & `reset_password.php`**: Alur reset kata sandi melalui token kedaluwarsa.
* **`admin_settings.php`**: Dashboard konfigurasi SMTP dan API key validasi email.
* **`admin_users.php`**: Manajemen status suspensi dan penghapusan pengguna.
* **`admin_logs.php`**: Viewer riwayat aktivitas sistem.
