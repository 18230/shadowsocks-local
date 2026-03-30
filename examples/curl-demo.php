<?php

declare(strict_types=1);

use SsLocal\Support\ProxyService;
use SsLocal\Support\TlsSettings;

require dirname(__DIR__) . '/vendor/autoload.php';

$proxy = ProxyService::fromListenAddress('127.0.0.1:1080', TlsSettings::fromIni());

$ch = curl_init('https://api.ipify.org?format=json');
$proxy->applyToCurlHandle($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$result = curl_exec($ch);
var_dump($result, curl_error($ch));
curl_close($ch);
