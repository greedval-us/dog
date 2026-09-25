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
 * @property int $experience
 * @property int $level
 * @property int $exhibition_wins
 * @property int $competition_wins
 * @property int $walks_count
 * @property int $trainings_count
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

    public function preferredLocale(): string
    {
        return $this->locale;
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
            'experience' => 'integer',
            'level' => 'integer',
            'exhibition_wins' => 'integer',
            'competition_wins' => 'integer',
            'walks_count' => 'integer',
            'trainings_count' => 'integer',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'tutorial_completed_at' => 'datetime',
            'starter_pet_claimed_at' => 'datetime',
        ];
    }
}
