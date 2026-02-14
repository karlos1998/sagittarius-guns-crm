<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Netgun.pl API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for integrating with netgun.pl weapon listing platform
    |
    */

    'base_url' => 'https://www.netgun.pl',

    // Base URL without www for image uploads (must match exactly what netgun expects)
    'image_base_url' => 'https://netgun.pl',

    // API endpoints
    'endpoints' => [
        'image_upload' => '/api/image-uploader',
        'new_listing' => '/nowe-ogloszenie',
        'promote' => '/promowanie-ogloszenia',
    ],

    // Dane kontaktowe
    'contact' => [
        'email' => env('NETGUN_EMAIL', 'sagittarius.fundacja@gmail.com'),
        'phone' => env('NETGUN_PHONE', '606101419'),
    ],

    // Lokalizacja
    'location' => [
        'nickname' => env('NETGUN_NICKNAME', 'Klub Strzelecki Sagittarius'),
        'city' => env('NETGUN_CITY', 'Piątnica'),
        'province' => env('NETGUN_PROVINCE', 'podlaskie'),
    ],

    // Login credentials
    'username' => env('NETGUN_USERNAME', ''),
    'password' => env('NETGUN_PASSWORD', ''),

    // Domyślne wartości ogłoszenia
    'defaults' => [
        'transaction_type' => 'sell', // sell, buy, exchange
        'item_state' => 'USED', // NEW, USED
        'category' => 'pistolety', // Default category
        'url' => 'militariaforty.pl',
    ],

    // Mapowanie kategorii broni na kategorie Netgun
    'category_mapping' => [
        'pistolet' => 'pistolety',
        'pistolety' => 'pistolety',
        'rewolwer' => 'rewolwery',
        'rewolwery' => 'rewolwery',
        'karabin' => 'karabinki-automatyczne-szturmowe',
        'karabinek' => 'karabinki-automatyczne-szturmowe',
        'strzelba' => 'strzelby',
        'strzelby' => 'strzelby',
        'shotgun' => 'strzelby',
        'snajperka' => 'karabiny-sniper',
        'pm' => 'pistolety-maszynowe',
        'pistolet maszynowy' => 'pistolety-maszynowe',
    ],
];
