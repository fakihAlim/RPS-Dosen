<?php
require_once 'mail_config.php';

class SmtpMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $secure;
    private $timeout = 10;
    private $log_file;

    public function __construct() {
        $this->host = SMTP_HOST;
        $this->port = SMTP_PORT;
        $this->username = SMTP_USER;
        $this->password = SMTP_PASS;
        $this->secure = strtolower(SMTP_SECURE);
        $this->log_file = __DIR__ . '/debug_smtp.log';
    }

    private function log($message) {
        if (SMTP_DEBUG) {
            $log_content = "[" . date('Y-m-d H:i:s') . "] SMTP DEBUG: " . $message . "\n";
            file_put_contents($this->log_file, $log_content, FILE_APPEND);
        }
    }

    private function readResponse($socket, $expected_code) {
        $response = '';
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') {
                break;
            }
        }
        $code = substr($response, 0, 3);
        $this->log("S: " . trim($response));
        if ($code !== (string)$expected_code) {
            throw new Exception("SMTP Error: Diharapkan respon {$expected_code}, tetapi mendapatkan {$code}. Detail: {$response}");
        }
        return $response;
    }

    private function writeCommand($socket, $command) {
        $this->log("C: " . trim($command));
        fwrite($socket, $command . "\r\n");
    }

    public function send($to, $subject, $htmlContent, $textContent = '') {
        // Cek jika masih menggunakan kredensial placeholder default
        if ($this->username === 'your-email@gmail.com' || empty($this->password) || $this->password === 'your-app-password-here') {
            $this->log("Pengiriman diabaikan. SMTP masih menggunakan konfigurasi placeholder default.");
            return false;
        }

        $socket = null;
        try {
            $host = $this->host;
            if ($this->secure === 'ssl') {
                $host = 'ssl://' . $host;
            }

            $this->log("Menghubungkan ke {$host}:{$this->port}...");
            $socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);

            if (!$socket) {
                throw new Exception("Gagal menghubungkan ke server SMTP ({$errno}): {$errstr}");
            }

            $this->readResponse($socket, 220);

            // Send EHLO
            $this->writeCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $this->readResponse($socket, 250);

            // TLS Upgrade if tls secure is chosen
            if ($this->secure === 'tls') {
                $this->writeCommand($socket, "STARTTLS");
                $this->readResponse($socket, 220);

                // Enable encryption on socket stream
                $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                // Add TLS 1.2/1.3 constraints if supported for better security
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto_method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }

                stream_context_set_option($socket, 'ssl', 'verify_peer', false);
                stream_context_set_option($socket, 'ssl', 'verify_peer_name', false);

                if (!stream_socket_enable_crypto($socket, true, $crypto_method)) {
                    throw new Exception("Gagal melakukan upgrade enkripsi TLS pada koneksi.");
                }

                // Send EHLO again after TLS upgrade
                $this->writeCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                $this->readResponse($socket, 250);
            }

            // Authentication login
            $this->writeCommand($socket, "AUTH LOGIN");
            $this->readResponse($socket, 334);

            $this->writeCommand($socket, base64_encode($this->username));
            $this->readResponse($socket, 334);

            $this->writeCommand($socket, base64_encode($this->password));
            $this->readResponse($socket, 235);

            // MAIL FROM
            $this->writeCommand($socket, "MAIL FROM: <" . SMTP_FROM . ">");
            $this->readResponse($socket, 250);

            // RCPT TO
            $this->writeCommand($socket, "RCPT TO: <" . $to . ">");
            $this->readResponse($socket, 250);

            // DATA
            $this->writeCommand($socket, "DATA");
            $this->readResponse($socket, 354);

            // Build MIME-compliant headers
            $boundary = md5(uniqid(time()));
            $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
            $encodedFromName = "=?UTF-8?B?" . base64_encode(SMTP_FROM_NAME) . "?=";

            $headers = [
                "From: {$encodedFromName} <" . SMTP_FROM . ">",
                "To: <{$to}>",
                "Subject: {$encodedSubject}",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
                "Date: " . date('r'),
                "Message-ID: <" . uniqid('', true) . "@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">"
            ];

            $body = "";
            foreach ($headers as $h) {
                $body .= $h . "\r\n";
            }
            $body .= "\r\n"; // Header-body separator

            // Plain text part
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= ($textContent ? $textContent : strip_tags($htmlContent)) . "\r\n\r\n";

            // HTML part
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $htmlContent . "\r\n\r\n";

            // End boundary
            $body .= "--{$boundary}--\r\n";

            // Prevent SMTP injection by escaping leading periods
            $body = preg_replace('/^\./m', '..', $body);

            // Send body and end marker
            fwrite($socket, $body);
            $this->writeCommand($socket, ".");
            $this->readResponse($socket, 250);

            // QUIT
            $this->writeCommand($socket, "QUIT");
            $this->readResponse($socket, 221);

            fclose($socket);
            $this->log("Email berhasil dikirim ke {$to} menggunakan SMTP.");
            return true;
        } catch (Exception $e) {
            $this->log("Gagal mengirim email ke {$to}. Error: " . $e->getMessage());
            if ($socket) {
                @fclose($socket);
            }
            return false;
        }
    }
}

