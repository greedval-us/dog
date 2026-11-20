<?php

use App\Http\Controllers\PetBoardingController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TelegramConnectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('settings/telegram/link', [TelegramConnectionController::class, 'store'])
        ->middleware('throttle:6,1')->name('telegram.link');
    Route::delete('settings/telegram', [TelegramConnectionController::class, 'destroy'])
        ->middleware('throttle:6,1')->name('telegram.unlink');
    Route::put('settings/boarding', [PetBoardingController::class, 'update'])
        ->middleware('throttle:15,1')->name('boarding.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
