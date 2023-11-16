<?php

return [
    'bot_signatures' => [
        'xtype' => 'textfield',
        'value' => "YandexBot|Yandex|Googlebot|DataForSeoBot|DotBot|bingbot|Mail.RU_Bot|PetalBot|MegaIndex.ru|serpstatbot|BLEXBot|vip0|Crawler|ClaudeBot|research-bot|Go-http-client",
        'area' => 'smartsessions_main',
    ],
    'bots_gc_maxlifetime' => [
        'xtype' => 'numberfield',
        'value' => 86400,
        'area' => 'smartsessions_main',
    ],
    'empty_user_agent_gc_maxlifetime' => [
        'xtype' => 'numberfield',
        'value' => 86400,
        'area' => 'smartsessions_main',
    ],
    'authorized_users_gc_maxlifetime' => [
        'xtype' => 'numberfield',
        'value' => 604800,
        'area' => 'smartsessions_main',
    ]
];