/**
 * Fungsi pembungkus untuk mengirim email verifikasi secara indah
 */
function sendVerificationEmail($to, $username, $code) {
    $subject = "Kode Verifikasi Pendaftaran - RPS Generator AI";

    // Email HTML Template (menggunakan desain dark/glassmorphic sesuai tema RPS Generator)
    $htmlContent = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Verifikasi Email - RPS Generator AI</title>
        <style>
            body {
                background-color: #0b0f19;
                color: #f3f4f6;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 40px auto;
                background-color: #161f30;
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.5);
            }
            .header {
                background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
                padding: 30px 20px;
                text-align: center;
            }
            .logo {
                font-size: 24px;
                font-weight: 800;
                color: #ffffff;
                text-decoration: none;
                letter-spacing: 1px;
            }
            .content {
                padding: 40px 30px;
            }
            h2 {
                color: #ffffff;
                font-size: 22px;
                margin-top: 0;
            }
            p {
                color: #9ca3af;
                font-size: 16px;
                line-height: 1.6;
            }
            .otp-container {
                background-color: #1f2a40;
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 8px;
                padding: 20px;
                text-align: center;
                margin: 30px 0;
            }
            .otp-code {
                font-size: 32px;
                font-weight: 700;
                letter-spacing: 8px;
                color: #818cf8;
                font-family: monospace;
            }
            .footer {
                background-color: #0e1420;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #6b7280;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
            }
            .footer a {
                color: #6366f1;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='logo'>RPS Generator AI</span>
            </div>
            <div class='content'>
                <h2>Halo, $username!</h2>
                <p>Terima kasih telah mendaftar akun dosen di RPS Generator AI. Untuk mengaktifkan akun Anda, silakan masukkan kode OTP verifikasi berikut pada halaman verifikasi:</p>
                
                <div class='otp-container'>
                    <div class='otp-code'>$code</div>
                </div>
                
                <p>Kode di atas berlaku selama <strong>15 menit</strong>. Jangan bagikan kode ini kepada siapapun demi keamanan akun Anda.</p>
                <p>Jika Anda tidak merasa mendaftar di sistem kami, abaikan email ini.</p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " RPS Generator AI. Semua Hak Dilindungi.<br>
                Sistem Pembuatan RPS Terstandarisasi AI.
            </div>
        </div>
    </body>
    </html>
    ";

    $textContent = "Halo, $username!\n\n" .
                   "Terima kasih telah mendaftar di RPS Generator AI.\n" .
                   "Kode verifikasi pendaftaran Anda adalah: $code\n\n" .
                   "Kode berlaku selama 15 menit. Masukkan kode ini pada halaman verifikasi.\n\n" .
                   "Terima kasih.";

    global $pdo;
    $log_id = null;

    // Tulis ke database tabel email_logs untuk menggantikan file debug_emails.txt demi keamanan
    try {
        $stmt = $pdo->prepare("INSERT INTO email_logs (to_email, subject, body, verification_code, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$to, $subject, $htmlContent, $code, 'sending']);
        $log_id = $pdo->lastInsertId();
    } catch (PDOException $e) {
        // Silently continue jika logging gagal agar alur utama tidak terputus
    }

    // Kirim menggunakan SMTP
    $mailer = new SmtpMailer();
    $sent = $mailer->send($to, $subject, $htmlContent, $textContent);

    $status = $sent ? 'sent' : 'failed';

    if (!$sent) {
        // Sebagai fallback di localhost jika SMTP gagal/belum diisi, pastikan user mendapat feedback
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $_SESSION['mail_fallback_active'] = true;
    }
    
    // Perbarui status log email di database
    if ($log_id) {
        try {
            $updateStmt = $pdo->prepare("UPDATE email_logs SET status = ? WHERE id = ?");
            $updateStmt->execute([$status, $log_id]);
        } catch (PDOException $e) {
            // Silently continue
        }
    }

    return $sent;
}

// Function to get the email information from check-mail.org API
function verify_email($email) {
    $apiKey = $_ENV['CHECK_MAIL_API_KEY'] ?? '';
    if (empty($apiKey)) {
        // Fallback jika API key tidak diisi agar pendaftaran tidak terhambat
        return ['block' => false, 'valid' => true, 'is_disposable' => false, 'risk' => 0];
    }
    $url = "https://api.check-mail.org/v2/";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(["email" => $email]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout 5 detik agar user tidak menunggu terlalu lama

    // Execute the request
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        // Fallback ke status aman jika koneksi API error
        return ['block' => false, 'valid' => true, 'is_disposable' => false, 'risk' => 0];
    }

    curl_close($ch);

    // Decode and return the response
    return json_decode($response, true);
}

// Function to just determine if an email should be blocked
function should_email_be_blocked($email) {
    $emailData = verify_email($email);
    if ($emailData && isset($emailData['block'])) {
        return (bool)$emailData['block'];
    }
    return false;
}
