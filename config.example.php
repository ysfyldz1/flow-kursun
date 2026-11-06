<?php

// Kuruma özel ayarlar. Bu dosyayı config.php olarak kopyalayın; config.php git'e girmez.
return [
    'app' => [
        'name'     => 'Flow',
        'url'      => 'http://flow2.test',
        'debug'    => true,
        'timezone' => 'Europe/Istanbul',
        'locale'   => 'tr',          // kurum varsayılan dili
        'locales'  => ['tr', 'en'],
    ],
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'flow2',
        'user'     => 'root',
        'pass'     => '',
    ],
    'session' => [
        'name'     => 'flow_session',
        'lifetime' => 60 * 60 * 8,
    ],
];
