<?php
$page_title = 'Buat/Edit RPS';
$active_page = 'create_rps';
require_once 'db.php';
require_once 'auth.php';

// Proteksi login
requireLogin();

$user_id = $_SESSION['user_id'];
$edit_mode = false;
$rps_id = 0;
$course_data = null;
$meetings_data = [];

// Periksa apakah ini edit mode
if (isset($_GET['edit'])) {
    $rps_id = (int)$_GET['edit'];
    try {
        // Ambil data RPS utama
        $stmt = $pdo->prepare("SELECT * FROM rps WHERE id = ? AND user_id = ?");
        $stmt->execute([$rps_id, $user_id]);
        $course_data = $stmt->fetch();
        
        if ($course_data) {
            $edit_mode = true;
            
            // Ambil rincian pertemuan
            $stmt = $pdo->prepare("SELECT * FROM rps_meetings WHERE rps_id = ? ORDER BY pertemuan_ke ASC");
            $stmt->execute([$rps_id]);
            $meetings_data = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Handle error silent or show
    }
}

// Cek apakah API key dikonfigurasi
$stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (empty($user['api_key'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'header.php';
?>

<!-- Data RPS Eksisting untuk JS (jika edit mode) -->
<script>
    const editMode = <?= $edit_mode ? 'true' : 'false' ?>;
    const rpsId = (int)<?= $rps_id ?>;
    const existingCourseData = <?= $course_data ? json_encode($course_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null' ?>;
    const existingMeetingsData = <?= !empty($meetings_data) ? json_encode($meetings_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null' ?>;
</script>

<div class="wizard-stepper">
    <div class="wizard-progress" id="wizard-progress"></div>
    <div class="step-node active" data-step="1">
        <div class="step-circle">1</div>
        <div class="step-label">Detail Kuliah</div>
    </div>
    <div class="step-node" data-step="2">
        <div class="step-circle">2</div>
        <div class="step-label">CPL & CPMK</div>
    </div>
    <div class="step-node" data-step="3">
        <div class="step-circle">3</div>
        <div class="step-label">Generate AI</div>
    </div>
    <div class="step-node" data-step="4">
        <div class="step-circle">4</div>
        <div class="step-label">Review & Edit</div>
    </div>
</div>

<!-- TAMPILAN WIZARD PANEL -->
<div class="card" style="margin-bottom: 2rem; position: relative;">

    <!-- PANEL LANGKAH 1: DETAIL KULIAH -->
    <div class="wizard-step-panel active" id="step-panel-1">
        <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            Langkah 1: Informasi Umum Mata Kuliah
        </h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="kode_mk">Kode Mata Kuliah</label>
                <input class="form-control" type="text" id="kode_mk" placeholder="Contoh: INF-301" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="nama_mk">Nama Mata Kuliah</label>
                <input class="form-control" type="text" id="nama_mk" placeholder="Contoh: Pemrograman Web" required>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1.5fr 1.5fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="sks">SKS</label>
                <select class="form-control" id="sks">
                    <option value="1">1 SKS</option>
                    <option value="2" selected>2 SKS</option>
                    <option value="3">3 SKS</option>
                    <option value="4">4 SKS</option>
                    <option value="6">6 SKS</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="semester">Semester</label>
                <input class="form-control" type="number" id="semester" min="1" max="8" value="1" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="program_studi">Program Studi</label>
                <input class="form-control" type="text" id="program_studi" placeholder="Contoh: Teknik Informatika" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="prasyarat">Prasyarat MK</label>
                <input class="form-control" type="text" id="prasyarat" value="-" placeholder="-">
            </div>
        </div>

        <!-- Bidang metadata disembunyikan atas permintaan user -->
        <div style="display: none;">
            <input type="hidden" id="no_dokumen" value="-">
            <input type="hidden" id="revisi" value="-">
            <input type="hidden" id="tanggal_penyusunan" value="-">
        </div>

        <div class="form-group">
            <label class="form-label" for="deskripsi_mk">Deskripsi Singkat Mata Kuliah (Untuk Konteks AI)</label>
            <textarea class="form-control" id="deskripsi_mk" placeholder="Tuliskan penjelasan singkat tentang fokus mata kuliah ini agar AI dapat menyusun rencana pembelajaran yang akurat." style="min-height: 120px;"></textarea>
        </div>
    </div>

    <!-- PANEL LANGKAH 2: CPL & CPMK -->
    <div class="wizard-step-panel" id="step-panel-2">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    Langkah 2: CPL, CPMK & Sub-CPMK
                </h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Masukkan kurikulum secara manual atau biarkan AI merumuskannya secara otomatis.</p>
            </div>
            <button class="btn btn-primary" id="btn-generate-cpl">
                Generate dengan AI
            </button>
        </div>

        <!-- Indikator Loading CPL -->
        <div class="loading-container" id="loading-cpl" style="display: none;">
            <div class="spinner"></div>
            <h4>AI sedang merumuskan Capaian Pembelajaran...</h4>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">Proses ini memerlukan waktu sekitar 10-15 detik.</p>
        </div>

        <div id="cpl-inputs-container">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" for="cpl">Capaian Pembelajaran Lulusan (CPL) <span style="color: var(--text-muted); font-size: 0.8rem;">(Pisahkan dengan baris baru)</span></label>
                    <textarea class="form-control" id="cpl" placeholder="CPL 1: Menunjukkan sikap bertanggung jawab...&#10;CPL 2: Menguasai konsep teoretis..." style="min-height: 100px;"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="cpmk">Capaian Pembelajaran Mata Kuliah (CPMK) <span style="color: var(--text-muted); font-size: 0.8rem;">(Pisahkan dengan baris baru)</span></label>
                    <textarea class="form-control" id="cpmk" placeholder="CPMK 1: Mampu menjelaskan konsep dasar...&#10;CPMK 2: Mampu menerapkan metode..." style="min-height: 100px;"></textarea>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="sub_cpmk">Sub-CPMK <span style="color: var(--text-muted); font-size: 0.8rem;">(Pisahkan dengan baris baru)</span></label>
                <textarea class="form-control" id="sub_cpmk" placeholder="Sub-CPMK 1.1: Mampu menerangkan ruang lingkup...&#10;Sub-CPMK 1.2: Mampu mendemonstrasikan sintaks..." style="min-height: 100px;"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="bahan_kajian">Bahan Kajian (Materi Utama Kurikulum Makro) <span style="color: var(--text-muted); font-size: 0.8rem;">(Pisahkan dengan baris baru)</span></label>
                <textarea class="form-control" id="bahan_kajian" placeholder="BK1 Membangun keterampilan dalam menggunakan teknologi informasi..." style="min-height: 80px;"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" for="referensi_utama">Referensi Utama <span style="color: var(--text-muted); font-size: 0.8rem;">(Pisahkan dengan baris baru)</span></label>
                    <textarea class="form-control" id="referensi_utama" placeholder="[1]. Direktorat Pembelajaran...&#10;[2]. Stuart J. Russell..." style="min-height: 100px;"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="referensi_pendukung">Referensi Pendukung <span style="color: var(--text-muted); font-size: 0.8rem;">(Pisahkan dengan baris baru)</span></label>
                    <textarea class="form-control" id="referensi_pendukung" placeholder="[1]. Wayne Holmes..." style="min-height: 100px;"></textarea>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" for="sarana_umum">Sarana Pembelajaran - Umum <span style="color: var(--text-muted); font-size: 0.8rem;">(Pisahkan dengan koma atau baris baru)</span></label>
                    <textarea class="form-control" id="sarana_umum" placeholder="Ruang Kelas, Spidol, Proyektor" style="min-height: 60px;">Ruang Kelas, Spidol, Proyektor</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sarana_khusus">Sarana Pembelajaran - Khusus <span style="color: var(--text-muted); font-size: 0.8rem;">(Pisahkan dengan koma atau baris baru)</span></label>
                    <textarea class="form-control" id="sarana_khusus" placeholder="Software AI Tools (ChatGPT, Gemini), Internet" style="min-height: 60px;">-</textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- PANEL LANGKAH 3: GENERATE AI PERTEMUAN -->
    <div class="wizard-step-panel" id="step-panel-3">
        <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            Langkah 3: Membuat Rencana Pembelajaran Mingguan (1-16)
        </h3>
        
        <div style="text-align: center; padding: 2rem 1.5rem;" id="generate-trigger-container">
            <h2 style="margin-top: 0.5rem; font-weight: 800; margin-bottom: 0.5rem;">Siap Menyusun Rencana Mingguan</h2>
            <p style="color: var(--text-muted); max-width: 550px; margin: 0 auto 1.5rem auto; font-size: 0.95rem;">
                AI akan menyusun 16 pertemuan lengkap beserta materi (bahan kajian), metode pembelajaran aktif, estimasi waktu, kriteria penilaian, dan pembobotan nilai secara otomatis sesuai standar OBE.
            </p>

            <div style="max-width: 600px; margin: 0 auto; text-align: left; background: #f8fafc; padding: 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 2rem;" id="generate-options-container">
                <h4 style="font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; color: #1e293b;">Konfigurasi Evaluasi & Penugasan</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="opt_bobot_uts">Bobot UTS (%)</label>
                        <input class="form-control" type="number" id="opt_bobot_uts" value="25" min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="opt_bobot_uas">Bobot UAS (%)</label>
                        <input class="form-control" type="number" id="opt_bobot_uas" value="25" min="0" max="100">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="opt_jumlah_tugas">Jumlah Tugas</label>
                        <select class="form-control" id="opt_jumlah_tugas">
                            <option value="2">2 Tugas</option>
                            <option value="3" selected>3 Tugas</option>
                            <option value="4">4 Tugas</option>
                            <option value="5">5 Tugas</option>
                            <option value="6">6 Tugas</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="opt_jumlah_kuis">Jumlah Kuis</label>
                        <select class="form-control" id="opt_jumlah_kuis">
                            <option value="0">Tanpa Kuis</option>
                            <option value="1">1 Kuis</option>
                            <option value="2" selected>2 Kuis</option>
                            <option value="3">3 Kuis</option>
                            <option value="4">4 Kuis</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="opt_spesifikasi">Spesifikasi Rincian Tugas & Kuis (Opsional)</label>
                    <input class="form-control" type="text" id="opt_spesifikasi" placeholder="Misal: Tugas makalah kelompok, kuis pilihan ganda di LMS.">
                </div>
            </div>

            <button class="btn btn-primary btn-lg" id="btn-generate-meetings" style="font-size: 1.1rem; padding: 1rem 2rem;">
                Generate 16 Pertemuan dengan AI
            </button>
        </div>

        <style>
            @keyframes float {
                0% { transform: translateY(0px); }
                50% { transform: translateY(-10px); }
                100% { transform: translateY(0px); }
            }
        </style>

        <!-- Indikator Loading Pertemuan -->
        <div class="loading-container" id="loading-meetings" style="display: none; flex-direction: column; align-items: center; justify-content: center;">
            <dotlottie-player src="loading.lottie" background="transparent" speed="1" style="width: 280px; height: 280px; margin-bottom: 1rem;" loop autoplay></dotlottie-player>
            <h3 style="margin-top: 0;">AI sedang menyusun silabus 16 pertemuan...</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem; max-width: 500px; text-align: center;">
                Proses ini memerlukan waktu lebih lama (sekitar 20-30 detik) karena AI sedang memformulasikan seluruh rincian pertemuan terstruktur. Harap tunggu dan jangan menutup halaman ini.
            </p>
        </div>
    </div>

    <!-- Script DotLottie Player -->
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>

    <!-- PANEL LANGKAH 4: REVIEW & EDIT PERTEMUAN -->
    <div class="wizard-step-panel" id="step-panel-4">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    Langkah 4: Tinjau & Sesuaikan Rencana Pembelajaran
                </h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Klik salah satu pertemuan untuk mengedit detailnya. Pastikan total bobot bernilai 100%.</p>
            </div>
            <div class="badge badge-indigo" style="font-size: 1rem; padding: 0.5rem 1rem;" id="total-bobot-badge">
                Total Bobot: 0%
            </div>
        </div>

        <div class="alert alert-danger" id="bobot-warning" style="display: none;">
            <strong>Jumlah Bobot Salah:</strong> Akumulasi bobot penilaian saat ini adalah <strong id="current-total-weight">0%</strong>. Harap sesuaikan agar total bobot menjadi <strong>100%</strong> sebelum melakukan finalisasi.
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">Mgu</th>
                        <th style="width: 18%;">Sub-CPMK</th>
                        <th style="width: 18%;">Bahan Kajian (Materi)</th>
                        <th style="width: 14%;">Metode Luring</th>
                        <th style="width: 14%;">Metode Daring</th>
                        <th style="width: 8%;">Waktu</th>
                        <th style="width: 12%;">Indikator Penilaian</th>
                        <th style="width: 12%;">Bentuk Penilaian</th>
                        <th style="width: 4%; text-align: center;">Bobot (%)</th>
                        <th style="width: 60px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="meetings-table-body">
                    <!-- Dinamis diisi lewat JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- TOMBOL NAVIGASI -->
    <div class="wizard-footer">
        <button class="btn btn-secondary" id="btn-wizard-prev" style="display: none;">Kembali</button>
        <div style="margin-left: auto; display: flex; gap: 10px;">
            <button class="btn btn-secondary" id="btn-wizard-draft">Simpan Draft</button>
            <button class="btn btn-primary" id="btn-wizard-next">Selanjutnya</button>
            <button class="btn btn-primary" id="btn-wizard-save" style="display: none;">Simpan & Finalisasi RPS</button>
        </div>
    </div>
</div>

<!-- MODAL EDIT PERTEMUAN -->
<div class="modal-overlay" id="edit-meeting-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="font-weight: 700;" id="edit-modal-title">Edit Detail Pertemuan Ke-X</h3>
            <button class="modal-close" id="btn-close-modal">&times;</button>
        </div>
        <form id="edit-meeting-form">
            <input type="hidden" id="edit-meeting-index">
            
            <div class="form-group">
                <label class="form-label" for="modal-sub-cpmk">Sub-CPMK</label>
                <textarea class="form-control" id="modal-sub-cpmk" rows="2" required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="modal-bahan-kajian">Bahan Kajian (Materi)</label>
                <textarea class="form-control" id="modal-bahan-kajian" rows="2" required></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="modal-metode-luring">Metode Luring</label>
                    <textarea class="form-control" id="modal-metode-luring" rows="2" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="modal-metode-daring">Metode Daring</label>
                    <textarea class="form-control" id="modal-metode-daring" rows="2" required></textarea>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="modal-waktu">Estimasi Waktu</label>
                    <input class="form-control" type="text" id="modal-waktu" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="modal-bobot">Bobot Penilaian (%)</label>
                    <input class="form-control" type="number" id="modal-bobot" min="0" max="100" step="0.1" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="modal-indikator-penilaian">Indikator Penilaian</label>
                <textarea class="form-control" id="modal-indikator-penilaian" rows="2" required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="modal-bentuk-penilaian">Bentuk/Kriteria Penilaian</label>
                <textarea class="form-control" id="modal-bentuk-penilaian" rows="2" required></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" id="btn-cancel-modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/wizard.js"></script>

<?php
require_once 'footer.php';
?>
