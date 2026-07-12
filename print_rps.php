<?php
require_once 'db.php';
require_once 'auth.php';

// Memastikan user sudah login
requireLogin();

$user_id = $_SESSION['user_id'];
$rps_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($rps_id <= 0) {
    die("ID RPS tidak valid.");
}

try {
    // Load data RPS utama
    $stmt = $pdo->prepare("SELECT rps.*, u.username FROM rps 
                           JOIN users u ON rps.user_id = u.id 
                           WHERE rps.id = ? AND rps.user_id = ?");
    $stmt->execute([$rps_id, $user_id]);
    $rps = $stmt->fetch();
    
    if (!$rps) {
        die("RPS tidak ditemukan atau Anda tidak memiliki akses.");
    }
    
    // Load rincian pertemuan
    $stmt = $pdo->prepare("SELECT * FROM rps_meetings WHERE rps_id = ? ORDER BY pertemuan_ke ASC");
    $stmt->execute([$rps_id]);
    $meetings = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Kesalahan database: " . $e->getMessage());
}

// Pisahkan CPL, CPMK, Sub-CPMK berdasarkan baris baru untuk ditampilkan sebagai list
$cpl_list = array_filter(explode("\n", $rps['cpl'] ?? ''));
$cpmk_list = array_filter(explode("\n", $rps['cpmk'] ?? ''));
$sub_cpmk_list = array_filter(explode("\n", $rps['sub_cpmk'] ?? ''));
$bahan_kajian_list = array_filter(explode("\n", $rps['bahan_kajian'] ?? ''));
$ref_utama_list = array_filter(explode("\n", $rps['referensi_utama'] ?? ''));
$ref_pendukung_list = array_filter(explode("\n", $rps['referensi_pendukung'] ?? ''));
$sarana_umum_list = array_filter(explode("\n", $rps['sarana_umum'] ?? ''));
$sarana_khusus_list = array_filter(explode("\n", $rps['sarana_khusus'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak RPS - <?= htmlspecialchars($rps['nama_mk']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #000;
            background-color: #fff;
            line-height: 1.4;
            padding: 20px;
            font-size: 9.5pt;
        }

        .no-print-bar {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            font-size: 10pt;
        }
        .btn-secondary {
            background-color: #4b5563;
        }

        /* Kop Table */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .kop-table td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: middle;
        }

        /* Tabel Identitas dan Makro */
        .standard-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .standard-table td, .standard-table th {
            border: 1px solid #000;
            padding: 8px 10px;
            vertical-align: top;
        }

        .standard-table th {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .identitas-title {
            font-weight: bold;
            background-color: #f8fafc;
            width: 30%;
        }

        /* Diagram Analisis Pembelajaran */
        .diagram-section {
            border: 1px solid #000;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }

        .diagram-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 20px;
            font-size: 10pt;
            text-decoration: underline;
        }

        .cpmk-box {
            background-color: #3b82f6;
            color: white;
            padding: 12px;
            border-radius: 4px;
            font-size: 9pt;
            text-align: left;
            margin-bottom: 15px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.3;
        }

        .diagram-arrow {
            font-size: 16pt;
            font-weight: bold;
            color: #4b5563;
            margin: 8px 0;
        }

        .subcpmk-stack {
            display: flex;
            flex-direction: column-reverse;
            gap: 8px;
            max-width: 700px;
            margin: 0 auto;
        }

        .subcpmk-node {
            border: 1px solid #3b82f6;
            background-color: #eff6ff;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 8.5pt;
            text-align: left;
            line-height: 1.3;
        }

        .subcpmk-header {
            font-weight: bold;
            color: #1d4ed8;
            margin-bottom: 4px;
        }

        /* Tabel Rincian Mingguan */
        .weekly-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 25px;
            font-size: 8.5pt;
        }

        .weekly-table th, .weekly-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }

        .weekly-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: center;
            font-size: 8.5pt;
        }

        /* Signatures */
        .signatures-container {
            display: flex;
            justify-content: space-around;
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 45%;
            text-align: center;
        }

        .signature-space {
            height: 80px;
        }

        ul {
            margin: 0;
            padding-left: 18px;
        }

        li {
            margin-bottom: 4px;
        }

        @media print {
            .no-print-bar {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            @page {
                size: A4;
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body>

    <!-- Bar Aksi (Hanya muncul di Browser) -->
    <div class="no-print-bar">
        <span style="font-family: Arial, sans-serif; font-size: 10pt; color: #374151;">
            <strong>Pratinjau Cetak:</strong> Hubungkan ke printer atau simpan dokumen sebagai PDF.
        </span>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
            <button onclick="window.print()" class="btn">Cetak / Simpan PDF</button>
        </div>
    </div>

    <!-- 1. KOP SURAT (Page 1) -->
    <table class="kop-table">
        <tr>
            <td style="width: 15%; text-align: center; vertical-align: middle; font-weight: bold; font-size: 14pt; border: 1px solid #000; font-family: 'Arial Black', sans-serif; color: #1e3a8a;">
                UVERS
            </td>
            <td style="width: 60%; text-align: center; vertical-align: middle; font-weight: bold; font-size: 12pt; text-transform: uppercase;">
                Rencana Pembelajaran Semester (RPS)
            </td>
            <td style="width: 25%; text-align: center; vertical-align: middle; font-size: 10pt; font-weight: bold;">
                HALAMAN: 1
            </td>
        </tr>
    </table>

    <!-- 2. IDENTITAS MATA KULIAH -->
    <table class="standard-table">
        <tr>
            <td class="identitas-title" style="width: 25%;">Fakultas / Program Studi</td>
            <td style="width: 2%;">:</td>
            <td><?= htmlspecialchars($rps['program_studi']) ?></td>
        </tr>
        <tr>
            <td class="identitas-title">Kode Mata Kuliah</td>
            <td>:</td>
            <td><?= htmlspecialchars($rps['kode_mk']) ?></td>
        </tr>
        <tr>
            <td class="identitas-title">Mata Kuliah</td>
            <td>:</td>
            <td><?= htmlspecialchars($rps['nama_mk']) ?></td>
        </tr>
        <tr>
            <td class="identitas-title">Bobot SKS</td>
            <td>:</td>
            <td><?= (int)$rps['sks'] ?> SKS</td>
        </tr>
        <tr>
            <td class="identitas-title">Semester</td>
            <td>:</td>
            <td><?= (int)$rps['semester'] ?></td>
        </tr>
        <tr>
            <td class="identitas-title">Prasyarat Mata Kuliah</td>
            <td>:</td>
            <td><?= htmlspecialchars($rps['prasyarat'] ?? '-') ?></td>
        </tr>
        <tr>
            <td class="identitas-title">Dosen Pengampu</td>
            <td>:</td>
            <td><?= htmlspecialchars($rps['username']) ?></td>
        </tr>
    </table>

    <div style="page-break-after: always;"></div>

    <!-- 3. CAPAIAN PEMBELAJARAN (CPL, CPMK, Sub-CPMK, Deskripsi, Bahan Kajian) -->
    <table class="standard-table">
        <tr>
            <td class="identitas-title" style="width: 25%;">Capaian Pembelajaran Lulusan (CPL) yang dibebankan pada Mata Kuliah</td>
            <td>
                <?php if (!empty($cpl_list)): ?>
                    <ul>
                        <?php foreach ($cpl_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>-</p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="identitas-title">Capaian Pembelajaran Mata Kuliah (CPMK)</td>
            <td>
                <?php if (!empty($cpmk_list)): ?>
                    <ul>
                        <?php foreach ($cpmk_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>-</p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="identitas-title">Sub-Capaian Pembelajaran Mata Kuliah (Sub-CPMK)</td>
            <td>
                <?php if (!empty($sub_cpmk_list)): ?>
                    <ul>
                        <?php foreach ($sub_cpmk_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>-</p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="identitas-title">Deskripsi singkat mata kuliah</td>
            <td><?= nl2br(htmlspecialchars($rps['deskripsi_mk'] ?? '')) ?></td>
        </tr>
        <tr>
            <td class="identitas-title">Bahan Kajian</td>
            <td>
                <?php if (!empty($bahan_kajian_list)): ?>
                    <ul>
                        <?php foreach ($bahan_kajian_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>-</p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <div style="page-break-after: always;"></div>

    <!-- 4. REFERENSI DAN SARANA PEMBELAJARAN -->
    <table class="standard-table">
        <tr>
            <td class="identitas-title" style="width: 25%;">Daftar Referensi</td>
            <td>
                <strong>Utama:</strong>
                <?php if (!empty($ref_utama_list)): ?>
                    <ul style="margin-bottom: 10px;">
                        <?php foreach ($ref_utama_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="margin-bottom: 10px;">-</p>
                <?php endif; ?>

                <strong>Pendukung:</strong>
                <?php if (!empty($ref_pendukung_list)): ?>
                    <ul>
                        <?php foreach ($ref_pendukung_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>-</p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="identitas-title">Sarana Pembelajaran</td>
            <td>
                <strong>Umum:</strong>
                <p style="margin: 0 0 10px 0;"><?= htmlspecialchars($rps['sarana_umum'] ?? 'Ruang Kelas, Spidol, Proyektor') ?></p>
                
                <strong>Khusus:</strong>
                <p style="margin: 0;"><?= htmlspecialchars($rps['sarana_khusus'] ?? '-') ?></p>
            </td>
        </tr>
    </table>

    <div style="page-break-after: always;"></div>

    <!-- 5. DIAGRAM ANALISIS PEMBELAJARAN -->
    <div class="diagram-section">
        <div class="diagram-title">Diagram Analisis Pembelajaran Mata Kuliah</div>
        
        <!-- CPMK Box -->
        <div class="cpmk-box">
            <strong>CPMK:</strong>
            <?php foreach ($cpmk_list as $item): ?>
                <div style="margin-top: 4px; font-size: 8.5pt;">&bull; <?= htmlspecialchars(trim($item)) ?></div>
            <?php endforeach; ?>
        </div>
        
        <div class="diagram-arrow">&uarr;</div>
        
        <!-- Stack Sub-CPMK -->
        <div class="subcpmk-stack">
            <?php foreach ($sub_cpmk_list as $index => $item): ?>
                <?php 
                    $clean_text = trim($item);
                    // Ambil kode Sub-CPMK jika ada (misal Sub-CPMK1 atau Sub-CPMK 1)
                    $node_title = "Sub-CPMK " . ($index + 1);
                    if (preg_match('/^(Sub-CPMK\s*\d+[:\s.-]*)/i', $clean_text, $matches)) {
                        $node_title = rtrim($matches[0], " :.-");
                        $clean_text = trim(substr($clean_text, strlen($matches[0])));
                    }
                ?>
                <?php if ($index > 0): ?>
                    <div class="diagram-arrow">&uarr;</div>
                <?php endif; ?>
                <div class="subcpmk-node">
                    <div class="subcpmk-header"><?= htmlspecialchars($node_title) ?></div>
                    <div><?= htmlspecialchars($clean_text) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="page-break-after: always;"></div>

    <!-- 6. RINCIAN KEGIATAN PEMBELAJARAN MINGGUAN -->
    <h3 style="font-size: 10pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase;">
        Rencana Kegiatan Pembelajaran Mingguan (16 Pertemuan)
    </h3>
    
    <table class="weekly-table">
        <thead>
            <tr>
                <th style="width: 3%; text-align: center;">Minggu Ke-</th>
                <th style="width: 17%;">Sub-CPMK Kemampuan Akhir yang Diharapkan</th>
                <th style="width: 17%;">Materi Pembelajaran</th>
                <th style="width: 15%;">Bentuk/Metode Luring (Durasi)</th>
                <th style="width: 15%;">Bentuk/Metode Daring (Durasi)</th>
                <th style="width: 8%;">Alokasi Waktu</th>
                <th style="width: 10%;">Indikator Penilaian</th>
                <th style="width: 10%;">Kriteria & Teknik Penilaian</th>
                <th style="width: 5%; text-align: center;">Bobot (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($meetings as $meeting): ?>
                <tr <?php if ($meeting['pertemuan_ke'] == 8 || $meeting['pertemuan_ke'] == 16) echo 'style="background-color: #f9fafb; font-weight: bold;"'; ?>>
                    <td style="text-align: center; font-weight: bold;"><?= (int)$meeting['pertemuan_ke'] ?></td>
                    <td><?= nl2br(htmlspecialchars($meeting['sub_cpmk'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($meeting['bahan_kajian'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($meeting['metode_luring'] ?? $meeting['metode_pembelajaran'] ?? '-')) ?></td>
                    <td><?= nl2br(htmlspecialchars($meeting['metode_daring'] ?? '-')) ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($meeting['estimasi_waktu'] ?? '-') ?></td>
                    <td><?= nl2br(htmlspecialchars($meeting['indikator_penilaian'] ?? $meeting['indikator_kriteria'] ?? '-')) ?></td>
                    <td><?= nl2br(htmlspecialchars($meeting['bentuk_penilaian'] ?? '-')) ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= (float)$meeting['bobot_penilaian'] ?>%</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 7. KOMPONEN EVALUASI OBE -->
    <h3 style="font-size: 10pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; margin-top: 25px; page-break-inside: avoid;">
        Komponen Evaluasi (Outcome-Based Education)
    </h3>
    
    <table class="weekly-table" style="margin-top: 5px; margin-bottom: 25px; page-break-inside: avoid; table-layout: fixed; width: 100%;">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No.</th>
                <th style="width: 15%; text-align: left;">Metode Evaluasi</th>
                <?php foreach ($sub_cpmk_list as $index => $item): 
                    // Ambil hanya label singkat Sub-CPMK (misal: "Sub-CPMK 1.1" atau "Sub-CPMK 1")
                    $shortLabel = 'Sub-CPMK ' . ($index + 1);
                    if (preg_match('/Sub[- ]?CPMK\s*(\d+(?:\.\d+)?)/i', $item, $labelMatch)) {
                        $shortLabel = 'Sub-CPMK ' . $labelMatch[1];
                    }
                ?>
                    <th style="text-align: center; font-size: 8pt; font-weight: bold; padding: 4px;"><?= htmlspecialchars($shortLabel) ?></th>
                <?php endforeach; ?>
                <th style="width: 12%; text-align: center;">Bobot Evaluasi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Inisialisasi kategori evaluasi standar: TUGAS, KUIS, UTS, UAS
            $evaluations = [
                'TUGAS' => array_fill(0, count($sub_cpmk_list), 0.0),
                'KUIS' => array_fill(0, count($sub_cpmk_list), 0.0),
                'UTS' => array_fill(0, count($sub_cpmk_list), 0.0),
                'UAS' => array_fill(0, count($sub_cpmk_list), 0.0)
            ];
            
            // Helper pencocokan Sub-CPMK khusus
            if (!function_exists('getSubCpmkIndexForMeeting')) {
                function getSubCpmkIndexForMeeting($meetingSubCpmk, $subCpmkList) {
                    // Ekstrak nomor lengkap dari meeting (mis: "2.1" dari "Sub-CPMK 2.1: ...")
                    $meetingNum = '';
                    if (preg_match('/Sub[- ]?CPMK\s*(\d+(?:\.\d+)?)/i', $meetingSubCpmk, $mMatch)) {
                        $meetingNum = $mMatch[1];
                    }
                    
                    // Prioritas 1: Cocokkan berdasarkan nomor lengkap (mis: "2.1" == "2.1")
                    if (!empty($meetingNum)) {
                        foreach ($subCpmkList as $idx => $mainSub) {
                            if (preg_match('/Sub[- ]?CPMK\s*(\d+(?:\.\d+)?)/i', $mainSub, $sMatch)) {
                                if ($sMatch[1] === $meetingNum) {
                                    return $idx;
                                }
                            }
                        }
                    }
                    
                    // Prioritas 2: Cocokkan berdasarkan substring teks deskripsi
                    foreach ($subCpmkList as $idx => $mainSub) {
                        $cleanMain = trim(preg_replace('/^Sub[- ]?CPMK\s*\d+(?:\.\d+)?\s*[:.-]*/i', '', $mainSub));
                        if (!empty($cleanMain) && strlen($cleanMain) > 3 && stripos($meetingSubCpmk, substr($cleanMain, 0, 20)) !== false) {
                            return $idx;
                        }
                    }
                    
                    // Prioritas 3: Cocokkan hanya nomor utama jika tidak ada yang cocok (mis: "2" cocok dengan Sub-CPMK pertama grup "2.x")
                    if (!empty($meetingNum) && strpos($meetingNum, '.') === false) {
                        foreach ($subCpmkList as $idx => $mainSub) {
                            if (preg_match('/Sub[- ]?CPMK\s*(\d+)/i', $mainSub, $sMatch)) {
                                if ($sMatch[1] === $meetingNum) {
                                    return $idx;
                                }
                            }
                        }
                    }
                    
                    return 0;
                }
            }

            // Langkah 1: Kumpulkan Sub-CPMK unik dari paruh pertama (pertemuan 1-7) dan paruh kedua (pertemuan 9-15)
            $utsSubCpmkIndices = [];
            $uasSubCpmkIndices = [];
            foreach ($meetings as $m) {
                $pk = (int)$m['pertemuan_ke'];
                if ($pk >= 1 && $pk <= 7) {
                    $idx = getSubCpmkIndexForMeeting($m['sub_cpmk'], $sub_cpmk_list);
                    $utsSubCpmkIndices[$idx] = true;
                } elseif ($pk >= 9 && $pk <= 15) {
                    $idx = getSubCpmkIndexForMeeting($m['sub_cpmk'], $sub_cpmk_list);
                    $uasSubCpmkIndices[$idx] = true;
                }
            }
            
            // Konversi ke array index unik
            $utsSubCpmkIndices = array_keys($utsSubCpmkIndices);
            $uasSubCpmkIndices = array_keys($uasSubCpmkIndices);
            
            // Fallback jika kosong
            if (empty($utsSubCpmkIndices)) {
                $utsSubCpmkIndices = [0];
            }
            if (empty($uasSubCpmkIndices)) {
                $uasSubCpmkIndices = [count($sub_cpmk_list) - 1];
            }

            // Langkah 2: Petakan setiap pertemuan ke evaluasi
            foreach ($meetings as $m) {
                $weight = (float)$m['bobot_penilaian'];
                if ($weight <= 0) continue;
                
                $method = 'TUGAS';
                if ($m['pertemuan_ke'] == 8) {
                    $method = 'UTS';
                } elseif ($m['pertemuan_ke'] == 16) {
                    $method = 'UAS';
                } else {
                    $bentuk = strtoupper($m['bentuk_penilaian']);
                    if (stripos($bentuk, 'UTS') !== false) {
                        $method = 'UTS';
                    } elseif (stripos($bentuk, 'UAS') !== false) {
                        $method = 'UAS';
                    } elseif (stripos($bentuk, 'KUIS') !== false) {
                        $method = 'KUIS';
                    } else {
                        $method = 'TUGAS';
                    }
                }
                
                if ($method === 'UTS') {
                    // Bagi rata bobot UTS ke semua Sub-CPMK paruh pertama (pertemuan 1-7)
                    $perSub = round($weight / count($utsSubCpmkIndices), 2);
                    foreach ($utsSubCpmkIndices as $si => $idx) {
                        $evaluations['UTS'][$idx] += ($si === array_key_last($utsSubCpmkIndices))
                            ? $weight - ($perSub * (count($utsSubCpmkIndices) - 1))
                            : $perSub;
                    }
                } elseif ($method === 'UAS') {
                    // Bagi rata bobot UAS ke semua Sub-CPMK paruh kedua (pertemuan 9-15)
                    $perSub = round($weight / count($uasSubCpmkIndices), 2);
                    foreach ($uasSubCpmkIndices as $si => $idx) {
                        $evaluations['UAS'][$idx] += ($si === array_key_last($uasSubCpmkIndices))
                            ? $weight - ($perSub * (count($uasSubCpmkIndices) - 1))
                            : $perSub;
                    }
                } else {
                    $subIndex = getSubCpmkIndexForMeeting($m['sub_cpmk'], $sub_cpmk_list);
                    $evaluations[$method][$subIndex] += $weight;
                }
            }

            // Hanya tampilkan metode yang memiliki bobot > 0
            $activeEvaluations = [];
            foreach (['TUGAS', 'KUIS', 'UTS', 'UAS'] as $mth) {
                if (array_sum($evaluations[$mth]) > 0) {
                    $activeEvaluations[$mth] = $evaluations[$mth];
                }
            }

            $no = 1;
            $totalAllWeight = 0;
            $subCpmkTotals = array_fill(0, count($sub_cpmk_list), 0.0);
            
            foreach ($activeEvaluations as $method => $weights):
                $methodTotal = array_sum($weights);
                $totalAllWeight += $methodTotal;
            ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td style="font-weight: bold;"><?= htmlspecialchars($method) ?></td>
                    <?php foreach ($weights as $idx => $w): 
                        $subCpmkTotals[$idx] += $w;
                    ?>
                        <td style="text-align: center;"><?= $w > 0 ? rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.') : '-' ?></td>
                    <?php endforeach; ?>
                    <td style="text-align: center; font-weight: bold;"><?= rtrim(rtrim(number_format($methodTotal, 2, '.', ''), '0'), '.') ?>%</td>
                </tr>
            <?php endforeach; ?>
            <tr style="background-color: #f3f4f6; font-weight: bold;">
                <td colspan="2" style="text-align: left;">Total Persentase Komponen Evaluasi</td>
                <?php foreach ($subCpmkTotals as $totalVal): ?>
                    <td style="text-align: center;"><?= $totalVal > 0 ? rtrim(rtrim(number_format($totalVal, 2, '.', ''), '0'), '.') : '-' ?></td>
                <?php endforeach; ?>
                <td style="text-align: center; color: #10b981;"><?= rtrim(rtrim(number_format($totalAllWeight, 2, '.', ''), '0'), '.') ?>%</td>
            </tr>
    </table>

    <!-- 7.5. DAFTAR TUGAS DAN KUIS -->
    <h3 style="font-size: 10pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; margin-top: 25px; page-break-inside: avoid;">
        Daftar Rincian Tugas dan Kuis
    </h3>
    
    <table class="weekly-table" style="margin-top: 5px; margin-bottom: 25px; page-break-inside: avoid;">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No.</th>
                <th style="width: 15%; text-align: center;">Pertemuan Ke-</th>
                <th style="width: 35%; text-align: left;">Bentuk Penilaian / Nama Tugas</th>
                <th style="width: 35%; text-align: left;">Indikator Penilaian</th>
                <th style="width: 10%; text-align: center;">Bobot (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $tugasNo = 1;
            $hasTugas = false;
            foreach ($meetings as $m):
                $weight = (float)$m['bobot_penilaian'];
                if ($weight <= 0 || $m['pertemuan_ke'] == 8 || $m['pertemuan_ke'] == 16) {
                    continue;
                }
                $hasTugas = true;
            ?>
                <tr>
                    <td style="text-align: center;"><?= $tugasNo++ ?></td>
                    <td style="text-align: center; font-weight: bold;">Minggu <?= (int)$m['pertemuan_ke'] ?></td>
                    <td><?= nl2br(htmlspecialchars($m['bentuk_penilaian'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($m['indikator_penilaian'] ?? $m['indikator_kriteria'] ?? '-')) ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= number_format($weight, 2, ',', '.') ?>%</td>
                </tr>
            <?php endforeach; 
            if (!$hasTugas):
            ?>
                <tr>
                    <td colspan="5" style="text-align: center; font-style: italic;">Tidak ada tugas atau kuis terstruktur selain UTS dan UAS.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 8. TANDA TANGAN PENGESAHAN -->
    <div class="signatures-container">
        <div class="signature-box">
            <p>Dosen Penyusun</p>
            <div class="signature-space"></div>
            <p style="text-decoration: underline; font-weight: bold;"><?= htmlspecialchars($rps['username']) ?></p>
            <p>NIP. Dosen Pengampu</p>
        </div>
        <div class="signature-box">
            <p>Koordinator Program Studi</p>
            <div class="signature-space"></div>
            <p style="text-decoration: underline; font-weight: bold;">( Koordinator Prodi )</p>
            <p>NIP. Koordinator Prodi</p>
        </div>
    </div>

    <!-- Auto-trigger Cetak -->
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 600);
        };
    </script>
</body>
</html>
