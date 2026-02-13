<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/weapons', function () {
    return view('weapons.index');
});

// Route to view otobron response HTML files
Route::get('/otobron-response/{filename}', function ($filename) {
    $path = storage_path("app/otobron_responses/{$filename}");

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path);
})->where('filename', '.*\.html');

// Route to view netgun response HTML files
Route::get('/netgun-response/{filename}', function ($filename) {
    $path = storage_path("app/netgun_responses/{$filename}");

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path);
})->where('filename', '.*\.html');
