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

// Mengatur HTTP Headers untuk mengunduh dokumen MS Word (.doc)
$filename = "RPS_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $rps['nama_mk']) . ".doc";
header("Content-Type: application/vnd.ms-word");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Disposition: attachment; filename=" . $filename);
?>
<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:w="urn:schemas-microsoft-com:office:word" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($rps['nama_mk']) ?></title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #000000;
            line-height: 1.4;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table, th, td {
            border: 1px solid #000000;
        }

        th, td {
            padding: 8px;
            vertical-align: top;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        .kop-title {
            font-weight: bold;
            font-size: 12pt;
            text-transform: uppercase;
            text-align: center;
        }

        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 20px;
            margin-bottom: 10px;
            text-decoration: underline;
        }

        ul {
            margin: 0;
            padding-left: 20px;
        }

        li {
            margin-bottom: 4px;
        }

        .diagram-container {
            border: 1px solid #000000;
            padding: 15px;
            text-align: center;
            background-color: #ffffff;
            margin-bottom: 20px;
        }

        .cpmk-box {
            border: 1px solid #000000;
            background-color: #f2f2f2;
            padding: 10px;
            text-align: left;
            margin-bottom: 10px;
        }

        .subcpmk-box {
            border: 1px solid #000000;
            background-color: #f9f9f9;
            padding: 8px;
            text-align: left;
            margin: 5px 0;
        }
    </style>
