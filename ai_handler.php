<?php
require_once 'db.php';
require_once 'auth.php';

// Memastikan user sudah login
requireLogin();

/**
 * Mengirim request POST ke Gemini API.
 */
function callGeminiApi($prompt, $apiKey, $model = 'gemini-3.1-flash', $isJson = true) {
    // Normalisasi nama model ke API resmi
    if ($model === 'gemini-3.1-flash' || $model === 'gemini-1.5-flash') {
        $model = 'gemini-3.1-flash-lite';
    } elseif ($model === 'gemini-2.0-flash') {
        $model = 'gemini-3.5-flash';
    }
    
    // Model dikirim ke URL endpoint API Google Gemini
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    
    $requestData = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];
    
    // Konfigurasi output
    $requestData["generationConfig"] = [
        "maxOutputTokens" => 8192
    ];
    if ($isJson) {
        $requestData["generationConfig"]["responseMimeType"] = "application/json";
    }
    
    // Matikan thinking untuk model Gemini 3.5 (reasoning model) agar tidak menghabiskan token output
    if (stripos($model, '3.5') !== false || stripos($model, '2.0') !== false) {
        $requestData["generationConfig"]["thinkingConfig"] = [
            "thinkingBudget" => 0
        ];
    }
    
    $jsonData = json_encode($requestData);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    // Nonaktifkan verifikasi SSL hanya untuk local development jika terjadi issue sertifikat CA di Windows/Laragon
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorMsg = curl_error($ch);
    curl_close($ch);
    
    if ($response !== false) {
        file_put_contents(__DIR__ . '/debug_response.json', $response);
    }
    
    if ($response === false) {
        throw new Exception("cURL Error: " . $errorMsg);
    }
    
    if ($httpCode !== 200) {
        $errorResponse = json_decode($response, true);
        $message = $errorResponse['error']['message'] ?? "HTTP Code $httpCode";
        throw new Exception("Gemini API Error: " . $message);
    }
    
    $responseData = json_decode($response, true);
    $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    if (empty($text)) {
        throw new Exception("Gemini API mengembalikan konten kosong.");
    }
    
    return $text;
}

/**
 * Generate CPL, CPMK, dan Sub-CPMK berdasarkan detail mata kuliah.
 */
function aiGenerateCplCpmk($courseName, $description, $apiKey, $model) {
    $prompt = "Anda adalah pakar kurikulum Pendidikan Tinggi di Indonesia (standar KPT OBE - Outcome Based Education).
Berdasarkan mata kuliah berikut:
Nama Mata Kuliah: \"{$courseName}\"
Deskripsi: \"{$description}\"

Rumuskan Capaian Pembelajaran Lulusan (CPL) prodi yang dibebankan pada MK ini, Capaian Pembelajaran Mata Kuliah (CPMK), Sub-CPMK yang sesuai, Bahan Kajian (Kurikulum Makro), Referensi Buku Utama dan Pendukung (gunakan referensi nyata/real sesuai bidang keahlian), serta Sarana Pembelajaran Umum dan Khusus secara sistematis dan akademis.

Kembalikan output harus berupa JSON yang valid dengan format persis seperti ini:
{
  \"cpl\": [
    \"CPL 1: Menunjukkan sikap bertanggung jawab atas pekerjaan di bidang keahliannya secara mandiri...\",
    \"CPL 2: ...\"
  ],
  \"cpmk\": [
    \"CPMK 1: Mampu menganalisis...\",
    \"CPMK 2: ...\"
  ],
  \"sub_cpmk\": [
    \"Sub-CPMK 1.1: Mampu menjelaskan konsep...\",
    \"Sub-CPMK 1.2: ...\"
  ],
  \"bahan_kajian\": [
    \"BK1 Membangun keterampilan dalam menggunakan teknologi informasi dan kecerdasan buatan (A.I) secara etis dan produktif sesuai bidang studinya\"
  ],
  \"referensi_utama\": [
    \"[1]. Penulis. (Tahun). Judul Buku Utama. Penerbit\",
    \"[2]. ...\"
  ],
  \"referensi_pendukung\": [
    \"[1]. Penulis. (Tahun). Judul Buku Pendukung. Penerbit\"
  ],
  \"sarana_umum\": [
    \"Ruang Kelas\",
    \"Spidol\",
    \"Proyektor\"
  ],
  \"sarana_khusus\": [
    \"Software AI Tools (ChatGPT, Gemini)\",
    \"Koneksi Internet\"
  ]
}";

    $jsonResult = callGeminiApi($prompt, $apiKey, $model, true);
    $cleanJson = cleanJsonString($jsonResult);
    $decoded = json_decode($cleanJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents(__DIR__ . '/debug_ai.log', "=== CPL GENERATE ERROR ===\n" . "Error: " . json_last_error_msg() . "\n" . "Raw:\n" . $jsonResult . "\n========================\n", FILE_APPEND);
        throw new Exception("Gagal mengurai output JSON dari AI: " . json_last_error_msg() . "\nRespons mentah: " . substr($jsonResult, 0, 500));
    }
    
    return $decoded;
}

