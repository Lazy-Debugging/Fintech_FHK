<?php

namespace App\Services;

class EmailNotificationService
{
    public static ?string $lastError = null;
    public static array $debugLogs = [];

    public static function getEnvValue(string $key, mixed $default = null): mixed
    {
        if (function_exists('env')) {
            try {
                $val = env($key);
                if ($val !== null) return $val;
            } catch (\Throwable $e) {}
        }
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (isset($_SERVER[$key])) return $_SERVER[$key];
        $val = getenv($key);
        return ($val !== false && $val !== '') ? $val : $default;
    }

    private static function logInfo(string $msg, array $ctx = []): void
    {
        try {
            if (class_exists(\Illuminate\Support\Facades\Log::class)) {
                \Illuminate\Support\Facades\Log::info($msg, $ctx);
            }
        } catch (\Throwable $e) {}
    }

    private static function logWarning(string $msg, array $ctx = []): void
    {
        try {
            if (class_exists(\Illuminate\Support\Facades\Log::class)) {
                \Illuminate\Support\Facades\Log::warning($msg, $ctx);
            }
        } catch (\Throwable $e) {}
    }

    private static function logError(string $msg, array $ctx = []): void
    {
        try {
            if (class_exists(\Illuminate\Support\Facades\Log::class)) {
                \Illuminate\Support\Facades\Log::error($msg, $ctx);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Pure-PHP Direct Socket SMTP Client (Zero-Dependency)
     */
    public static function sendSmtpDirect(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        $host        = self::getEnvValue('MAIL_HOST', 'smtp.gmail.com');
        $username    = self::getEnvValue('MAIL_USERNAME', '24n40004@student.unika.ac.id');
        $rawPassword = self::getEnvValue('MAIL_PASSWORD', 'vkte vmnm fpyx ktro');
        $password    = str_replace(' ', '', $rawPassword);
        $from        = self::getEnvValue('MAIL_FROM_ADDRESS', $username);
        $fromName    = self::getEnvValue('MAIL_FROM_NAME', 'notifikiasifhk');

        $configs = [
            ['host' => $host, 'port' => 587, 'tls' => true],
            ['host' => 'ssl://' . $host, 'port' => 465, 'tls' => false],
            ['host' => 'smtp.googlemail.com', 'port' => 587, 'tls' => true],
            ['host' => 'ssl://smtp.googlemail.com', 'port' => 465, 'tls' => false],
        ];

        foreach ($configs as $cfg) {
            try {
                $ctx = stream_context_create([
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ]
                ]);

                $socket = @stream_socket_client($cfg['host'] . ':' . $cfg['port'], $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
                if (!$socket) {
                    self::$debugLogs[] = "Socket connect to {$cfg['host']}:{$cfg['port']} failed: {$errstr}";
                    continue;
                }

                stream_set_timeout($socket, 12);
                $res = fgets($socket, 515);

                fputs($socket, "EHLO " . (gethostname() ?: 'localhost') . "\r\n");
                while ($line = fgets($socket, 515)) {
                    if (substr($line, 3, 1) == ' ') break;
                }

                if ($cfg['tls']) {
                    fputs($socket, "STARTTLS\r\n");
                    $res = fgets($socket, 515);
                    if (strpos((string)$res, '220') === false) {
                        fclose($socket);
                        self::$debugLogs[] = "STARTTLS failed: {$res}";
                        continue;
                    }
                    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                        fclose($socket);
                        self::$debugLogs[] = "TLS crypto enable failed on {$cfg['host']}";
                        continue;
                    }
                    fputs($socket, "EHLO " . (gethostname() ?: 'localhost') . "\r\n");
                    while ($line = fgets($socket, 515)) {
                        if (substr($line, 3, 1) == ' ') break;
                    }
                }

                // AUTH LOGIN
                fputs($socket, "AUTH LOGIN\r\n");
                $res = fgets($socket, 515);
                if (strpos((string)$res, '334') === false) {
                    fclose($socket);
                    continue;
                }

                fputs($socket, base64_encode($username) . "\r\n");
                $res = fgets($socket, 515);
                if (strpos((string)$res, '334') === false) {
                    fclose($socket);
                    continue;
                }

                fputs($socket, base64_encode($password) . "\r\n");
                $res = fgets($socket, 515);
                if (strpos((string)$res, '235') === false) {
                    fclose($socket);
                    self::$debugLogs[] = "SMTP Auth rejected: {$res}";
                    continue;
                }

                // MAIL FROM & RCPT TO
                fputs($socket, "MAIL FROM: <{$from}>\r\n");
                $res = fgets($socket, 515);
                if (strpos((string)$res, '250') === false) {
                    fclose($socket);
                    continue;
                }

                fputs($socket, "RCPT TO: <{$to}>\r\n");
                $res = fgets($socket, 515);
                if (strpos((string)$res, '250') === false && strpos((string)$res, '251') === false) {
                    fclose($socket);
                    continue;
                }

                // DATA
                fputs($socket, "DATA\r\n");
                $res = fgets($socket, 515);
                if (strpos((string)$res, '354') === false) {
                    fclose($socket);
                    continue;
                }

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: {$fromName} <{$from}>\r\n";
                $headers .= "To: <{$to}>\r\n";
                $headers .= "Subject: {$subject}\r\n";
                $headers .= "Date: " . date('r') . "\r\n";
                $headers .= "X-Mailer: FHK-DirectSocket/1.0\r\n";

                fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
                $res = fgets($socket, 515);
                if (strpos((string)$res, '250') === false) {
                    fclose($socket);
                    continue;
                }

                fputs($socket, "QUIT\r\n");
                fclose($socket);

                self::logInfo("Email successfully sent via Direct Socket SMTP ({$cfg['host']}:{$cfg['port']})", ['to' => $to]);
                self::$debugLogs[] = "Direct Socket SMTP ({$cfg['host']}:{$cfg['port']}): SUCCESS";
                return true;
            } catch (\Throwable $e) {
                self::$debugLogs[] = "Direct SMTP ({$cfg['host']}): " . $e->getMessage();
            }
        }

        return false;
    }

    public static function sendEmail(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        self::$lastError = null;
        self::$debugLogs = [];

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = "Invalid recipient email: '{$to}'";
            self::logWarning(self::$lastError, ['to' => $to]);
            return false;
        }

        // 1. Direct Socket SMTP (Cepat & Zero-Dependency)
        if (self::sendSmtpDirect($to, $subject, $body, $isHtml)) {
            return true;
        }

        // 2. PHPMailer jika tersedia
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = self::getEnvValue('MAIL_HOST', 'smtp.gmail.com');
                $mail->SMTPAuth   = true;
                $mail->Username   = self::getEnvValue('MAIL_USERNAME', '24n40004@student.unika.ac.id');
                $mail->Password   = str_replace(' ', '', self::getEnvValue('MAIL_PASSWORD', 'vkte vmnm fpyx ktro'));
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';
                $mail->Timeout    = 10;
                $mail->setFrom(self::getEnvValue('MAIL_FROM_ADDRESS', $mail->Username), self::getEnvValue('MAIL_FROM_NAME', 'notifikiasifhk'));
                $mail->addAddress($to);
                $mail->isHTML($isHtml);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                $mail->send();
                self::$debugLogs[] = "PHPMailer: SUCCESS";
                return true;
            } catch (\Throwable $ePhpMailer) {
                self::$debugLogs[] = "PHPMailer Failed: " . $ePhpMailer->getMessage();
            }
        }

