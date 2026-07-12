<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'auth.php';
require_once 'ai_handler.php';

// Proteksi API hanya untuk user logged-in
if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Silakan login terlebih dahulu.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil API Key dan model Dosen dari database
try {
    $stmt = $pdo->prepare("SELECT api_key, ai_model FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (empty($user['api_key'])) {
        echo json_encode(['status' => 'error', 'message' => 'API Key belum diatur. Silakan atur API Key di menu Pengaturan.']);
        exit;
    }
    
    $apiKey = decryptApiKey($user['api_key']);
    $model = $user['ai_model'] ?? 'gemini-3.1-flash';
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Baca body input JSON
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Format request tidak valid.']);
    exit;
}

$action = $input['action'] ?? '';

try {
    if ($action === 'generate_cpl_cpmk') {
        $nama_mk = trim($input['nama_mk'] ?? '');
        $deskripsi = trim($input['deskripsi'] ?? '');
        
        if (empty($nama_mk)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama mata kuliah wajib diisi.']);
            exit;
        }
        
        $result = aiGenerateCplCpmk($nama_mk, $deskripsi, $apiKey, $model);
        logActivity($user_id, 'GENERATE_RPS_CPL_CPMK', 'Generate CPL & CPMK via AI untuk mata kuliah: ' . $nama_mk);
        echo json_encode(['status' => 'success', 'data' => $result]);
        exit;
        
    } elseif ($action === 'generate_meetings') {
        $nama_mk = trim($input['nama_mk'] ?? '');
        $sks = (int)($input['sks'] ?? 2);
        $semester = (int)($input['semester'] ?? 1);
        $cpl = $input['cpl'] ?? [];
        $cpmk = $input['cpmk'] ?? [];
        $sub_cpmk = $input['sub_cpmk'] ?? [];
        
        if (empty($nama_mk)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama mata kuliah wajib diisi.']);
            exit;
        }
        
        if (empty($cpl) || empty($cpmk) || empty($sub_cpmk)) {
            echo json_encode(['status' => 'error', 'message' => 'CPL, CPMK, dan Sub-CPMK wajib diisi atau di-generate terlebih dahulu.']);
            exit;
        }
        
        $bobot_uts = (float)($input['bobot_uts'] ?? 25.0);
        $bobot_uas = (float)($input['bobot_uas'] ?? 25.0);
        $jumlah_tugas = (int)($input['jumlah_tugas'] ?? 3);
        $jumlah_kuis = (int)($input['jumlah_kuis'] ?? 2);
        $spesifikasi = trim($input['spesifikasi'] ?? '');
        
        $result = aiGenerateMeetings($nama_mk, $sks, $semester, $cpl, $cpmk, $sub_cpmk, $apiKey, $model, $bobot_uts, $bobot_uas, $jumlah_tugas, $jumlah_kuis, $spesifikasi);
        
        // Normalisasi bobot agar jumlahnya tepat 100.0%
        if (is_array($result) && !empty($result)) {
            // Selalu pastikan pertemuan 8 (UTS) dan 16 (UAS) memiliki bobot persis sesuai konfigurasi input
            foreach ($result as $idx => $m) {
                if ($m['pertemuan_ke'] == 8) {
                    $result[$idx]['bobot_penilaian'] = $bobot_uts;
                } elseif ($m['pertemuan_ke'] == 16) {
                    $result[$idx]['bobot_penilaian'] = $bobot_uas;
                }
            }

            $sisaBobot = 100.0 - $bobot_uts - $bobot_uas;
            
            // Hitung total bobot non-UTS/UAS saat ini
            $totalNonExamWeight = 0.0;
            foreach ($result as $m) {
                $pk = (int)$m['pertemuan_ke'];
                if ($pk != 8 && $pk != 16) {
                    $totalNonExamWeight += (float)($m['bobot_penilaian'] ?? 0.0);
                }
            }
            
            if ($totalNonExamWeight > 0) {
                $newTotalNonExam = 0.0;
                $maxIdx = -1;
                $maxVal = -1.0;
                
                foreach ($result as $idx => $m) {
                    $pk = (int)$m['pertemuan_ke'];
                    if ($pk != 8 && $pk != 16) {
                        $w = (float)($m['bobot_penilaian'] ?? 0.0);
                        $scaled = ($w / $totalNonExamWeight) * $sisaBobot;
                        $rounded = round($scaled * 2) / 2; // Bulatkan ke kelipatan 0.5 terdekat
                        $result[$idx]['bobot_penilaian'] = $rounded;
                        $newTotalNonExam += $rounded;
                        
                        if ($rounded > $maxVal) {
                            $maxVal = $rounded;
                            $maxIdx = $idx;
                        }
                    }
                }
                
                $diff = $sisaBobot - $newTotalNonExam;
                if (abs($diff) > 0.01 && $maxIdx != -1) {
                    $result[$maxIdx]['bobot_penilaian'] = (float)$result[$maxIdx]['bobot_penilaian'] + $diff;
                }
            } else {
                // Jika total non-uts/uas adalah 0 tapi sisa bobot > 0, bagi rata ke tugas yang ada
                $targetIndices = [];
                foreach ($result as $idx => $m) {
                    $pk = (int)$m['pertemuan_ke'];
                    if ($pk != 8 && $pk != 16) {
                        $targetIndices[] = $idx;
                    }
                }
                if (!empty($targetIndices)) {
                    $share = round($sisaBobot / count($targetIndices) * 2) / 2;
                    $allocated = 0.0;
                    foreach ($targetIndices as $idx) {
                        $result[$idx]['bobot_penilaian'] = $share;
                        $allocated += $share;
                    }
                    $diff = $sisaBobot - $allocated;
                }
            }
            
            // Pastikan SEMUA Sub-CPMK tercakup oleh setidaknya 1 pertemuan berbobot > 0
            if (!empty($sub_cpmk) && is_array($sub_cpmk)) {
                // Deteksi Sub-CPMK mana saja yang sudah ter-cover
                $coveredSubCpmk = [];
                foreach ($result as $m) {
                    $w = (float)($m['bobot_penilaian'] ?? 0.0);
                    if ($w > 0 && !empty($m['sub_cpmk'])) {
                        // Ekstrak nomor lengkap dari meeting sub_cpmk
                        $meetingNum = '';
                        if (preg_match('/Sub[- ]?CPMK\s*(\d+(?:\.\d+)?)/i', $m['sub_cpmk'], $mMatch)) {
                            $meetingNum = $mMatch[1];
                        }
                        
                        foreach ($sub_cpmk as $scIdx => $scItem) {
                            // Cocokkan berdasarkan nomor lengkap Sub-CPMK (exact match)
                            if (preg_match('/Sub[- ]?CPMK\s*(\d+(?:\.\d+)?)/i', $scItem, $scMatch)) {
                                if ($scMatch[1] === $meetingNum) {
                                    $coveredSubCpmk[$scIdx] = true;
                                    continue;
                                }
                            }
                            // Juga cocokkan berdasarkan substring teks deskripsi
                            $cleanSc = trim(preg_replace('/^Sub[- ]?CPMK\s*\d+(?:\.\d+)?\s*[:.-]*/i', '', $scItem));
                            if (!empty($cleanSc) && strlen($cleanSc) > 5 && stripos($m['sub_cpmk'], substr($cleanSc, 0, 20)) !== false) {
                                $coveredSubCpmk[$scIdx] = true;
                            }
                        }
                    }
                }
                
                // Cari Sub-CPMK yang belum ter-cover
                $uncovered = [];
                foreach ($sub_cpmk as $scIdx => $scItem) {
                    if (!isset($coveredSubCpmk[$scIdx])) {
                        $uncovered[] = $scIdx;
                    }
                }
                
                // Untuk setiap Sub-CPMK yang belum ter-cover, cari pertemuan non-UTS/UAS 
                // yang berbobot 0 lalu assign Sub-CPMK tersebut dan beri bobot kecil
                if (!empty($uncovered)) {
                    // Cari pertemuan yang bisa di-reassign
                    $zeroWeightMeetings = [];
                    foreach ($result as $idx => $m) {
                        $pk = (int)$m['pertemuan_ke'];
                        if ($pk != 8 && $pk != 16 && (float)$m['bobot_penilaian'] <= 0) {
                            $zeroWeightMeetings[] = $idx;
                        }
                    }
                    
                    // Ambil bobot kecil dari pertemuan berbobot tertinggi (non-UTS/UAS) untuk didistribusikan
                    $weightPerUncovered = 2.0; // default 2% per Sub-CPMK yang belum ter-cover
                    $totalNeeded = count($uncovered) * $weightPerUncovered;
                    
                    // Kurangi dari pertemuan berbobot terbesar (non-UTS/UAS)
                    $sortedByWeight = [];
                    foreach ($result as $idx => $m) {
                        $pk = (int)$m['pertemuan_ke'];
                        if ($pk != 8 && $pk != 16 && (float)$m['bobot_penilaian'] > $weightPerUncovered) {
                            $sortedByWeight[] = ['idx' => $idx, 'w' => (float)$m['bobot_penilaian']];
                        }
                    }
                    usort($sortedByWeight, function($a, $b) { return $b['w'] <=> $a['w']; });
                    
                    $remaining = $totalNeeded;
                    foreach ($sortedByWeight as $sw) {
                        if ($remaining <= 0) break;
                        $take = min($remaining, (float)$result[$sw['idx']]['bobot_penilaian'] * 0.3);
                        $take = max($take, $weightPerUncovered);
                        $take = min($take, $remaining);
                        $result[$sw['idx']]['bobot_penilaian'] -= $take;
                        $remaining -= $take;
                    }
                    
                    // Assign Sub-CPMK yang belum ter-cover
                    foreach ($uncovered as $ui => $scIdx) {
                        $scItem = $sub_cpmk[$scIdx];
                        // Cari label pendek
                        $scLabel = 'Sub-CPMK ' . ($scIdx + 1);
                        if (preg_match('/Sub[- ]?CPMK\s*(\d+(?:\.\d+)?)/i', $scItem, $m2)) {
                            $scLabel = 'Sub-CPMK ' . $m2[1];
                        }
                        
                        if (isset($zeroWeightMeetings[$ui])) {
                            // Assign ke pertemuan berbobot 0
                            $targetIdx = $zeroWeightMeetings[$ui];
                            $result[$targetIdx]['sub_cpmk'] = $scItem;
                            $result[$targetIdx]['bobot_penilaian'] = $weightPerUncovered;
                            $result[$targetIdx]['bentuk_penilaian'] = 'TUGAS: Penugasan ' . $scLabel;
                        } else {
                            // Cari pertemuan yang sudah memiliki Sub-CPMK yang sama dan duplikat paling banyak
                            // lalu reassign sub_cpmk-nya
                            foreach ($result as $ridx => $rm) {
                                $pk = (int)$rm['pertemuan_ke'];
                                if ($pk != 8 && $pk != 16 && (float)$rm['bobot_penilaian'] > 0) {
                                    // Hitung berapa pertemuan lain yang pakai Sub-CPMK yang sama
                                    $dupeCount = 0;
                                    foreach ($result as $rm2) {
                                        if (stripos($rm2['sub_cpmk'], substr($rm['sub_cpmk'], 0, 20)) !== false) {
                                            $dupeCount++;
                                        }
                                    }
                                    if ($dupeCount > 1) {
                                        $result[$ridx]['sub_cpmk'] = $scItem;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Re-normalisasi akhir setelah redistribusi
                    $finalTotal = 0.0;
                    foreach ($result as $m) {
                        $finalTotal += (float)$m['bobot_penilaian'];
                    }
                    if (abs($finalTotal - 100.0) > 0.01) {
                        // Cari pertemuan berbobot terbesar non-UTS/UAS untuk adjust
                        $adjIdx = 0;
                        $adjMax = -1.0;
                        foreach ($result as $idx => $m) {
                            $pk = (int)$m['pertemuan_ke'];
                            if ($pk != 8 && $pk != 16 && (float)$m['bobot_penilaian'] > $adjMax) {
                                $adjMax = (float)$m['bobot_penilaian'];
                                $adjIdx = $idx;
                            }
                        }
                        $result[$adjIdx]['bobot_penilaian'] = (float)$result[$adjIdx]['bobot_penilaian'] + (100.0 - $finalTotal);
                    }
                }
            }
        }

        logActivity($user_id, 'GENERATE_RPS_MEETINGS', 'Generate detail pertemuan via AI untuk mata kuliah: ' . $nama_mk . ' (' . $sks . ' SKS)');
        echo json_encode(['status' => 'success', 'data' => $result]);
        exit;
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali.']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
