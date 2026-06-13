<?php

use App\Http\Controllers\Web\InstallController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'show'])->name('install.show');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');
Route::get('/install/success', [InstallController::class, 'success'])->name('install.success');
