<?php

namespace App\Models;

use App\Modules\Appearance\Enums\AssetCurrency;
use App\Modules\Appearance\Enums\AssetKind;
use Database\Factories\GameAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $code
 * @property AssetKind $kind
 * @property array<string, string> $name
 * @property int|null $dog_id
 * @property string|null $coat_color
 * @property string|null $pose
 * @property string $image_path
 * @property string $icon_path
 * @property int|null $coins_price
 * @property int|null $gems_price
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['code', 'kind', 'name', 'dog_id', 'coat_color', 'pose', 'image_path', 'icon_path', 'coins_price', 'gems_price', 'is_active', 'sort_order'])]
#[Hidden(['image_path', 'icon_path'])]
class GameAsset extends Model
{
    /** @use HasFactory<GameAssetFactory> */
    use HasFactory;

    /** @return BelongsTo<Dog, $this> */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    /** @return HasMany<AssetUnlock, $this> */
    public function unlocks(): HasMany
    {
        return $this->hasMany(AssetUnlock::class);
    }

    /** @param Builder<GameAsset> $query */
    #[Scope]
    protected function compatibleWith(Builder $query, Pet $pet): void
    {
        $query->where(function (Builder $query) use ($pet): void {
            $query->where(function (Builder $query): void {
                $query->where('kind', AssetKind::Background)->whereNull('dog_id')->whereNull('coat_color');
            })->orWhere(function (Builder $query) use ($pet): void {
                $query->where('kind', AssetKind::Portrait)->where('dog_id', $pet->dog_id)->where('coat_color', $pet->coat_color);
            });
        });
    }

    public function matches(Pet $pet): bool
    {
        return match ($this->kind) {
            AssetKind::Portrait => $this->dog_id === $pet->dog_id && $this->coat_color === $pet->coat_color,
            AssetKind::Background => $this->dog_id === null && $this->coat_color === null,
        };
    }

    public function isFree(): bool
    {
        return $this->coins_price === null && $this->gems_price === null;
    }

    public function hasValidPrice(): bool
    {
        return ($this->coins_price === null || $this->coins_price > 0)
            && ($this->gems_price === null || $this->gems_price > 0);
    }

    public function priceFor(AssetCurrency $currency): ?int
    {
        return match ($currency) {
            AssetCurrency::Coins => $this->coins_price,
            AssetCurrency::Gems => $this->gems_price,
        };
    }

    public function hasFiles(): bool
    {
        return $this->mediaPath('image') !== null && $this->mediaPath('icon') !== null;
    }

    public function mediaPath(string $variant): ?string
    {
        $path = match ($variant) {
            'image' => $this->image_path,
            'icon' => $this->icon_path,
            default => '',
        };

        if (! preg_match('#\A(?:appearance|breeds|scenes)/[a-z0-9_/-]+\.png\z#D', $path)) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? $path : null;
    }

    public function localizedName(string $locale): string
    {
        return $this->name[$locale] ?? $this->name['ru'] ?? $this->code;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => AssetKind::class,
            'name' => 'array',
            'dog_id' => 'integer',
            'coins_price' => 'integer',
            'gems_price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