/**
 * Generate 16 pertemuan RPS berdasarkan detail kuliah dan CPL/CPMK/Sub-CPMK.
 */
function aiGenerateMeetings($courseName, $sks, $semester, $cpl, $cpmk, $subCpmk, $apiKey, $model, $bobotUts = 25.0, $bobotUas = 25.0, $jumlahTugas = 3, $jumlahKuis = 2, $spesifikasi = '') {
    // Format CPL, CPMK, Sub-CPMK ke string untuk dimasukkan ke prompt
    $cplStr = is_array($cpl) ? implode("\n", $cpl) : $cpl;
    $cpmkStr = is_array($cpmk) ? implode("\n", $cpmk) : $cpmk;
    $subCpmkStr = is_array($subCpmk) ? implode("\n", $subCpmk) : $subCpmk;
    
    $sisaBobot = 100.0 - $bobotUts - $bobotUas;
    $spesifikasiPrompt = !empty($spesifikasi) ? "\n   - KETENTUAN KHUSUS Tugas & Kuis: {$spesifikasi}" : "";

    $prompt = "Anda adalah dosen pakar kurikulum Pendidikan Tinggi di Indonesia.
Buat rencana pembelajaran mingguan sebanyak persis 16 pertemuan untuk mata kuliah berikut:
Nama Mata Kuliah: \"{$courseName}\"
SKS: {$sks} SKS
Semester: {$semester}

Kurikulum & Capaian Pembelajaran:
CPL:
{$cplStr}

CPMK:
{$cpmkStr}

Sub-CPMK:
{$subCpmkStr}

Persyaratan Output:
1. Buat tepat 16 pertemuan.
2. Pertemuan ke-8 harus berupa \"UJIAN TENGAH SEMESTER\".
3. Pertemuan ke-16 harus berupa \"UJIAN AKHIR SEMESTER\".
4. Untuk pertemuan 1-7 dan 9-15, susun rencana pembelajaran yang logis dengan metode pembelajaran yang aktif (seperti Discovery Learning, Case Method, Project-Based Learning, Diskusi Kelompok, atau Kuliah).
5. Estimasi waktu harus disesuaikan dengan SKS. Karena mata kuliah ini adalah {$sks} SKS, maka untuk tatap muka biasa estimasinya adalah \"PB: 2 x 50 menit\" untuk SKS = 2, atau \"PB: 3 x 50 menit\" untuk SKS = 3.
6. Konfigurasi Evaluasi & Penugasan:
   - Pertemuan 8 (UTS) harus memiliki bobot penilaian PERSIS {$bobotUts}%.
   - Pertemuan 16 (UAS) harus memiliki bobot penilaian PERSIS {$bobotUas}%.
   - Di luar UTS/UAS, buatlah tepat {$jumlahTugas} Tugas dan tepat {$jumlahKuis} Kuis yang tersebar di pertemuan-pertemuan tatap muka.
   - Sisa pertemuan lainnya di luar tugas/kuis/UTS/UAS harus memiliki bobot penilaian 0% (tidak ada evaluasi).
   - Kumulatif total bobot Tugas dan Kuis harus berjumlah PERSIS {$sisaBobot}% sehingga total evaluasi (UTS + UAS + Tugas + Kuis) = 100%.{$spesifikasiPrompt}
7. Pisahkan metode pembelajaran untuk tatap muka Luring dan pembelajaran mandiri/penugasan Daring.
8. Pisahkan Indikator Penilaian dengan Kriteria/Bentuk Penilaian. Field \\\"bentuk_penilaian\\\" harus diawali dengan nama kategori evaluasinya (e.g. \\\"TUGAS: ...\\\", \\\"KUIS: ...\\\", \\\"UTS: ...\\\", atau \\\"UAS: ...\\\").
9. Tulis deskripsi pada setiap field (terutama sub_cpmk, bahan_kajian, metode_luring, metode_daring, indikator_penilaian, bentuk_penilaian) secara padat, ringkas, dan jelas (maksimal 15 kata per field) agar respons lengkap dan tidak terpotong.
10. PENTING: Setiap Sub-CPMK HARUS memiliki setidaknya satu pertemuan dengan bobot penilaian > 0%. Pastikan seluruh Sub-CPMK yang diberikan tercakup secara merata dalam distribusi evaluasi. Field \"sub_cpmk\" pada setiap pertemuan harus mengacu ke salah satu Sub-CPMK di atas menggunakan format yang sama (misalnya \"Sub-CPMK 1.1: ...\").

Kembalikan output harus berupa JSON Array of Objects dengan format persis seperti ini:
[
  {
    \"pertemuan_ke\": 1,
    \"sub_cpmk\": \"Mahasiswa mampu menjelaskan prinsip dasar teknologi informasi...\",
    \"bahan_kajian\": \"Penjelasan RPS dan kontrak perkuliahan, definisi literasi...\",
    \"metode_luring\": \"Discovery learning (PB: 2 x 50 menit)\\nKuis 1: Pengantar Literasi Teknologi\",
    \"metode_daring\": \"Membagikan Materi dan Kuis di edlink.id (PT: 2x60 menit + KM: 2x60 menit)\",
    \"estimasi_waktu\": \"2 x 50 Menit\",
    \"indikator_penilaian\": \"Ketepatan mahasiswa dalam menjelaskan:\\n- Definisi literasi teknologi\\n- Peran teknologi dalam kehidupan\",
    \"bentuk_penilaian\": \"Kriteria: Rubrik Edlink\\nTeknik: Kuis pilihan ganda atau jawaban singkat\",
    \"bobot_penilaian\": 1.25
  },
  ...
  {
    \"pertemuan_ke\": 8,
    \"sub_cpmk\": \"UJIAN TENGAH SEMESTER\",
    \"bahan_kajian\": \"Materi pertemuan 1 s.d 7\",
    \"metode_luring\": \"Ujian Tertulis / Esai\",
    \"metode_daring\": \"-\",
    \"estimasi_waktu\": \"2 x 50 Menit\",
    \"indikator_penilaian\": \"Kesesuaian jawaban dengan kunci jawaban\",
    \"bentuk_penilaian\": \"Teknik: Tes Tertulis\",
    \"bobot_penilaian\": 25.0
  },
  ...
  {
    \"pertemuan_ke\": 16,
    \"sub_cpmk\": \"UJIAN AKHIR SEMESTER\",
    \"bahan_kajian\": \"Materi pertemuan 9 s.d 15\",
    \"metode_luring\": \"Ujian Tertulis / Presentasi Proyek\",
    \"metode_daring\": \"-\",
    \"estimasi_waktu\": \"2 x 50 Menit\",
    \"indikator_penilaian\": \"Kualitas laporan dan keandalan prototype AI\",
    \"bentuk_penilaian\": \"Kriteria: Rubrik Laporan dan Rubrik Presentasi\\nTeknik: Penilaian Hasil Karya\",
    \"bobot_penilaian\": 35.0
  }
]";

    $jsonResult = callGeminiApi($prompt, $apiKey, $model, true);
    $cleanJson = cleanJsonString($jsonResult);
    $decoded = json_decode($cleanJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents(__DIR__ . '/debug_ai.log', "=== MEETINGS GENERATE ERROR ===\n" . "Error: " . json_last_error_msg() . "\n" . "Raw:\n" . $jsonResult . "\n========================\n", FILE_APPEND);
        throw new Exception("Gagal mengurai JSON rincian pertemuan dari AI: " . json_last_error_msg() . "\nRespons mentah: " . substr($jsonResult, 0, 500));
    }
    
    return $decoded;
}

