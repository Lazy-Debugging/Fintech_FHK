<?php
/**
 * Master One-Click Sync & Diagnostic Notification Dispatcher for FHK Live Server
 * Akses: https://app.mesinbayar.com/fhk/update_kiosk.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$baseDir = __DIR__;

// 1. Muat vendor autoloader jika ada
if (file_exists($baseDir . '/vendor/autoload.php')) {
    @require_once $baseDir . '/vendor/autoload.php';
}

// 2. Baca file .env jika ada
if (file_exists($baseDir . '/.env')) {
    $lines = file($baseDir . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!isset($_ENV[$name])) $_ENV[$name] = $value;
            if (!isset($_SERVER[$name])) $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    }
}

// 3. Fallback functions env() & config()
if (!function_exists('env')) {
    function env($key, $default = null) {
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (isset($_SERVER[$key])) return $_SERVER[$key];
        $val = getenv($key);
        return ($val !== false && $val !== '') ? $val : $default;
    }
}

if (!function_exists('config')) {
    function config($key = null, $default = null) {
        return $default;
    }
}

@mkdir($baseDir . '/app/Services', 0777, true);
@mkdir($baseDir . '/app/Console', 0777, true);
@mkdir($baseDir . '/storage/framework/views', 0777, true);

// 4. Overwrite app/Services/EmailNotificationService.php dengan kode Pure-PHP Direct Socket SMTP
$fullEmailServiceCode = <<<'PHP'
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
PHP;

file_put_contents($baseDir . '/app/Services/EmailNotificationService.php', $fullEmailServiceCode);

// 4b. Overwrite app/Http/Controllers/ProfileController.php dengan method updatePhone terbaru
$fullProfileControllerCode = <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\Voucher;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user() ?? (function_exists('auth') ? auth()->user() : null);
        $guestToken = $request->session()->get('guest_order_id');

        if ($user) {
            Transaksi::whereNull('user_id')
                ->where(function ($q) use ($user, $guestToken) {
                    $q->where('userEmail', $user->email);
                    if ($guestToken) {
                        $q->orWhere('guest_token', $guestToken);
                    }
                    $q->orWhere('userName', 'like', '%' . $user->name . '%');
                })
                ->update(['user_id' => $user->id]);
        }

        $pendingTxs = Transaksi::whereIn('status', ['PENDING', 'NEW', 'UNPAID'])
            ->where(function ($query) use ($user, $guestToken) {
                if ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('userEmail', $user->email);
                }
                if ($guestToken) {
                    $query->orWhere('guest_token', $guestToken);
                }
            })
            ->limit(10)
            ->get();

        if ($pendingTxs->isNotEmpty()) {
            $aiyoService = app(\App\Services\AiyoPaymentService::class);
            foreach ($pendingTxs as $tx) {
                if ($user && !$tx->user_id) {
                    $tx->update(['user_id' => $user->id]);
                }

                $token = $tx->aiyo_access_token ?? '';
                if (!str_starts_with($token, 'mock_')) {
                    $res = $aiyoService->checkInvoiceStatus($tx->invoiceId, $token);
                    if ($res['success'] && $res['isPaid']) {
                        $tx->update(['status' => 'PAID']);
                    } elseif ($res['success'] && in_array(strtoupper($res['status'] ?? ''), ['EXPIRED', 'CANCELLED', 'FAILED'])) {
                        $tx->update(['status' => strtoupper($res['status'])]);
                    }
                }
            }
        }

        $statusFilter = $request->query('status', 'all');
        $sortFilter   = $request->query('sort', 'latest');
        $searchFilter = trim($request->query('search', ''));

        $transactionsQuery = Transaksi::with('kiosk')
            ->where(function ($query) use ($user, $guestToken) {
                if ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('userEmail', $user->email);
                }
                if ($guestToken) {
                    $query->orWhere('guest_token', $guestToken);
                }
                if (!$user && !$guestToken) {
                    $query->whereRaw('1 = 0');
                }
            });

        if ($statusFilter !== 'all') {
            if (in_array($statusFilter, ['PENDING', 'NEW', 'UNPAID'])) {
                $transactionsQuery->whereIn('status', ['PENDING', 'NEW', 'UNPAID']);
            } elseif ($statusFilter === 'PAID') {
                $transactionsQuery->whereIn('status', ['PAID', 'AWAITING_KIOSK_SCAN']);
            } elseif ($statusFilter === 'COMPLETED') {
                $transactionsQuery->whereIn('status', ['COMPLETED', 'DISPENSING']);
            } elseif ($statusFilter === 'CANCELLED') {
                $transactionsQuery->whereIn('status', ['CANCELLED', 'EXPIRED', 'FAILED']);
            } else {
                $transactionsQuery->where('status', $statusFilter);
            }
        }

        if (!empty($searchFilter)) {
            $transactionsQuery->where(function ($q) use ($searchFilter) {
                $q->where('invoiceId', 'like', "%{$searchFilter}%")
                  ->orWhere('referenceId', 'like', "%{$searchFilter}%")
                  ->orWhere('water_type', 'like', "%{$searchFilter}%");
            });
        }

        switch ($sortFilter) {
            case 'oldest':
                $transactionsQuery->orderBy('created_at', 'asc')->orderBy('timestamp', 'asc');
                break;
            case 'amount_high':
                $transactionsQuery->orderBy('payAmount', 'desc');
                break;
            case 'amount_low':
                $transactionsQuery->orderBy('payAmount', 'asc');
                break;
            case 'latest':
            default:
                $transactionsQuery->orderBy('created_at', 'desc')->orderBy('timestamp', 'desc');
                break;
        }

        $transactions = $transactionsQuery->paginate(10)->withQueryString();

        $vouchers = $user
            ? Voucher::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit'))
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('profile.index', compact('transactions', 'vouchers', 'user'));
    }

    public function updatePhone(Request $request)
    {
        $user = $request->user() ?? (function_exists('auth') ? auth()->user() : null);
        if (!$user) {
            return redirect()->route('login')->with('error', 'Silakan masuk terlebih dahulu untuk memperbarui nomor telepon.');
        }

        $validated = $request->validate([
            'phone' => 'nullable|string|max:30',
        ]);

        $rawPhone = trim($validated['phone'] ?? '');
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);

        if (!empty($cleanPhone)) {
            if (str_starts_with($cleanPhone, '628')) {
                $cleanPhone = '08' . substr($cleanPhone, 3);
            }
        }

        // 1. Pastikan kolom 'phone' tersedia di tabel users
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('users') && !\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone')) {
                \Illuminate\Support\Facades\Schema::table('users', function ($table) {
                    $table->string('phone', 30)->nullable()->after('email');
                });
            }
        } catch (\Throwable $eCol) {
            \Illuminate\Support\Facades\Log::warning('Add phone column error: ' . $eCol->getMessage());
        }

        // 2. Simpan langsung ke database via DB::table (bypass Eloquent $fillable)
        try {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $user->id)
                ->update(['phone' => $cleanPhone]);
        } catch (\Throwable $eDb) {
            \Illuminate\Support\Facades\Log::warning('DB update user phone error: ' . $eDb->getMessage());
        }

        // 3. Simpan ke instance model aktif
        try {
            $user->phone = $cleanPhone;
            $user->save();
        } catch (\Throwable $eSave) {}

        if (!empty($cleanPhone)) {
            try {
                Transaksi::where('user_id', $user->id)
                    ->whereIn('status', ['NEW', 'PENDING', 'UNPAID'])
                    ->update(['userPhone' => $cleanPhone]);
            } catch (\Throwable $eTx) {}
        }

        return redirect()->route('profile')->with('success', 'Nomor WhatsApp berhasil diperbarui! Notifikasi pembayaran otomatis akan dikirim ke nomor ini.');
    }
}
PHP;

file_put_contents($baseDir . '/app/Http/Controllers/ProfileController.php', $fullProfileControllerCode);

// 4c. Overwrite app/Models/User.php dengan $fillable phone
$fullUserModelCode = <<<'PHP'
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'google_id',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
PHP;

file_put_contents($baseDir . '/app/Models/User.php', $fullUserModelCode);

// 4d. Overwrite app/Services/FonnteService.php dengan kode pure-cURL dan fallback resilient
$fullFonnteServiceCode = <<<'PHP'
<?php

namespace App\Services;

use App\Models\Transaksi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    public static ?string $lastError = null;
    public static ?array $lastResponse = null;

    public static function getEnvValue(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            try {
                $cVal = config('services.fonnte.' . strtolower(str_replace('FONNTE_', '', $key)));
                if ($cVal !== null) return $cVal;
            } catch (\Throwable $e) {}
        }
        if (function_exists('env')) {
            try {
                $val = env($key);
                if ($val !== null && $val !== '') return $val;
            } catch (\Throwable $e) {}
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        $val = getenv($key);
        return ($val !== false && $val !== '') ? $val : $default;
    }

    private static function executeFonntePost(string $token, array $postData): array
    {
        self::$lastError = null;
        self::$lastResponse = null;

        if (function_exists('curl_init')) {
            try {
                $ch = curl_init('https://api.fonnte.com/send');
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query($postData),
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: ' . $token,
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_CONNECTTIMEOUT => 10,
                ]);

                $raw = curl_exec($ch);
                $err = curl_error($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($raw !== false && !empty($raw)) {
                    $json = json_decode($raw, true);
                    self::$lastResponse = $json ?: ['raw' => $raw];

                    $isSuccess = ($code >= 200 && $code < 300) && ($json['status'] ?? false);
                    if (!$isSuccess) {
                        self::$lastError = $json['reason'] ?? $json['detail'] ?? ("HTTP " . $code . ": " . $raw);
                    }

                    return [
                        'status'   => $isSuccess,
                        'response' => $json ?: $raw,
                        'http_code'=> $code,
                    ];
                }

                if (!empty($err)) {
                    self::$lastError = 'cURL Error: ' . $err;
                }
            } catch (\Throwable $eCurl) {
                self::$lastError = 'cURL Exception: ' . $eCurl->getMessage();
            }
        }

        try {
            if (class_exists(\Illuminate\Support\Facades\Http::class)) {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => $token,
                ])->asForm()->post('https://api.fonnte.com/send', $postData);

                $json = $response->json();
                self::$lastResponse = $json;

                $isSuccess = $response->successful() && ($json['status'] ?? false);
                if (!$isSuccess) {
                    self::$lastError = $json['reason'] ?? $json['detail'] ?? ('HTTP ' . $response->status());
                }

                return [
                    'status'   => $isSuccess,
                    'response' => $json,
                ];
            }
        } catch (\Throwable $eHttp) {
            self::$lastError = 'Http Facade Error: ' . $eHttp->getMessage();
        }

        return [
            'status' => false,
            'error'  => self::$lastError ?: 'Gagal menghubungi Fonnte API',
        ];
    }

    public static function sendPaymentNotification(mixed $transaksi): array
    {
        $token = self::getEnvValue('FONNTE_TOKEN', 'Y1vmkxaWWRXVsatHp3aG');
        if (empty($token)) {
            return ['status' => false, 'reason' => 'FONNTE_TOKEN is empty'];
        }

        $userId      = is_array($transaksi) ? ($transaksi['user_id'] ?? null) : ($transaksi->user_id ?? null);
        $userEmail   = is_array($transaksi) ? ($transaksi['userEmail'] ?? null) : ($transaksi->userEmail ?? null);
        $userName    = is_array($transaksi) ? ($transaksi['userName'] ?? null) : ($transaksi->userName ?? null);
        $userPhone   = is_array($transaksi) ? ($transaksi['userPhone'] ?? '') : ($transaksi->userPhone ?? '');
        $invoiceId   = is_array($transaksi) ? ($transaksi['invoiceId'] ?? '-') : ($transaksi->invoiceId ?? '-');
        $referenceId = is_array($transaksi) ? ($transaksi['referenceId'] ?? '-') : ($transaksi->referenceId ?? '-');
        $waterType   = is_array($transaksi) ? ($transaksi['water_type'] ?? 'Air') : ($transaksi->water_type ?? 'Air');
        $volumeMl    = is_array($transaksi) ? ($transaksi['volume_ml'] ?? 0) : ($transaksi->volume_ml ?? 0);
        $payAmount   = is_array($transaksi) ? ($transaksi['payAmount'] ?? 0) : ($transaksi->payAmount ?? 0);
        $kioskId     = is_array($transaksi) ? ($transaksi['kiosk_id'] ?? null) : ($transaksi->kiosk_id ?? null);
        $kioskName   = is_array($transaksi) ? ($transaksi['kiosk_name'] ?? null) : ($transaksi->kiosk->name ?? null);

        $userObj = null;
        if (!empty($userId)) {
            try { 
                if (class_exists(\App\Models\User::class)) {
                    $userObj = \App\Models\User::find($userId); 
                }
            } catch (\Throwable $e) {}
        }
        if (!$userObj && !empty($userEmail)) {
            try { 
                if (class_exists(\App\Models\User::class)) {
                    $userObj = \App\Models\User::where('email', $userEmail)->first(); 
                }
            } catch (\Throwable $e) {}
        }
        if (!$userObj) {
            try {
                if (function_exists('app') && app()->bound('auth') && function_exists('auth') && auth()->check()) {
                    $userObj = auth()->user();
                }
            } catch (\Throwable $e) {}
        }

        if (empty($userName) || str_starts_with((string)$userName, 'Pengunjung Tamu') || $userName === 'Pengunjung Kios') {
            $userName = $userObj?->name ?? ($userName ?: 'Pelanggan');
        }

        if (empty($userPhone) || in_array($userPhone, ['0812000000', '08123456789', '081234567890', '-'])) {
            $userPhone = $userObj?->phone ?? $userPhone;
        }

        $waterLabel = match (strtoupper((string) $waterType)) {
            'COLD', 'DINGIN' => 'Air Dingin ❄️',
            'HOT', 'PANAS'   => 'Air Panas ☕',
            default          => 'Air Normal 💧',
        };

        $kioskLabel = $kioskName ? "{$kioskName} ({$kioskId})" : ($kioskId ? "Kios {$kioskId}" : 'Fresh Hydration Kiosk');
        $amountFormatted = 'Rp ' . number_format((float) $payAmount, 0, ',', '.');
        $timeFormatted = date('d/m/Y H:i') . ' WIB';

        $message = "💧 *PEMBAYARAN BERHASIL - FRESH HYDRATION KIOSK* 💧\n\n"
            . "Halo *{$userName}*,\n"
            . "Pembayaran pesanan air minum Anda telah kami terima dan diverifikasi.\n\n"
            . "📋 *Detail Transaksi:*\n"
            . "• *No. Invoice* : `{$invoiceId}`\n"
            . "• *Ref ID*      : `{$referenceId}`\n"
            . "• *Menu Air*    : {$waterLabel}\n"
            . "• *Volume*      : {$volumeMl} ml\n"
            . "• *Total Bayar* : *{$amountFormatted}*\n"
            . "• *Lokasi Kios* : {$kioskLabel}\n"
            . "• *Waktu*       : {$timeFormatted}\n"
            . "• *Status*      : *LUNAS (PAID)* ✅\n\n"
            . "Silakan ambil air Anda pada dispenser kios.\n"
            . "Terima kasih telah menggunakan Fresh Hydration Kiosk! 🌿";

        $targets = [];
        $cleanTxPhone = preg_replace('/[^0-9]/', '', (string) $userPhone);
        if (!empty($cleanTxPhone) && !in_array($cleanTxPhone, ['0812000000', '08123456789', '081234567890', '081200000000', '0'])) {
            $targets[] = $cleanTxPhone;
        }

        if ($userObj && !empty($userObj->phone)) {
            $cleanUserPhone = preg_replace('/[^0-9]/', '', (string) $userObj->phone);
            if (!empty($cleanUserPhone) && !in_array($cleanUserPhone, ['0812000000', '08123456789', '081234567890', '081200000000', '0'])) {
                $targets[] = $cleanUserPhone;
            }
        }

        $adminPhone = self::getEnvValue('FONNTE_TARGET', '');
        if (!empty($adminPhone)) {
            $adminClean = preg_replace('/[^0-9]/', '', (string) $adminPhone);
            if (!empty($adminClean)) {
                $targets[] = $adminClean;
            }
        }

        $targets = array_unique(array_filter($targets));

        if (empty($targets)) {
            return ['status' => false, 'reason' => 'No target phone number'];
        }

        $targetStr = implode(',', $targets);

        $res = self::executeFonntePost($token, [
            'target'      => $targetStr,
            'message'     => $message,
            'countryCode' => '62',
        ]);

        return $res;
    }

    public static function sendMessage(string $target, string $message): array
    {
        $token = self::getEnvValue('FONNTE_TOKEN', 'Y1vmkxaWWRXVsatHp3aG');
        if (empty($token)) {
            return ['status' => false, 'reason' => 'FONNTE_TOKEN is empty'];
        }

        $cleanTarget = preg_replace('/[^0-9]/', '', $target);
        if (empty($cleanTarget)) {
            return ['status' => false, 'reason' => 'Target number is empty'];
        }

        return self::executeFonntePost($token, [
            'target'      => $cleanTarget,
            'message'     => $message,
            'countryCode' => '62',
        ]);
    }
}
PHP;

file_put_contents($baseDir . '/app/Services/FonnteService.php', $fullFonnteServiceCode);

// 4e. Overwrite app/Models/Transaksi.php dengan hook notifikasi aman
$fullTransaksiModelCode = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaksi extends Model
{
    protected $table = 'transaksi';
    protected $primaryKey = 'invoiceId';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'invoiceId',
        'referenceId',
        'kiosk_id',
        'user_id',
        'voucher_id',
        'guest_token',
        'userName',
        'userEmail',
        'userPhone',
        'water_type',
        'volume_ml',
        'payAmount',
        'original_amount',
        'discount_amount',
        'aiyo_access_token',
        'status',
        'redemption_token_hash',
        'redemption_token_encrypted',
        'redemption_expires_at',
        'redeemed_at',
        'remarks',
        'items',
        'qr_content',
        'invoice_url',
        'dispense_started_at',
        'dispense_completed_at',
        'timestamp',
    ];

    protected $casts = [
        'payAmount' => 'integer',
        'original_amount' => 'integer',
        'discount_amount' => 'integer',
        'volume_ml' => 'integer',
        'items' => 'array',
        'dispense_started_at' => 'datetime',
        'dispense_completed_at' => 'datetime',
        'timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'redemption_expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (Transaksi $transaksi) {
            if ($transaksi->wasChanged('status')) {
                $newStatus = strtoupper((string) $transaksi->status);
                $oldStatus = strtoupper((string) $transaksi->getOriginal('status'));

                if (in_array($newStatus, ['PAID', 'AWAITING_KIOSK_SCAN'], true) 
                    && !in_array($oldStatus, ['PAID', 'AWAITING_KIOSK_SCAN', 'DISPENSING', 'COMPLETED'], true)) {
                    try {
                        if (class_exists(\App\Services\FonnteService::class)) {
                            \App\Services\FonnteService::sendPaymentNotification($transaksi);
                        }
                    } catch (\Throwable $e) {}
                    try {
                        if (class_exists(\App\Services\EmailNotificationService::class)) {
                            \App\Services\EmailNotificationService::sendPaymentEmail($transaksi);
                        }
                    } catch (\Throwable $e) {}
                }

                if (in_array($newStatus, ['EXPIRED', 'CANCELLED'], true) 
                    && in_array($oldStatus, ['PENDING', 'NEW', 'UNPAID'], true)) {
                    try {
                        if (class_exists(\App\Services\EmailNotificationService::class)) {
                            \App\Services\EmailNotificationService::sendUnpaidExpiredEmail($transaksi);
                        }
                    } catch (\Throwable $e) {}
                }
            }
        });

        static::created(function (Transaksi $transaksi) {
            $status = strtoupper((string) $transaksi->status);
            if (in_array($status, ['PAID', 'AWAITING_KIOSK_SCAN'], true)) {
                try {
                    if (class_exists(\App\Services\FonnteService::class)) {
                        \App\Services\FonnteService::sendPaymentNotification($transaksi);
                    }
                } catch (\Throwable $e) {}
                try {
                    if (class_exists(\App\Services\EmailNotificationService::class)) {
                        \App\Services\EmailNotificationService::sendPaymentEmail($transaksi);
                    }
                } catch (\Throwable $e) {}
            }
        });
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class, 'kiosk_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
PHP;

file_put_contents($baseDir . '/app/Models/Transaksi.php', $fullTransaksiModelCode);

// 4f. Tambah kolom phone ke database.sqlite & sinkronkan nomor HP ke tabel transaksi
$sqliteDb = $baseDir . '/database/database.sqlite';
if (file_exists($sqliteDb)) {
    try {
        $p = new PDO("sqlite:" . $sqliteDb);
        $hasCol = false;
        $tInfo = $p->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tInfo as $c) {
            if ($c['name'] === 'phone') {
                $hasCol = true;
                break;
            }
        }
        if (!$hasCol) {
            $p->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL");
        }

        // Simpan nomor HP default untuk akun Lauren Xue jika belum ada
        $p->exec("UPDATE users SET phone = '085800661438' WHERE (phone IS NULL OR phone = '') AND (email = '24n40009@student.unika.ac.id' OR name LIKE '%Lauren%')");

        // Sinkronkan nomor WhatsApp ke riwayat transaksi yang nomornya masih kosong
        $p->exec("UPDATE transaksi SET userPhone = '085800661438' WHERE (userPhone IS NULL OR userPhone = '' OR userPhone LIKE '08120000%') AND (userEmail = '24n40009@student.unika.ac.id' OR userName LIKE '%Lauren%' OR user_id IN (SELECT id FROM users WHERE email = '24n40009@student.unika.ac.id'))");
    } catch (\Throwable $eSqlite) {}
}

// 4g. Perbaiki file controllers, routing, dan views
$webRoutesFile = $baseDir . '/routes/web.php';
if (file_exists($webRoutesFile)) {
    $webRoutesContent = file_get_contents($webRoutesFile);
    $webRoutesContent = str_replace(
        "Route::redirect('/', '/admin/dashboard')->name('home');",
        "Route::redirect('/', '/admin/dashboard')->name('index');",
        $webRoutesContent
    );
    $webRoutesContent = str_replace(
        "Route::prefix('fhk/admin')->middleware(['auth', 'admin'])->group(\$adminRoutes);",
        "Route::prefix('fhk/admin')->as('admin.fhk.')->middleware(['auth', 'admin'])->group(\$adminRoutes);",
        $webRoutesContent
    );
    $webRoutesContent = str_replace(
        "Route::prefix('app/fhk/admin')->middleware(['auth', 'admin'])->group(\$adminRoutes);",
        "Route::prefix('app/fhk/admin')->as('admin.app_fhk.')->middleware(['auth', 'admin'])->group(\$adminRoutes);",
        $webRoutesContent
    );
    file_put_contents($webRoutesFile, $webRoutesContent);
}

$kioskLayoutFile = $baseDir . '/resources/views/layouts/kiosk_layout.blade.php';
if (file_exists($kioskLayoutFile)) {
    $kioskLayoutContent = file_get_contents($kioskLayoutFile);
    $kioskLayoutContent = str_replace(
        "<a href=\"{{ route('home') }}\"",
        "<a href=\"{{ route('kiosk.home') }}\"",
        $kioskLayoutContent
    );
    file_put_contents($kioskLayoutFile, $kioskLayoutContent);
}

$kioskIndexFile = $baseDir . '/resources/views/kiosk/index.blade.php';
if (file_exists($kioskIndexFile)) {
    $kioskIndexContent = file_get_contents($kioskIndexFile);
    $kioskIndexContent = str_replace(
        "user_phone:  userPhone || undefined,",
        "user_phone:  (userPhone || \"{{ auth()->user()->phone ?? '' }}\") || undefined,",
        $kioskIndexContent
    );
    file_put_contents($kioskIndexFile, $kioskIndexContent);
}

$loginBladeFile = $baseDir . '/resources/views/auth/login.blade.php';
if (file_exists($loginBladeFile)) {
    $loginBladeContent = file_get_contents($loginBladeFile);
    $loginBladeContent = str_replace(
        "<a href=\"{{ route('home') }}\"",
        "<a href=\"{{ route('kiosk.home') }}\"",
        $loginBladeContent
    );
    file_put_contents($loginBladeFile, $loginBladeContent);
}

$authCtrlFile = $baseDir . '/app/Http/Controllers/AuthController.php';
if (file_exists($authCtrlFile)) {
    $authCtrlContent = file_get_contents($authCtrlFile);
    $authCtrlContent = str_replace(
        "return redirect()->route('home');",
        "return redirect()->route('kiosk.home');",
        $authCtrlContent
    );
    file_put_contents($authCtrlFile, $authCtrlContent);
}

$orderCtrlFile = $baseDir . '/app/Http/Controllers/Kiosk/OrderController.php';
if (file_exists($orderCtrlFile)) {
    $orderCtrlContent = file_get_contents($orderCtrlFile);
    $orderCtrlContent = str_replace(
        "\$transaksi->update(['status' => 'PAID']); \$this->preparePickup(\$transaksi);",
        "\$transaksi->update(['status' => 'PAID']); \$this->preparePickup(\$transaksi); try { \\App\\Services\\FonnteService::sendPaymentNotification(\$transaksi); \\App\\Services\\EmailNotificationService::sendPaymentEmail(\$transaksi); } catch (\\Throwable \$e) {}",
        $orderCtrlContent
    );
    $orderCtrlContent = str_replace(
        "if (\$transaksi->redemption_token_hash) return;",
        "if (\$transaksi->redemption_token_hash) { try { \\App\\Services\\FonnteService::sendPaymentNotification(\$transaksi); } catch (\\Throwable \$e) {} return; }",
        $orderCtrlContent
    );
    file_put_contents($orderCtrlFile, $orderCtrlContent);
}

$aiyoCbFile = $baseDir . '/app/Http/Controllers/Payment/AiyoCallbackController.php';
if (file_exists($aiyoCbFile)) {
    $aiyoCbContent = file_get_contents($aiyoCbFile);
    if (!str_contains($aiyoCbContent, 'FonnteService::sendPaymentNotification')) {
        $aiyoCbContent = str_replace(
            "Log::info('AiYO Callback SUCCESS: Pembayaran Berhasil Diproses', [",
            "try { \\App\\Services\\FonnteService::sendPaymentNotification(\$transaksi); \\App\\Services\\EmailNotificationService::sendPaymentEmail(\$transaksi); } catch (\\Throwable \$eNotify) {}\n            Log::info('AiYO Callback SUCCESS: Pembayaran Berhasil Diproses', [",
            $aiyoCbContent
        );
        file_put_contents($aiyoCbFile, $aiyoCbContent);
    }
}

// 5. Bersihkan view cache Blade & bootstrap route cache
$views = glob($baseDir . '/storage/framework/views/*.php');
$del = 0;
if ($views) {
    foreach ($views as $v) {
        if (@unlink($v)) $del++;
    }
}
$bCaches = glob($baseDir . '/bootstrap/cache/*.php');
if ($bCaches) {
    foreach ($bCaches as $bc) {
        if (basename($bc) !== '.gitignore') @unlink($bc);
    }
}

// 6. Muat service dan jalankan dispatch email & WhatsApp Fonnte
require_once $baseDir . '/app/Services/EmailNotificationService.php';
require_once $baseDir . '/app/Services/FonnteService.php';

$dbPath = $baseDir . '/database/database.sqlite';
$tx = null;
$emailResult = false;
$fonnteResult = null;
$userEmailTarget = null;
$phpmailerAvailable = class_exists(\PHPMailer\PHPMailer\PHPMailer::class);

if (file_exists($dbPath)) {
    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $stmt = $pdo->query("SELECT * FROM transaksi WHERE invoiceId IN ('5ZEmXacJZuYqgzX3vWa4', 'fjznYigRzZllJn8Y02AX', 'KMZKLwyk5QtBXbavXl3J', 'IlNAftim8HAbvcUPcxWM', 'seSdyAVfwS3zLUxe8SKq') OR status IN ('PAID', 'AWAITING_KIOSK_SCAN', 'SUCCESS') ORDER BY updated_at DESC LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($row) {
            $tx = (object) $row;
            if (!empty($tx->user_id)) {
                $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $userStmt->execute([$tx->user_id]);
                $uRow = $userStmt->fetch(PDO::FETCH_ASSOC);
                if ($uRow) {
                    if (!empty($uRow['email'])) $tx->userEmail = $uRow['email'];
                    if (!empty($uRow['phone'])) $tx->userPhone = $uRow['phone'];
                    $tx->userName = $uRow['name'] ?? $tx->userName;
                }
            }
        }
    } catch (\Throwable $eDb) {
        \App\Services\EmailNotificationService::$debugLogs[] = "Database query error: " . $eDb->getMessage();
    }
}

if (!$tx) {
    $tx = (object) [
        'invoiceId'   => '5ZEmXacJZuYqgzX3vWa4',
        'referenceId' => 'FHK-REF-' . time(),
        'water_type'  => 'NORMAL',
        'volume_ml'   => 250,
        'payAmount'   => 1,
        'userEmail'   => '24n40009@student.unika.ac.id',
        'userName'    => 'Lauren xue',
        'userPhone'   => '085800661438',
        'kiosk_id'    => 'FHK-JAKARTA-01',
    ];
}

if (empty($tx->userEmail) || str_ends_with($tx->userEmail, '@fhk.id') || $tx->userEmail === 'customer@fhk.id') {
    $tx->userEmail = '24n40009@student.unika.ac.id';
}
if (empty($tx->userPhone) || in_array($tx->userPhone, ['0812000000', '08123456789', '081234567890', '-'])) {
    $tx->userPhone = '085800661438';
}

$userEmailTarget = $tx->userEmail;
$emailResult = \App\Services\EmailNotificationService::sendPaymentEmail($tx);
$fonnteResult = \App\Services\FonnteService::sendPaymentNotification($tx);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'              => 'SUCCESS',
    'phpmailer_available' => $phpmailerAvailable,
    'openssl_enabled'     => extension_loaded('openssl'),
    'services_updated'    => true,
    'views_cache_deleted' => $del,
    'dispatched_invoice'  => $tx ? $tx->invoiceId : null,
    'recipient_email'     => $userEmailTarget,
    'recipient_phone'     => $tx ? ($tx->userPhone ?? null) : null,
    'email_sent'          => $emailResult,
    'fonnte_result'       => $fonnteResult,
    'fonnte_last_error'   => \App\Services\FonnteService::$lastError,
    'debug_logs'          => \App\Services\EmailNotificationService::$debugLogs,
    'last_error'          => \App\Services\EmailNotificationService::$lastError,
    'message'             => ($fonnteResult['status'] ?? false)
        ? 'Pembaruan berhasil dan notifikasi WhatsApp Fonnte BERHASIL dikirim ke ' . $tx->userPhone . '!' 
        : 'Pembaruan berhasil diterapkan, silakan periksa status pengiriman Fonnte.'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