</head>
<body>

    <!-- 1. KOP SURAT / HEADER DOKUMEN -->
    <table>
        <tr>
            <td style="width: 20%; text-align: center; vertical-align: middle; font-weight: bold; font-size: 14pt;">
                UVERS
            </td>
            <td class="kop-title" style="width: 80%; vertical-align: middle; text-align: center;">
                RENCANA PEMBELAJARAN SEMESTER (RPS)
            </td>
        </tr>
    </table>

    <!-- 2. IDENTITAS MATA KULIAH -->
    <h3 class="section-title">I. Identitas Mata Kuliah</h3>
    <table>
        <tr>
            <td style="width: 30%; font-weight: bold; background-color: #f2f2f2;">Fakultas / Program Studi</td>
            <td><?= htmlspecialchars($rps['program_studi']) ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Kode Mata Kuliah</td>
            <td><?= htmlspecialchars($rps['kode_mk']) ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Mata Kuliah</td>
            <td><?= htmlspecialchars($rps['nama_mk']) ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Bobot SKS</td>
            <td><?= (int)$rps['sks'] ?> SKS</td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Semester</td>
            <td><?= (int)$rps['semester'] ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Prasyarat Mata Kuliah</td>
            <td><?= htmlspecialchars($rps['prasyarat'] ?? '-') ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Dosen Pengampu</td>
            <td><?= htmlspecialchars($rps['username']) ?></td>
        </tr>
    </table>

    <br clear="all" style="page-break-before: always;" />

    <!-- 3. CAPAIAN PEMBELAJARAN -->
    <h3 class="section-title">II. Capaian Pembelajaran</h3>
    <table>
        <tr>
            <td style="width: 30%; font-weight: bold; background-color: #f2f2f2;">Capaian Pembelajaran Lulusan (CPL) yang dibebankan pada Mata Kuliah</td>
            <td>
                <?php if (!empty($cpl_list)): ?>
                    <ul>
                        <?php foreach ($cpl_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Capaian Pembelajaran Mata Kuliah (CPMK)</td>
            <td>
                <?php if (!empty($cpmk_list)): ?>
                    <ul>
                        <?php foreach ($cpmk_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Sub-Capaian Pembelajaran Mata Kuliah (Sub-CPMK)</td>
            <td>
                <?php if (!empty($sub_cpmk_list)): ?>
                    <ul>
                        <?php foreach ($sub_cpmk_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Deskripsi Singkat Mata Kuliah</td>
            <td><?= nl2br(htmlspecialchars($rps['deskripsi_mk'] ?? '')) ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Bahan Kajian</td>
            <td>
                <?php if (!empty($bahan_kajian_list)): ?>
                    <ul>
                        <?php foreach ($bahan_kajian_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <br clear="all" style="page-break-before: always;" />

    <!-- 4. REFERENSI & SARANA PEMBELAJARAN -->
    <h3 class="section-title">III. Referensi & Sarana Pembelajaran</h3>
    <table>
        <tr>
            <td style="width: 30%; font-weight: bold; background-color: #f2f2f2;">Referensi Utama</td>
            <td>
                <?php if (!empty($ref_utama_list)): ?>
                    <ul>
                        <?php foreach ($ref_utama_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Referensi Pendukung</td>
            <td>
                <?php if (!empty($ref_pendukung_list)): ?>
                    <ul>
                        <?php foreach ($ref_pendukung_list as $item): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Sarana Pembelajaran - Umum</td>
            <td><?= htmlspecialchars($rps['sarana_umum'] ?? 'Ruang Kelas, Spidol, Proyektor') ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f2f2f2;">Sarana Pembelajaran - Khusus</td>
            <td><?= htmlspecialchars($rps['sarana_khusus'] ?? '-') ?></td>
        </tr>
    </table>

    <br clear="all" style="page-break-before: always;" />

    <!-- 5. DIAGRAM ANALISIS PEMBELAJARAN -->
    <h3 class="section-title">IV. Diagram Analisis Pembelajaran</h3>
    <div class="diagram-container">
        <div class="cpmk-box">
            <strong>CPMK:</strong><br>
            <?php foreach ($cpmk_list as $item): ?>
                &bull; <?= htmlspecialchars(trim($item)) ?><br>
            <?php endforeach; ?>
        </div>
        
        <table style="width: 100%; border: none;">
            <?php foreach (array_reverse($sub_cpmk_list) as $index => $item): ?>
                <tr>
                    <td style="border: none; text-align: center; font-size: 14pt; padding: 2px 0;">&uarr;</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #000000; background-color: #f9f9f9; padding: 10px; text-align: left;">
                        <strong>Sub-CPMK <?= count($sub_cpmk_list) - $index ?>:</strong><br>
                        <?= htmlspecialchars(trim($item)) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <br clear="all" style="page-break-before: always;" />

    <!-- 6. RINCIAN KEGIATAN PEMBELAJARAN MINGGUAN -->
    <h3 class="section-title">V. Rencana Kegiatan Pembelajaran Mingguan</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">Minggu</th>
                <th style="width: 18%;">Sub-CPMK</th>
                <th style="width: 17%;">Materi Pembelajaran (Bahan Kajian)</th>
                <th style="width: 15%;">Metode Luring (Durasi)</th>
                <th style="width: 15%;">Metode Daring (Durasi)</th>
                <th style="width: 8%;">Waktu</th>
                <th style="width: 10%;">Indikator Penilaian</th>
                <th style="width: 8%;">Bentuk Penilaian</th>
                <th style="width: 4%;">Bobot (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($meetings as $meeting): ?>
                <tr>
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

    <!-- 6. KOMPONEN EVALUASI OBE -->
    <h3 class="section-title">VI. Komponen Evaluasi (Outcome-Based Education)</h3>
    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No.</th>
                <th style="width: 15%; text-align: left;">Metode Evaluasi</th>
                <?php foreach ($sub_cpmk_list as $index => $item): 
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
            if (!function_exists('getSubCpmkIndexForWordMeeting')) {
                function getSubCpmkIndexForWordMeeting($meetingSubCpmk, $subCpmkList) {
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
                    
                    // Prioritas 3: Cocokkan hanya nomor utama jika tidak ada yang cocok
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
                    $idx = getSubCpmkIndexForWordMeeting($m['sub_cpmk'], $sub_cpmk_list);
                    $utsSubCpmkIndices[$idx] = true;
                } elseif ($pk >= 9 && $pk <= 15) {
                    $idx = getSubCpmkIndexForWordMeeting($m['sub_cpmk'], $sub_cpmk_list);
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
                    $subIndex = getSubCpmkIndexForWordMeeting($m['sub_cpmk'], $sub_cpmk_list);
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
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="2" style="text-align: left;">Total Persentase Komponen Evaluasi</td>
                <?php foreach ($subCpmkTotals as $totalVal): ?>
                    <td style="text-align: center;"><?= $totalVal > 0 ? rtrim(rtrim(number_format($totalVal, 2, '.', ''), '0'), '.') : '-' ?></td>
                <?php endforeach; ?>
                <td style="text-align: center; color: #10b981;"><?= rtrim(rtrim(number_format($totalAllWeight, 2, '.', ''), '0'), '.') ?>%</td>
            </tr>
    </table>

    <br/>

    <!-- 6.5. DAFTAR TUGAS DAN KUIS -->
    <h3 class="section-title">VI.b. Daftar Rincian Tugas dan Kuis</h3>
    <table style="width: 100%; border-collapse: collapse;">
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

    <br/>

    <!-- 7. TANDA TANGAN PENGESAHAN -->
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 50%; border: none; text-align: center;">
                Dosen Penyusun<br/><br/><br/><br/>
                <u><strong><?= htmlspecialchars($rps['username']) ?></strong></u><br/>
                NIP. Dosen Pengampu
            </td>
            <td style="width: 50%; border: none; text-align: center;">
                Koordinator Program Studi<br/><br/><br/><br/>
                <u><strong>( Koordinator Prodi )</strong></u><br/>
                NIP. Koordinator Prodi
            </td>
        </tr>
    </table>

</body>
</html>