        // 3. Laravel Mail Facade
        try {
            if (class_exists(\Illuminate\Support\Facades\Mail::class)) {
                $fromAddress = self::getEnvValue('MAIL_FROM_ADDRESS', self::getEnvValue('MAIL_USERNAME', '24n40004@student.unika.ac.id'));
                $fromName    = self::getEnvValue('MAIL_FROM_NAME', 'notifikiasifhk');
                \Illuminate\Support\Facades\Mail::html($body, function ($msg) use ($to, $subject, $fromAddress, $fromName) {
                    $msg->to($to)->subject($subject)->from($fromAddress, $fromName);
                });
                self::$debugLogs[] = "Laravel Mail Facade: SUCCESS";
                return true;
            }
        } catch (\Throwable $eMail) {
            self::$debugLogs[] = "Laravel Mail Facade Failed: " . $eMail->getMessage();
        }

        // 4. Native mail()
        if (function_exists('mail')) {
            try {
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: notifikiasifhk <24n40004@student.unika.ac.id>\r\n";
                $headers .= "Reply-To: 24n40004@student.unika.ac.id\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
                $sent = @\mail($to, $subject, $body, $headers);
                if ($sent) {
                    self::$debugLogs[] = "Native mail(): SUCCESS";
                    return true;
                }
            } catch (\Throwable $eNative) {
                self::$debugLogs[] = "Native mail() Failed: " . $eNative->getMessage();
            }
        }