/**
 * Membersihkan string JSON dari tag markdown (```json ... ```), karakter BOM, atau trailing garbage.
 */
function cleanJsonString($string) {
    // Bersihkan BOM (Byte Order Mark) jika ada
    $string = preg_replace('/^[\x{FEFF}\x{200B}]+/u', '', $string);
    
    // Hapus blok kode markdown jika ada
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $string, $matches)) {
        $string = $matches[1];
    }
    
    $string = trim($string);
    
    // Potong karakter sampah tambahan di luar kurung utama (e.g. extra closing brackets)
    $string = extractFirstJsonStructure($string);
    
    // Perbaikan mandiri jika penutup JSON terpotong/hilang dari AI
    if (substr($string, 0, 1) === '{' && substr($string, -1) !== '}') {
        $string .= '}';
    } elseif (substr($string, 0, 1) === '[' && substr($string, -1) !== ']') {
        $string .= ']';
    }
    
    return $string;
}

/**
 * Mengambil struktur JSON pertama (Array atau Objek) yang valid berdasarkan balance tanda kurung pembuka dan penutup.
 * Sangat berguna jika AI menambahkan karakter penutup ekstra di luar struktur utama.
 */
function extractFirstJsonStructure($string) {
    $firstChar = substr($string, 0, 1);
    if ($firstChar !== '{' && $firstChar !== '[') {
        return $string;
    }
    
    $openChar = $firstChar;
    $closeChar = ($openChar === '{') ? '}' : ']';
    
    $len = strlen($string);
    $balance = 0;
    $inString = false;
    $escape = false;
    
    for ($i = 0; $i < $len; $i++) {
        $char = $string[$i];
        
        if ($escape) {
            $escape = false;
            continue;
        }
        
        if ($char === '\\') {
            $escape = true;
            continue;
        }
        
        if ($char === '"') {
            $inString = !$inString;
            continue;
        }
        
        if (!$inString) {
            if ($char === $openChar) {
                $balance++;
            } elseif ($char === $closeChar) {
                $balance--;
                if ($balance === 0) {
                    // Ketemu akhir dari struktur utama, potong di sini
                    return substr($string, 0, $i + 1);
                }
            }
        }
    }
    
    return $string;
}
