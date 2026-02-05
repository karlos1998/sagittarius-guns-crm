<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Otobron.pl API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for integrating with otobron.pl weapon listing platform
    |
    */

    'api_url' => 'https://otobron.pl/add-listing/',

    'listing_type' => 'bron',

    'category_id' => 175, // Kategoria broni

    // Dane kontaktowe
    'contact' => [
        'email' => env('OTOBRON_EMAIL', 'sagittarius.fundacja@gmail.com'),
        'phone' => env('OTOBRON_PHONE', '606 101 419'),
    ],

    // Lokalizacja
    'location' => [
        'address' => env('OTOBRON_ADDRESS', 'Stawiskowska 57a, 18-421 Piątnica Poduchowna, Polska'),
        'lat' => env('OTOBRON_LAT', '53.20247679666036'),
        'lng' => env('OTOBRON_LNG', '22.098898574212722'),
    ],

    // Login credentials
    'username' => env('OTOBRON_USERNAME', ''),
    'password' => env('OTOBRON_PASSWORD', ''),

    // Login URL
    'login_url' => 'https://otobron.pl/my-account/',

    // Domyślne wartości
    'defaults' => [
        'condition' => 'Używana',
        'additional_options' => ['Cena do negocjacji'],
    ],
];
