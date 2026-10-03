<?php

namespace App\Models;

use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string|null $avatar_path
 * @property string|null $bio
 * @property PlayerStatus $status
 * @property int $coins
 * @property int $gems
 * @property int $pet_slots
 * @property string $experience
 * @property int $level
 * @property int $exhibition_wins
 * @property int $competition_wins
 * @property int $walks_count
 * @property int $trainings_count
 * @property array<string, int> $pet_statistics
 * @property int $active_days
 * @property CarbonImmutable|null $last_pet_action_at
 * @property-read int $pets_count
 * @property string $locale
 * @property string $timezone
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $tutorial_completed_at
 * @property CarbonImmutable|null $starter_pet_claimed_at
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'password', 'bio'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'avatar_path'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return HasMany<Pet, $this> */
    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    /** @return HasMany<AssetUnlock, $this> */
    public function assetUnlocks(): HasMany
    {
        return $this->hasMany(AssetUnlock::class);
    }

    /** @return HasMany<InventoryItem, $this> */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /** @return HasMany<ItemPurchase, $this> */
    public function itemPurchases(): HasMany
    {
        return $this->hasMany(ItemPurchase::class);
    }

    /** @return HasMany<ItemUsage, $this> */
    public function itemUsages(): HasMany
    {
        return $this->hasMany(ItemUsage::class);
    }

    public function preferredLocale(): string
    {
        return $this->locale ?? config('localization.default', 'ru');
    }

    public function avatarVersion(): ?string
    {
        return $this->avatar_path === null ? null : hash('sha256', $this->avatar_path);
    }

    public function canClaimStarterPet(): bool
    {
        return self::query()->whereKey($this->id)->eligibleForStarterPet()->exists();
    }

    /** @param Builder<User> $query */
    #[Scope]
    protected function eligibleForStarterPet(Builder $query): void
    {
        $query->whereNull('starter_pet_claimed_at')->whereDoesntHave('pets');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlayerStatus::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'coins' => 'integer',
            'gems' => 'integer',
            'pet_slots' => 'integer',
            'experience' => 'string',
            'level' => 'integer',
            'exhibition_wins' => 'integer',
            'competition_wins' => 'integer',
            'walks_count' => 'integer',
            'trainings_count' => 'integer',
            'pet_statistics' => 'array',
            'active_days' => 'integer',
            'last_pet_action_at' => 'immutable_datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'tutorial_completed_at' => 'datetime',
            'starter_pet_claimed_at' => 'datetime',
        ];
    }
}
