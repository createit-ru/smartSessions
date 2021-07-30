<?php

return [
    'bot_signatures' => [
        'xtype' => 'textfield',
        'value' => "DataForSeoBot|Googlebot|YandexBot|DotBot|bingbot|Mail.RU_Bot|PetalBot|MegaIndex.ru",
        'area' => 'smartsessions_main',
    ],

    'bots_gc_maxlifetime' => [
        'xtype' => 'numberfield',
        'value' => 604800,
        'area' => 'smartsessions_main',
    ],

    'authorized_users_gc_maxlifetime' => [
        'xtype' => 'numberfield',
        'value' => 604800,
        'area' => 'smartsessions_main',
    ],

];