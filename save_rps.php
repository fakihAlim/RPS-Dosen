<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'auth.php';

// Memastikan user sudah login
if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Silakan login terlebih dahulu.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Baca input JSON
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Format request tidak valid.']);
    exit;
}

$rps_id = (int)($input['id'] ?? 0);
$kode_mk = trim($input['kode_mk'] ?? '');
$nama_mk = trim($input['nama_mk'] ?? '');
$sks = (int)($input['sks'] ?? 2);
$semester = (int)($input['semester'] ?? 1);
$program_studi = trim($input['program_studi'] ?? '');
$cpl = trim($input['cpl'] ?? '');
$cpmk = trim($input['cpmk'] ?? '');
$sub_cpmk = trim($input['sub_cpmk'] ?? '');
$deskripsi_mk = trim($input['deskripsi_mk'] ?? '');
$no_dokumen = trim($input['no_dokumen'] ?? 'F-M2.STD-PD-3.6');
$revisi = trim($input['revisi'] ?? '02');
$tanggal_penyusunan = trim($input['tanggal_penyusunan'] ?? '30 November 2023');
$prasyarat = trim($input['prasyarat'] ?? '-');
$bahan_kajian = trim($input['bahan_kajian'] ?? '');
$referensi_utama = trim($input['referensi_utama'] ?? '');
$referensi_pendukung = trim($input['referensi_pendukung'] ?? '');
$sarana_umum = trim($input['sarana_umum'] ?? '');
$sarana_khusus = trim($input['sarana_khusus'] ?? '');
$meetings = $input['meetings'] ?? [];
$is_draft = (bool)($input['is_draft'] ?? false);
$status = $is_draft ? 'draft' : 'final';

if (empty($kode_mk) || empty($nama_mk) || empty($program_studi)) {
    echo json_encode(['status' => 'error', 'message' => 'Detail mata kuliah (Kode, Nama, SKS, Semester, Prodi) wajib diisi.']);
    exit;
}

// Validasi ketat HANYA jika bukan draft
if (!$is_draft) {
    if (count($meetings) !== 16) {
        echo json_encode(['status' => 'error', 'message' => 'Jumlah pertemuan harus tepat 16 pertemuan untuk difinalisasi.']);
        exit;
    }

    // Validasi bobot penilaian kumulatif
    $total_weight = 0;
    foreach ($meetings as $meeting) {
        $total_weight += (float)($meeting['bobot_penilaian'] ?? 0);
    }
    // Toleransi margin error float (misal 99.9 s.d. 100.1)
    if (abs($total_weight - 100.0) > 0.1) {
        echo json_encode(['status' => 'error', 'message' => 'Akumulasi bobot penilaian seluruh pertemuan harus tepat 100%. Saat ini: ' . $total_weight . '%']);
        exit;
    }
}

try {
    $pdo->beginTransaction();
    
    if ($rps_id > 0) {
        // Mode EDIT: Pastikan data milik user
        $stmt = $pdo->prepare("SELECT id FROM rps WHERE id = ? AND user_id = ?");
        $stmt->execute([$rps_id, $user_id]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Akses ditolak atau RPS tidak ditemukan.']);
            exit;
        }
        
        // Update data utama rps termasuk status draft/final
        $stmt = $pdo->prepare("UPDATE rps SET kode_mk = ?, nama_mk = ?, sks = ?, semester = ?, program_studi = ?, cpl = ?, cpmk = ?, sub_cpmk = ?, deskripsi_mk = ?, no_dokumen = ?, revisi = ?, tanggal_penyusunan = ?, prasyarat = ?, bahan_kajian = ?, referensi_utama = ?, referensi_pendukung = ?, sarana_umum = ?, sarana_khusus = ?, status = ? WHERE id = ?");
        $stmt->execute([$kode_mk, $nama_mk, $sks, $semester, $program_studi, $cpl, $cpmk, $sub_cpmk, $deskripsi_mk, $no_dokumen, $revisi, $tanggal_penyusunan, $prasyarat, $bahan_kajian, $referensi_utama, $referensi_pendukung, $sarana_umum, $sarana_khusus, $status, $rps_id]);
        
        // Hapus pertemuan lama (akan diganti baru)
        $stmt = $pdo->prepare("DELETE FROM rps_meetings WHERE rps_id = ?");
        $stmt->execute([$rps_id]);
        
        logActivity($user_id, 'EDIT_RPS', 'Mengubah dokumen RPS ID ' . $rps_id . ': ' . $nama_mk . ' (' . $status . ')');
        
    } else {
        // Mode BARU: Insert data utama rps termasuk status draft/final
        $stmt = $pdo->prepare("INSERT INTO rps (user_id, kode_mk, nama_mk, sks, semester, program_studi, cpl, cpmk, sub_cpmk, deskripsi_mk, no_dokumen, revisi, tanggal_penyusunan, prasyarat, bahan_kajian, referensi_utama, referensi_pendukung, sarana_umum, sarana_khusus, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $kode_mk, $nama_mk, $sks, $semester, $program_studi, $cpl, $cpmk, $sub_cpmk, $deskripsi_mk, $no_dokumen, $revisi, $tanggal_penyusunan, $prasyarat, $bahan_kajian, $referensi_utama, $referensi_pendukung, $sarana_umum, $sarana_khusus, $status]);
        $rps_id = $pdo->lastInsertId();
        
        logActivity($user_id, 'CREATE_RPS', 'Membuat dokumen RPS baru ID ' . $rps_id . ': ' . $nama_mk . ' (' . $status . ')');
    }
    
    // Insert pertemuan baru (jika ada)
    if (!empty($meetings)) {
        $stmt = $pdo->prepare("INSERT INTO rps_meetings (rps_id, pertemuan_ke, sub_cpmk, bahan_kajian, estimasi_waktu, bobot_penilaian, metode_luring, metode_daring, indikator_penilaian, bentuk_penilaian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($meetings as $meeting) {
            $stmt->execute([
                $rps_id,
                (int)$meeting['pertemuan_ke'],
                trim($meeting['sub_cpmk'] ?? ''),
                trim($meeting['bahan_kajian'] ?? ''),
                trim($meeting['estimasi_waktu'] ?? ''),
                (float)($meeting['bobot_penilaian'] ?? 0.00),
                trim($meeting['metode_luring'] ?? ''),
                trim($meeting['metode_daring'] ?? ''),
                trim($meeting['indikator_penilaian'] ?? ''),
                trim($meeting['bentuk_penilaian'] ?? '')
            ]);
        }
    }
    
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => $is_draft ? 'Draft RPS berhasil disimpan!' : 'RPS berhasil difinalisasi!', 'rps_id' => $rps_id]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan database: ' . $e->getMessage()]);
}
