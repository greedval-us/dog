<?php

use App\Http\Controllers\AssetImageController;
use App\Http\Controllers\AssetPurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameImageController;
use App\Http\Controllers\KennelController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PetAppearanceController;
use App\Http\Controllers\PlayerAvatarController;
use App\Http\Controllers\PlayerProfileController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('auth')->group(function () {
    Route::get('media/players/{user:username}/avatar', [PlayerAvatarController::class, 'show'])->name('players.avatar.show');
    Route::post('settings/avatar', [PlayerAvatarController::class, 'store'])->middleware('throttle:10,1')->name('players.avatar.store');
    Route::delete('settings/avatar', [PlayerAvatarController::class, 'destroy'])->middleware('throttle:10,1')->name('players.avatar.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::put('pets/{pet}/appearance', [PetAppearanceController::class, 'update'])
        ->middleware('throttle:30,1')->name('pets.appearance.update');
    Route::post('pets/{pet}/appearance/purchases', [AssetPurchaseController::class, 'store'])
        ->middleware('throttle:15,1')->name('pets.appearance.purchase');
    Route::get('media/assets/{asset}/{variant}', AssetImageController::class)
        ->whereIn('variant', ['image', 'icon'])->name('assets.image');
    Route::get('players/{user:username}', PlayerProfileController::class)->name('players.show');
    Route::get('kennel', [KennelController::class, 'index'])->name('kennel.index');
    Route::post('kennel', [KennelController::class, 'store'])->name('kennel.store');
    Route::get('media/breeds/{breed}/{variant}', [GameImageController::class, 'breed'])
        ->where('breed', '[a-z_]+')->whereIn('variant', ['portrait', 'icon'])->name('breeds.image');
    Route::get('media/pet-scene', [GameImageController::class, 'scene'])->name('pet-scene');
});

require __DIR__.'/settings.php';
