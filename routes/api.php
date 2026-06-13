<?php

use App\Http\Controllers\Api\WeaponImportController;
use Illuminate\Support\Facades\Route;

Route::post('/import/weapons', [WeaponImportController::class, 'store']);
