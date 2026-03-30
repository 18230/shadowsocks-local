<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use SsLocal\Support\ProxyService;
use SsLocal\Support\TlsSettings;

require dirname(__DIR__) . '/vendor/autoload.php';

$proxy = ProxyService::fromListenAddress('127.0.0.1:1080', TlsSettings::fromIni());
$client = new Client($proxy->guzzleOptions());

$response = $client->get('https://api.ipify.org?format=json');
echo $response->getBody()->getContents(), PHP_EOL;
