<?php

return [
    'listen' => env('SS_LOCAL_LISTEN', '127.0.0.1:1080'),
    'tls' => [
        'verify_peer' => env('SS_LOCAL_TLS_VERIFY_PEER', true),
        'verify_host' => env('SS_LOCAL_TLS_VERIFY_HOST', true),
        'ca_file' => env('SS_LOCAL_CA_FILE', ''),
        'ca_path' => env('SS_LOCAL_CA_PATH', ''),
    ],
];
