<?php

use App\Http\Controllers\AssetImageController;
use App\Http\Controllers\AssetPurchaseController;
use App\Http\Controllers\CareItemController;
use App\Http\Controllers\DailyWorkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DogWorkController;
use App\Http\Controllers\GameImageController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KennelController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PetAppearanceController;
use App\Http\Controllers\PetCareController;
use App\Http\Controllers\PetSkillController;
use App\Http\Controllers\PetSlotController;
use App\Http\Controllers\PlayerAvatarController;
use App\Http\Controllers\PlayerProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SystemNotificationController;
use App\Http\Controllers\VeterinarianController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('auth')->group(function () {
    Route::get('notifications', [SystemNotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [SystemNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{notification}', [SystemNotificationController::class, 'update'])
        ->whereUuid('notification')->name('notifications.update');
    Route::get('media/players/{user:username}/avatar', [PlayerAvatarController::class, 'show'])->name('players.avatar.show');
    Route::post('settings/avatar', [PlayerAvatarController::class, 'store'])->middleware('throttle:10,1')->name('players.avatar.store');
    Route::delete('settings/avatar', [PlayerAvatarController::class, 'destroy'])->middleware('throttle:10,1')->name('players.avatar.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('care-items', CareItemController::class)->name('care-items');
    Route::get('dog-work', [DogWorkController::class, 'index'])->name('dog-work.index');
    Route::get('veterinarian', [VeterinarianController::class, 'index'])->name('veterinarian.index');
    Route::post('veterinarian', [VeterinarianController::class, 'store'])->middleware('throttle:15,1')->name('veterinarian.store');
    Route::post('dog-work', [DogWorkController::class, 'store'])->middleware('throttle:15,1')->name('dog-work.store');
    Route::post('dog-work/complete', [DogWorkController::class, 'complete'])->middleware('throttle:15,1')->name('dog-work.complete');
    Route::post('daily-work', [DailyWorkController::class, 'store'])->middleware('throttle:15,1')->name('daily-work.store');
    Route::post('pets/{pet}/care', [PetCareController::class, 'store'])->middleware('throttle:30,1')->name('pets.care.store');
    Route::post('pets/{pet}/care/complete', [PetCareController::class, 'complete'])->middleware('throttle:30,1')->name('pets.care.complete');
    Route::post('pets/{pet}/skills', [PetSkillController::class, 'store'])->middleware('throttle:15,1')->name('pets.skills.store');
    Route::get('shop', [ShopController::class, 'index'])->name('shop.index');
    Route::get('inventory', InventoryController::class)->name('inventory.index');
    Route::post('shop/purchases', [ShopController::class, 'store'])->middleware('throttle:15,1')->name('shop.store');
    Route::post('pet-slots', [PetSlotController::class, 'store'])->middleware('throttle:15,1')->name('pet-slots.store');
    Route::put('pets/{pet}/appearance', [PetAppearanceController::class, 'update'])
        ->middleware('throttle:30,1')->name('pets.appearance.update');
    Route::post('pets/{pet}/appearance/purchases', [AssetPurchaseController::class, 'store'])
        ->middleware('throttle:15,1')->name('pets.appearance.purchase');
    Route::get('media/assets/{asset}/{variant}', AssetImageController::class)
        ->whereIn('variant', ['image', 'icon'])->name('assets.image');
    Route::get('players/{user:username}', PlayerProfileController::class)->name('players.show');
    Route::get('kennel', [KennelController::class, 'index'])->name('kennel.index');
    Route::post('kennel', [KennelController::class, 'store'])->name('kennel.store');
    Route::post('kennel/purchases', [KennelController::class, 'purchase'])->middleware('throttle:15,1')->name('kennel.purchase');
    Route::get('media/breeds/{breed}/{variant}', [GameImageController::class, 'breed'])
        ->where('breed', '[a-z_]+')->whereIn('variant', ['portrait', 'icon'])->name('breeds.image');
    Route::get('media/pet-scene', [GameImageController::class, 'scene'])->name('pet-scene');
});

require __DIR__.'/settings.php';
