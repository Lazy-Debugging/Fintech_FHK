<?php
/**
 * Test & Trigger Email Bukti Pembayaran untuk Transaksi Tertentu / Terakhir
 * Akses: https://app.mesinbayar.com/fhk/send_pending_emails.php?invoiceId=S1ZfNPCCouJwrgDEV7t7
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Transaksi;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\FonnteService;

$invoiceId = $_GET['invoiceId'] ?? null;

if ($invoiceId) {
    $tx = Transaksi::where('invoiceId', $invoiceId)->orWhere('referenceId', $invoiceId)->first();
} else {
    $tx = Transaksi::latest('updated_at')->first();
}

$results = [];

if ($tx) {
    $user = null;
    if ($tx->user_id) {
        $user = User::find($tx->user_id);
    }

    $emailTarget = $tx->userEmail;
    if (empty($emailTarget) || str_ends_with($emailTarget, '@fhk.id')) {
        $emailTarget = $user?->email ?? $emailTarget;
    }

    $emailSent = EmailNotificationService::sendPaymentEmail($tx);
    $waSent    = FonnteService::sendPaymentNotification($tx);

    $results = [
        'invoiceId'    => $tx->invoiceId,
        'referenceId'  => $tx->referenceId,
        'userName'     => $tx->userName,
        'userEmail'    => $tx->userEmail,
        'resolvedEmail'=> $emailTarget,
        'userPhone'    => $tx->userPhone,
        'status'       => $tx->status,
        'payAmount'    => $tx->payAmount,
        'email_sent'   => $emailSent,
        'wa_sent'      => $waSent,
    ];
} else {
    $results = [
        'error' => 'Transaksi tidak ditemukan: ' . $invoiceId
    ];
}

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
