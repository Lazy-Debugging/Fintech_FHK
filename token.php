<?php
/**
 * Mendapatkan OAuth Token dari AiYO API
 * Sesuai dengan Meeting 03 Slide 22 (token.php)
 */
include_once __DIR__ . "/aiyo_config.php";

$pathToken   = '/api/oauth/token';
$urlGetToken = $host . $pathToken;

$chGetToken = curl_init($urlGetToken);
curl_setopt($chGetToken, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($chGetToken, CURLOPT_ENCODING, '');
curl_setopt($chGetToken, CURLOPT_MAXREDIRS, 10);
curl_setopt($chGetToken, CURLOPT_TIMEOUT, 30);
curl_setopt($chGetToken, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($chGetToken, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($chGetToken, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($chGetToken, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($chGetToken, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($chGetToken, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($chGetToken, CURLOPT_HTTPHEADER, array(
    'Authorization: Basic ' . base64_encode($username . ':' . $password)
));

$result = curl_exec($chGetToken);
$curlError = curl_error($chGetToken);
curl_close($chGetToken);

$nilai = json_decode($result);
$accessToken = $nilai->responseData->accessToken ?? null;

// Jika dipanggil langsung lewat browser/terminal (bukan di-include)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    if ($accessToken) {
        echo json_encode([
            'success'      => true,
            'responseCode' => $nilai->responseCode ?? '2000000',
            'accessToken'  => $accessToken,
            'expiresIn'    => $nilai->responseData->expiresIn ?? 3600,
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'success'   => false,
            'error'     => $curlError ?: 'Gagal mendapatkan OAuth Access Token',
            'rawResult' => $result
        ], JSON_PRETTY_PRINT);
    }
}
?>
