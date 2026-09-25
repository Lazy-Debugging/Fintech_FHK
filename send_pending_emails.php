<?php
/**
 * Test & Trigger Email Bukti Pembayaran untuk Transaksi Tertentu / Terakhir
 * Akses: https://app.mesinbayar.com/fhk/send_pending_emails.php?invoiceId=S1ZfNPCCouJwrgDEV7t7
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$invoiceId = $_GET['invoiceId'] ?? null;
$steps = [];
$txData = null;

// 1. Coba Bootstrap Laravel
try {
    if (file_exists(__DIR__ . '/vendor/autoload.php') && file_exists(__DIR__ . '/bootstrap/app.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->bootstrap();

        if ($invoiceId) {
            $txModel = \App\Models\Transaksi::where('invoiceId', $invoiceId)->orWhere('referenceId', $invoiceId)->first();
        } else {
            $txModel = \App\Models\Transaksi::latest('updated_at')->first();
        }

        if ($txModel) {
            $user = $txModel->user_id ? \App\Models\User::find($txModel->user_id) : null;
            $txData = $txModel;
            $emailSent = \App\Services\EmailNotificationService::sendPaymentEmail($txModel);
            $waSent    = \App\Services\FonnteService::sendPaymentNotification($txModel);

            $steps['email_sent'] = $emailSent;
            $steps['wa_sent']    = $waSent;
            $steps['method']     = 'Laravel Eloquent Dispatcher';
        }
    }
} catch (\Throwable $e) {
    $steps['laravel_bootstrap_error'] = $e->getMessage();
}

// 2. Fallback via SQLite Direct jika Laravel belum menghasilkan
if (!$txData) {
    $dbPath = __DIR__ . '/database/database.sqlite';
    if (file_exists($dbPath)) {
        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($invoiceId) {
                $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE invoiceId = ? OR referenceId = ? LIMIT 1");
                $stmt->execute([$invoiceId, $invoiceId]);
            } else {
                $stmt = $pdo->query("SELECT * FROM transaksi ORDER BY updated_at DESC LIMIT 1");
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $txData = (object) $row;
                // Coba kirim via PHPMailer / mail()
                require_once __DIR__ . '/app/Services/EmailNotificationService.php';
                require_once __DIR__ . '/app/Services/FonnteService.php';

                $emailSent = \App\Services\EmailNotificationService::sendPaymentEmail($txData);
                $waSent    = \App\Services\FonnteService::sendPaymentNotification($txData);

                $steps['email_sent'] = $emailSent;
                $steps['wa_sent']    = $waSent;
                $steps['method']     = 'Direct SQLite PDO Dispatcher';
            } else {
                $steps['error'] = 'Transaksi tidak ditemukan di database.';
            }
        } catch (\Throwable $ePdo) {
            $steps['pdo_error'] = $ePdo->getMessage();
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'invoice_target' => $invoiceId,
    'transaction'    => $txData ? [
        'invoiceId'   => $txData->invoiceId ?? null,
        'referenceId' => $txData->referenceId ?? null,
        'userName'    => $txData->userName ?? null,
        'userEmail'   => $txData->userEmail ?? null,
        'userPhone'   => $txData->userPhone ?? null,
        'status'      => $txData->status ?? null,
        'payAmount'   => $txData->payAmount ?? null,
    ] : null,
    'dispatch_result' => $steps
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
