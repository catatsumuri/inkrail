<?php

use App\Http\Controllers\DeleteNotificationController;
use App\Http\Controllers\MarkNotificationAsReadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::patch('notifications/{notification}/read', MarkNotificationAsReadController::class)
    ->middleware('auth')
    ->name('notifications.read');

Route::delete('notifications/{notification}', DeleteNotificationController::class)
    ->middleware('auth')
    ->name('notifications.destroy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
