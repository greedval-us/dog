<?php

namespace App\MoonShine\Support;

use App\Models\AdminAuditLog;
use App\Models\CurrencyTransaction;
use App\Models\Dog;
use App\Models\DogWorkShift;
use App\Models\DogWorkType;
use App\Models\GameAsset;
use App\Models\Item;
use App\Models\ItemPurchase;
use App\Models\KennelPurchase;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\ShopOffer;
use App\Models\Skill;
use App\Models\StatusEffect;
use App\Models\Training;
use App\Models\User;
use App\MoonShine\Enums\StaffRole;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Laravel\MoonShineAuth;
use MoonShine\Support\Enums\Ability;

final class StaffAccess
{
    public static function role(?MoonshineUser $user = null): ?StaffRole
    {
        $user ??= MoonShineAuth::getGuard()->user();

        if (! $user instanceof MoonshineUser) {
            return null;
        }

        return StaffRole::tryFrom((string) $user->moonshineUserRole?->getAttribute('code'));
    }

    /** @param class-string $model */
    public static function allows(string $model, Ability $ability, ?MoonshineUser $user = null): bool
    {
        $role = self::role($user);
        $read = in_array($ability, [Ability::VIEW_ANY, Ability::VIEW], true);

        return match ($model) {
            MoonshineUser::class => $role === StaffRole::Administrator
                && ($read || in_array($ability, [Ability::CREATE, Ability::UPDATE], true)),
            MoonshineUserRole::class => $role === StaffRole::Administrator && $read,
            User::class => in_array($role, [StaffRole::Administrator, StaffRole::Moderator], true)
                && ($read || $ability === Ability::UPDATE),
            Pet::class, AdminAuditLog::class => in_array($role, [StaffRole::Administrator, StaffRole::Moderator], true) && $read,
            CurrencyTransaction::class, ItemPurchase::class, KennelPurchase::class, PetCareAction::class, DogWorkShift::class => in_array($role, [StaffRole::Administrator, StaffRole::Analyst], true) && $read,
            Dog::class, Item::class, ShopOffer::class, GameAsset::class, Training::class, Skill::class, DogWorkType::class, StatusEffect::class => ($read && in_array($role, [StaffRole::Administrator, StaffRole::Analyst], true))
                || ($role === StaffRole::Administrator && $ability === Ability::UPDATE),
            default => false,
        };
    }
}
