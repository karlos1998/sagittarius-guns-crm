<?php

use App\Http\Controllers\Api\WeaponImportController;
use Illuminate\Support\Facades\Route;

Route::get('/import/weapons', [WeaponImportController::class, 'index']);
Route::post('/import/weapons', [WeaponImportController::class, 'store']);
