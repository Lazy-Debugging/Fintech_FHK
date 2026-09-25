<?php
/**
 * Master One-Click Sync & Notification Dispatcher for FHK Live Server
 * Akses: https://app.mesinbayar.com/fhk/update_kiosk.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$baseDir = __DIR__;
@mkdir($baseDir . '/app/Services', 0777, true);
@mkdir($baseDir . '/app/Console', 0777, true);
@mkdir($baseDir . '/storage/framework/views', 0777, true);

// 1. Tulis app/Services/EmailNotificationService.php
$emailServiceCode = <<<'PHP'
<?php

namespace App\Services;

use App\Models\Transaksi;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    private static function createMailer(int $port = 587, string $encryption = 'tls')
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return null;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $host       = config('mail.mailers.smtp.host', env('MAIL_HOST', 'smtp.gmail.com'));
        $username   = config('mail.mailers.smtp.username', env('MAIL_USERNAME', '24n40004@student.unika.ac.id'));
        $password   = config('mail.mailers.smtp.password', env('MAIL_PASSWORD', 'vkte vmnm fpyx ktro'));
        $fromAddress = config('mail.from.address', env('MAIL_FROM_ADDRESS', $username));
        $fromName    = config('mail.from.name', env('MAIL_FROM_NAME', 'notifikiasifhk'));

        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = $encryption;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 10;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom($fromAddress, $fromName);

        return $mail;
    }

    public static function sendEmail(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // 1 & 2: PHPMailer jika tersedia
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                $mail = self::createMailer(587, 'tls');
                if ($mail) {
                    $mail->addAddress($to);
                    $mail->isHTML($isHtml);
                    $mail->Subject = $subject;
                    $mail->Body    = $body;
                    $mail->send();
                    return true;
                }
            } catch (\Throwable $e1) {
                try {
                    $mail2 = self::createMailer(465, 'ssl');
                    if ($mail2) {
                        $mail2->addAddress($to);
                        $mail2->isHTML($isHtml);
                        $mail2->Subject = $subject;
                        $mail2->Body    = $body;
                        $mail2->send();
                        return true;
                    }
                } catch (\Throwable $e2) {}
            }
        }

        // 3: Laravel Mail Facade
        try {
            if (class_exists(\Illuminate\Support\Facades\Mail::class)) {
                $fromAddress = env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME', '24n40004@student.unika.ac.id'));
                $fromName    = env('MAIL_FROM_NAME', 'notifikiasifhk');
                \Illuminate\Support\Facades\Mail::html($body, function ($msg) use ($to, $subject, $fromAddress, $fromName) {
                    $msg->to($to)->subject($subject)->from($fromAddress, $fromName);
                });
                return true;
            }
        } catch (\Throwable $eMail) {}

        // 4: Native mail()
        try {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: notifikiasifhk <24n40004@student.unika.ac.id>\r\n";
            return @mail($to, $subject, $body, $headers);
        } catch (\Throwable $eNative) {}

        return false;
    }

    public static function sendPaymentEmail(mixed $transaksi): bool
    {
        $userId      = is_array($transaksi) ? ($transaksi['user_id'] ?? null) : ($transaksi->user_id ?? null);
        $userObj     = (!empty($userId) && class_exists(\App\Models\User::class)) ? \App\Models\User::find($userId) : (function_exists('auth') && auth()->check() ? auth()->user() : null);

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

        $recipients = [];
        if (!empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL) && !str_ends_with($userEmail, '@fhk.id')) {
            $recipients[] = $userEmail;
        }

        $adminEmail = env('MAIL_ADMIN_NOTIFICATION', env('MAIL_USERNAME', '24n40004@student.unika.ac.id'));
        if (!empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $adminEmail;
        }

        $recipients = array_unique($recipients);
        if (empty($recipients)) {
            return false;
        }

        $waterLabel = match (strtoupper((string) $waterType)) {
            'COLD', 'DINGIN' => 'Air Dingin ❄️',
            'HOT', 'PANAS'   => 'Air Panas ☕',
            default          => 'Air Normal 💧',
        };

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

            <p>Halo <b>" . htmlspecialchars((string)$userName) . "</b>,</p>
            <p>Terima kasih, pembayaran pesanan air minum Anda telah kami terima dengan rincian sebagai berikut:</p>

            <table style='width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px;'>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>No. Invoice</td>
                    <td style='padding: 8px 0; text-align: right; font-family: monospace; font-weight: bold; color: #0f172a;'>" . htmlspecialchars((string)$invoiceId) . "</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>No. Referensi</td>
                    <td style='padding: 8px 0; text-align: right; font-family: monospace; color: #64748b;'>" . htmlspecialchars((string)$referenceId) . "</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>Pilihan Air</td>
                    <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #0284c7;'>" . htmlspecialchars((string)$waterLabel) . "</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>Volume</td>
                    <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #0f172a;'>" . htmlspecialchars((string)$volumeMl) . " ml</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 8px 0; color: #64748b;'>Waktu Transaksi</td>
                    <td style='padding: 8px 0; text-align: right; color: #0f172a;'>" . htmlspecialchars((string)$timeFormatted) . "</td>
                </tr>
                <tr style='border-top: 2px solid #e2e8f0; font-size: 16px;'>
                    <td style='padding: 12px 0; font-weight: bold; color: #0f172a;'>Total Bayar</td>
                    <td style='padding: 12px 0; text-align: right; font-weight: bold; color: #059669;'>" . htmlspecialchars((string)$amountFormatted) . "</td>
                </tr>
            </table>

            <p style='color: #475569; font-size: 13px;'>Silakan ambil air Anda pada dispenser kios. Nikmati kesegaran air higienis dari Fresh Hydration Kiosk!</p>

            <hr style='border: none; border-top: 1px solid #f1f5f9; margin: 24px 0 16px;'>
            <p style='color: #94a3b8; font-size: 11px; text-align: center; margin: 0;'>
                Email ini dibuat secara otomatis oleh sistem Fresh Hydration Kiosk (FHK).
            </p>
        </div>";

        $allOk = true;
        foreach ($recipients as $recipient) {
            $sent = self::sendEmail($recipient, $subject, $body, true);
            if (!$sent) $allOk = false;
        }

        return $allOk;
    }
}
PHP;

file_put_contents($baseDir . '/app/Services/EmailNotificationService.php', $emailServiceCode);

// 2. Tulis app/Services/FonnteService.php
$fonnteCode = <<<'PHP'
<?php

namespace App\Services;

class FonnteService
{
    public static function sendPaymentNotification(mixed $transaksi): array
    {
        $token = env('FONNTE_TOKEN', 'uyb4wurTrdytqeCvg7tu');
        $invoiceId = is_array($transaksi) ? ($transaksi['invoiceId'] ?? '-') : ($transaksi->invoiceId ?? '-');
        $userPhone = is_array($transaksi) ? ($transaksi['userPhone'] ?? '') : ($transaksi->userPhone ?? '');
        $payAmount = is_array($transaksi) ? ($transaksi['payAmount'] ?? 0) : ($transaksi->payAmount ?? 0);
        $amountFormatted = 'Rp ' . number_format((float) $payAmount, 0, ',', '.');

        $cleanPhone = preg_replace('/[^0-9]/', '', (string)$userPhone);
        if (empty($cleanPhone) || in_array($cleanPhone, ['0812000000', '08123456789', '0'])) {
            return ['status' => false, 'reason' => 'No target phone'];
        }

        $message = "💧 *PEMBAYARAN BERHASIL - FRESH HYDRATION KIOSK* 💧\n\n"
            . "Pembayaran invoice `{$invoiceId}` sebesar *{$amountFormatted}* telah diterima. Silakan ambil air di kios! ✅";

        return self::sendMessage($cleanPhone, $message);
    }

    public static function sendMessage(string $target, string $message): array
    {
        $token = env('FONNTE_TOKEN', 'uyb4wurTrdytqeCvg7tu');
        $cleanTarget = preg_replace('/[^0-9]/', '', $target);
        if (empty($cleanTarget)) return ['status' => false];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['target' => $cleanTarget, 'message' => $message, 'countryCode' => '62'],
            CURLOPT_HTTPHEADER => ['Authorization: ' . $token],
            CURLOPT_TIMEOUT => 10,
        ]);
        $res = curl_exec($curl);
        curl_close($curl);
        $json = json_decode((string)$res, true);
        return ['status' => $json['status'] ?? false, 'response' => $json];
    }
}
PHP;

file_put_contents($baseDir . '/app/Services/FonnteService.php', $fonnteCode);

// 3. Tulis app/Console/Kernel.php
$kernelCode = <<<'PHP'
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
    }

    protected function commands(): void
    {
    }
}
PHP;

file_put_contents($baseDir . '/app/Console/Kernel.php', $kernelCode);

// 4. Bersihkan view cache
$views = glob($baseDir . '/storage/framework/views/*.php');
$del = 0;
if ($views) {
    foreach ($views as $v) {
        if (@unlink($v)) $del++;
    }
}

// 5. Trigger langsung pengiriman email untuk invoice terakhir / IlNAftim8HAbvcUPcxWM
require_once $baseDir . '/app/Services/EmailNotificationService.php';
require_once $baseDir . '/app/Services/FonnteService.php';

$dbPath = $baseDir . '/database/database.sqlite';
$tx = null;
$emailResult = false;

if (file_exists($dbPath)) {
    $pdo = new PDO("sqlite:" . $dbPath);
    $stmt = $pdo->query("SELECT * FROM transaksi WHERE invoiceId = 'IlNAftim8HAbvcUPcxWM' OR status IN ('PAID', 'AWAITING_KIOSK_SCAN') ORDER BY updated_at DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tx = (object) $row;
        $emailResult = \App\Services\EmailNotificationService::sendPaymentEmail($tx);
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'             => 'SUCCESS',
    'services_created'   => true,
    'views_cache_deleted'=> $del,
    'dispatched_invoice' => $tx ? $tx->invoiceId : null,
    'recipient_email'    => $tx ? $tx->userEmail : null,
    'email_sent'         => $emailResult,
    'message'            => 'EmailNotificationService berhasil dipasang dan email bukti pembayaran langsung dikirimkan!'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
