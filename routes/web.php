<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameImageController;
use App\Http\Controllers\KennelController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PlayerProfileController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('players/{user:username}', PlayerProfileController::class)->name('players.show');
    Route::get('kennel', [KennelController::class, 'index'])->name('kennel.index');
    Route::post('kennel', [KennelController::class, 'store'])->name('kennel.store');
    Route::get('media/breeds/{breed}/{variant}', [GameImageController::class, 'breed'])
        ->where('breed', '[a-z_]+')->whereIn('variant', ['portrait', 'icon'])->name('breeds.image');
    Route::get('media/pet-scene', [GameImageController::class, 'scene'])->name('pet-scene');
});

require __DIR__.'/settings.php';
