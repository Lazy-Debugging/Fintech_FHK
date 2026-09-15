<?php
include "aiyo_config.php";

$pathToken   = '/api/oauth/token';
$urlGetToken = $host . $pathToken;

$chGetToken = curl_init($urlGetToken);
curl_setopt($chGetToken, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($chGetToken, CURLOPT_ENCODING, '');
curl_setopt($chGetToken, CURLOPT_MAXREDIRS, 10);
curl_setopt($chGetToken, CURLOPT_TIMEOUT, 0);
curl_setopt($chGetToken, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($chGetToken, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($chGetToken, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($chGetToken, CURLOPT_HTTPHEADER, array(
    'Authorization: Basic ' . base64_encode($username . ':' . $password)
));

$result = curl_exec($chGetToken);
$nilai = json_decode($result);
$accessToken = $nilai->responseData->accessToken;
curl_close($chGetToken);
?>