        self::$lastError = implode(" | ", self::$debugLogs);
        return false;
    }

    public static function sendOTPEmail(string $to, string $otpCode, string $recipientName = 'Pengguna'): bool
    {
        $subject = 'Kode Verifikasi OTP - Fresh Hydration Kiosk';
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 540px; margin: auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
            <h2 style='color: #0284c7; text-align: center; margin-top: 0;'>💧 Fresh Hydration Kiosk</h2>
            <p>Halo <b>" . htmlspecialchars($recipientName) . "</b>,</p>
            <p>Berikut adalah kode OTP verifikasi keamanan akun Anda:</p>
            <div style='text-align: center; margin: 24px 0;'>
                <span style='display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 6px; padding: 12px 28px; background: #f0f9ff; color: #0369a1; border: 2px dashed #0284c7; border-radius: 8px;'>
                    " . htmlspecialchars($otpCode) . "
                </span>
            </div>
            <p style='color: #64748b; font-size: 13px;'>Kode ini berlaku selama 5 menit. Jangan berikan kode OTP ini kepada siapa pun demi keamanan akun Anda.</p>
            <hr style='border: none; border-top: 1px solid #f1f5f9; margin: 20px 0;'>
            <p style='color: #94a3b8; font-size: 12px; text-align: center; margin: 0;'>&copy; " . date('Y') . " Fresh Hydration Kiosk. All rights reserved.</p>
        </div>";

        return self::sendEmail($to, $subject, $body, true);
    }

    public static function sendPaymentEmail(mixed $transaksi): bool
    {
        $userId  = is_array($transaksi) ? ($transaksi['user_id'] ?? null) : ($transaksi->user_id ?? null);
        $userObj = null;

        if (!empty($userId)) {
            try {
                if (class_exists(\Illuminate\Database\Eloquent\Model::class) && \Illuminate\Database\Eloquent\Model::getConnectionResolver() !== null && class_exists(\App\Models\User::class)) {
                    $userObj = \App\Models\User::find($userId);
                }
            } catch (\Throwable $eUser) {}

            if (!$userObj) {
                try {
                    $dbFile = function_exists('base_path') ? base_path('database/database.sqlite') : (__DIR__ . '/../../database/database.sqlite');
                    if (file_exists($dbFile)) {
                        $pdo = new \PDO("sqlite:" . $dbFile);
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                        $stmt->execute([$userId]);
                        $uRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($uRow) {
                            $userObj = (object) $uRow;
                        }
                    }
                } catch (\Throwable $ePdo) {}
            }
        }
        if (!$userObj) {
            try {
                if (function_exists('auth') && class_exists(\Illuminate\Support\Facades\Auth::class) && \Illuminate\Support\Facades\Auth::check()) {
                    $userObj = \Illuminate\Support\Facades\Auth::user();
                }
            } catch (\Throwable $eAuth) {}
        }

        $userEmail   = is_array($transaksi) ? ($transaksi['userEmail'] ?? null) : ($transaksi->userEmail ?? null);
        if (empty($userEmail) || str_ends_with((string)$userEmail, '@fhk.id') || $userEmail === 'customer@fhk.id') {
            $userEmail = $userObj?->email ?? $userEmail;
        }

        $userName    = is_array($transaksi) ? ($transaksi['userName'] ?? null) : ($transaksi->userName ?? null);
        if (empty($userName) || str_starts_with((string)$userName, 'Pengunjung Tamu') || $userName === 'Pengunjung Kios') {
            $userName = $userObj?->name ?? ($userName ?: 'Pelanggan');
        }

        $invoiceId   = is_array($transaksi) ? ($transaksi['invoiceId'] ?? '-') : ($transaksi->invoiceId ?? '-');
        $referenceId = is_array($transaksi) ? ($transaksi['referenceId'] ?? '-') : ($transaksi->referenceId ?? '-');
        $waterType   = is_array($transaksi) ? ($transaksi['water_type'] ?? 'Air') : ($transaksi->water_type ?? 'Air');
        $volumeMl    = is_array($transaksi) ? ($transaksi['volume_ml'] ?? 0) : ($transaksi->volume_ml ?? 0);
        $payAmount   = is_array($transaksi) ? ($transaksi['payAmount'] ?? 0) : ($transaksi->payAmount ?? 0);
        $kioskId     = is_array($transaksi) ? ($transaksi['kiosk_id'] ?? null) : ($transaksi->kiosk_id ?? null);
        $kioskName   = is_array($transaksi) ? ($transaksi['kiosk_name'] ?? null) : ($transaksi->kiosk->name ?? null);

        $recipients = [];
        if (!empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL) && !str_ends_with($userEmail, '@fhk.id')) {
            $recipients[] = $userEmail;
        }

        $adminEmail = self::getEnvValue('MAIL_ADMIN_NOTIFICATION', self::getEnvValue('MAIL_USERNAME', '24n40004@student.unika.ac.id'));
        if (!empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $adminEmail;
        }

        $recipients = array_unique($recipients);
        if (empty($recipients)) {
            self::logInfo('Email notification skipped: No recipient email found', ['invoiceId' => $invoiceId]);
            return false;
        }

        $waterLabel = match (strtoupper((string) $waterType)) {
            'COLD', 'DINGIN' => 'Air Dingin ❄️',
            'HOT', 'PANAS'   => 'Air Panas ☕',
            default          => 'Air Normal 💧',
        };

        $kioskLabel = $kioskName ? "{$kioskName} ({$kioskId})" : ($kioskId ? "Kios {$kioskId}" : 'Fresh Hydration Kiosk');
        $amountFormatted = 'Rp ' . number_format((float) $payAmount, 0, ',', '.');
        $timeFormatted = date('d/m/Y H:i') . ' WIB';

        $subject = "Bukti Pembayaran Sukses - Invoice {$invoiceId}";

        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 580px; margin: auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #0284c7; margin: 0;'>💧 Fresh Hydration Kiosk</h2>
                <p style='color: #64748b; font-size: 14px; margin-top: 4px;'>Bukti Pembayaran Resmi</p>
            </div>
            
            <div style='background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px; margin-bottom: 20px; text-align: center;'>
                <span style='color: #065f46; font-weight: bold; font-size: 16px;'>✅ PEMBAYARAN BERHASIL (LUNAS)</span>
            </div>

            <p>Halo <b>" . htmlspecialchars($userName) . "</b>,</p>
            <p>Terima kasih, pembayaran pesanan air minum Anda telah kami terima dengan rincian sebagai berikut:</p>

            <table style='width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 14px;'>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>No. Invoice</td>
                    <td style='padding: 8px 0; font-weight: bold; text-align: right; font-family: monospace;'>" . htmlspecialchars($invoiceId) . "</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>Reference ID</td>
                    <td style='padding: 8px 0; font-weight: bold; text-align: right; font-family: monospace;'>" . htmlspecialchars($referenceId) . "</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>Menu & Volume</td>
                    <td style='padding: 8px 0; text-align: right;'>" . htmlspecialchars($waterLabel) . " (" . htmlspecialchars((string) $volumeMl) . " ml)</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>Lokasi Kios</td>
                    <td style='padding: 8px 0; text-align: right;'>" . htmlspecialchars($kioskLabel) . "</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>Waktu Pembayaran</td>
                    <td style='padding: 8px 0; text-align: right;'>" . htmlspecialchars($timeFormatted) . "</td>
                </tr>
                <tr style='background: #f8fafc;'>
                    <td style='padding: 12px 8px; font-weight: bold; color: #1e293b; font-size: 15px;'>Total Pembayaran</td>
                    <td style='padding: 12px 8px; font-weight: bold; text-align: right; color: #0284c7; font-size: 16px;'>" . htmlspecialchars($amountFormatted) . "</td>
                </tr>
            </table>

            <p style='font-size: 13px; color: #475569;'>Silakan lakukan proses penuangan air pada dispenser kios yang bersangkutan.</p>
            
            <hr style='border: none; border-top: 1px solid #f1f5f9; margin: 24px 0;'>
            <p style='color: #94a3b8; font-size: 12px; text-align: center; margin: 0;'>
                Email ini dikirim secara otomatis oleh sistem Fresh Hydration Kiosk.<br>
                &copy; " . date('Y') . " Fresh Hydration Kiosk.
            </p>
        </div>";

        $allSuccess = true;
        foreach ($recipients as $recipient) {
            $sent = self::sendEmail($recipient, $subject, $body, true);
            if (!$sent) $allSuccess = false;
        }

        return $allSuccess;
    }
}