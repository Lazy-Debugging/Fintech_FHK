<?php

namespace App\Services;

use App\Models\Transaksi;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    /**
     * Inisialisasi dan konfigurasi instance PHPMailer
     */
    /**
     * Inisialisasi dan konfigurasi instance PHPMailer
     */
    private static function createMailer(int $port = 587, string $encryption = 'tls'): PHPMailer
    {
        $mail = new PHPMailer(true);

        $host       = config('mail.mailers.smtp.host', env('MAIL_HOST', 'smtp.gmail.com'));
        $username   = config('mail.mailers.smtp.username', env('MAIL_USERNAME', '24n40004@student.unika.ac.id'));
        $password   = config('mail.mailers.smtp.password', env('MAIL_PASSWORD', 'vkte vmnm fpyx ktro'));
        $fromAddress = config('mail.from.address', env('MAIL_FROM_ADDRESS', $username));
        $fromName    = config('mail.from.name', env('MAIL_FROM_NAME', 'notifikiasifhk'));

        // Server settings
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = $encryption;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 10;

        // Opsi SSL untuk kompatibilitas shared hosting / cPanel
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

    /**
     * Kirim email umum (dengan auto-fallback: PHPMailer TLS -> PHPMailer SSL -> Laravel Mail -> Native mail())
     */
    public static function sendEmail(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Email dispatch skipped: Invalid recipient email', ['to' => $to]);
            return false;
        }

        // Percobaan 1 & 2: PHPMailer jika library tersedia
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                $mail = self::createMailer(587, 'tls');
                $mail->addAddress($to);
                $mail->isHTML($isHtml);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                $mail->send();
                Log::info('Email successfully sent via PHPMailer (Port 587 TLS)', ['to' => $to, 'subject' => $subject]);
                return true;
            } catch (\Throwable $e1) {
                Log::warning('PHPMailer Port 587 failed, trying Port 465 SSL: ' . $e1->getMessage(), ['to' => $to]);
                try {
                    $mail2 = self::createMailer(465, 'ssl');
                    $mail2->addAddress($to);
                    $mail2->isHTML($isHtml);
                    $mail2->Subject = $subject;
                    $mail2->Body    = $body;
                    $mail2->send();
                    Log::info('Email successfully sent via PHPMailer (Port 465 SSL Fallback)', ['to' => $to, 'subject' => $subject]);
                    return true;
                } catch (\Throwable $e2) {
                    Log::error('PHPMailer all attempts failed: ' . $e2->getMessage());
                }
            }
        }

        // Percobaan 3: Laravel Mail Facade
        try {
            if (class_exists(\Illuminate\Support\Facades\Mail::class)) {
                $fromAddress = env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME', '24n40004@student.unika.ac.id'));
                $fromName    = env('MAIL_FROM_NAME', 'notifikiasifhk');
                \Illuminate\Support\Facades\Mail::html($body, function ($msg) use ($to, $subject, $fromAddress, $fromName) {
                    $msg->to($to)->subject($subject)->from($fromAddress, $fromName);
                });
                Log::info('Email successfully sent via Laravel Mail Facade', ['to' => $to, 'subject' => $subject]);
                return true;
            }
        } catch (\Throwable $eMail) {
            Log::warning('Laravel Mail Facade error: ' . $eMail->getMessage());
        }

        // Percobaan 4: Native mail()
        try {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: notifikiasifhk <24n40004@student.unika.ac.id>\r\n";
            $sent = @mail($to, $subject, $body, $headers);
            if ($sent) {
                Log::info('Email sent via native mail() fallback', ['to' => $to]);
                return true;
            }
        } catch (\Throwable $eNative) {
            Log::error('Native mail() failed: ' . $eNative->getMessage());
        }

        return false;
    }

    /**
     * Kirim email kode OTP (sesuai fungsi sendOTPEmail pada slide)
     */
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

    /**
     * Kirim email bukti pembayaran transaksi berhasil
     */
    public static function sendPaymentEmail(mixed $transaksi): bool
    {
        // 1. Ambil data pengguna dari Transaksi atau relasi User (Google / Auth login)
        $userId      = is_array($transaksi) ? ($transaksi['user_id'] ?? null) : ($transaksi->user_id ?? null);
        $userObj     = null;

        if (!empty($userId)) {
            $userObj = \App\Models\User::find($userId);
        } elseif (function_exists('auth') && auth()->check()) {
            $userObj = auth()->user();
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

        // Tentukan target penerima email (utamakan email akun login Google / Gmail)
        $recipients = [];
        if (!empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL) && !str_ends_with($userEmail, '@fhk.id') && $userEmail !== 'customer@fhk.id') {
            $recipients[] = $userEmail;
        }

        $adminEmail = env('MAIL_ADMIN_NOTIFICATION', env('MAIL_USERNAME', '24n40004@student.unika.ac.id'));
        if (!empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $adminEmail;
        }

        $recipients = array_unique($recipients);
        if (empty($recipients)) {
            Log::info('Email notification skipped: No recipient email found', ['invoiceId' => $invoiceId]);
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

    /**
     * Kirim email notifikasi tagihan yang tidak terbayar / kedaluwarsa setelah batas waktu (3 menit)
     */
    public static function sendUnpaidExpiredEmail(mixed $transaksi): bool
    {
        $userId      = is_array($transaksi) ? ($transaksi['user_id'] ?? null) : ($transaksi->user_id ?? null);
        $userObj     = !empty($userId) ? \App\Models\User::find($userId) : (function_exists('auth') && auth()->check() ? auth()->user() : null);

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
        if (!empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL) && !str_ends_with($userEmail, '@fhk.id') && $userEmail !== 'customer@fhk.id') {
            $recipients[] = $userEmail;
        }

        $adminEmail = env('MAIL_ADMIN_NOTIFICATION', env('MAIL_USERNAME', '24n40004@student.unika.ac.id'));
        if (!empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $adminEmail;
        }

        $recipients = array_unique($recipients);
        if (empty($recipients)) {
            Log::info('Unpaid expired email skipped: No recipient email found', ['invoiceId' => $invoiceId]);
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

        $subject = "Pemberitahuan: Tagihan Pembayaran Kedaluwarsa - Invoice {$invoiceId}";

        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 580px; margin: auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #0284c7; margin: 0;'>💧 Fresh Hydration Kiosk</h2>
                <p style='color: #64748b; font-size: 14px; margin-top: 4px;'>Pemberitahuan Tagihan Kedaluwarsa</p>
            </div>
            
            <div style='background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px; margin-bottom: 20px; text-align: center;'>
                <span style='color: #991b1b; font-weight: bold; font-size: 16px;'>⚠️ WAKTU PEMBAYARAN TELAH BERAKHIR (KEDALUWARSA)</span>
            </div>

            <p>Halo <b>" . htmlspecialchars($userName) . "</b>,</p>
            <p>Kami menginformasikan bahwa sesi pembayaran QRIS untuk pesanan air minum Anda telah berakhir karena tidak ada pembayaran yang diterima dalam batas waktu <b>3 menit</b>.</p>

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
                    <td style='padding: 8px 0; color: #64748b;'>Waktu Kedaluwarsa</td>
                    <td style='padding: 8px 0; text-align: right;'>" . htmlspecialchars($timeFormatted) . "</td>
                </tr>
                <tr style='background: #f8fafc;'>
                    <td style='padding: 12px 8px; font-weight: bold; color: #1e293b; font-size: 15px;'>Nominal Tagihan</td>
                    <td style='padding: 12px 8px; font-weight: bold; text-align: right; color: #dc2626; font-size: 16px;'>" . htmlspecialchars($amountFormatted) . "</td>
                </tr>
            </table>

            <p style='font-size: 13px; color: #475569;'>
                Jika Anda masih ingin melakukan pembelian air minum, silakan lakukan pemesanan ulang melalui layar Kios Fresh Hydration Kiosk.
            </p>
            
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

    /**
     * Memeriksa dan membatalkan transaksi yang belum dibayar dalam batas waktu tertentu (default: 3 menit)
     */
    public static function checkAndExpireUnpaidTransactions(int $minutes = 3): int
    {
        $cutoff = now()->subMinutes($minutes);
        $unpaidTxs = Transaksi::whereIn('status', ['PENDING', 'NEW', 'UNPAID'])
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($unpaidTxs as $tx) {
            $tx->update([
                'status'  => 'EXPIRED',
                'remarks' => trim(($tx->remarks ?? '') . ' | Auto-expired setelah 3 menit belum dibayar')
            ]);
            $count++;
        }

        if ($count > 0) {
            Log::info("Expired {$count} unpaid transactions older than {$minutes} minutes.");
        }

        return $count;
    }
}
