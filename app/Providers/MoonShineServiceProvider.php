<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\AdminAuditLogResource;
use App\MoonShine\Resources\CurrencyTransactionResource;
use App\MoonShine\Resources\DogResource;
use App\MoonShine\Resources\DogWorkShiftResource;
use App\MoonShine\Resources\DogWorkTypeResource;
use App\MoonShine\Resources\GameAssetResource;
use App\MoonShine\Resources\ItemPurchaseResource;
use App\MoonShine\Resources\ItemResource;
use App\MoonShine\Resources\KennelPurchaseResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\PetCareActionResource;
use App\MoonShine\Resources\PetHistoryEventResource;
use App\MoonShine\Resources\PetHistoryPhraseResource;
use App\MoonShine\Resources\PetResource;
use App\MoonShine\Resources\PlayerResource;
use App\MoonShine\Resources\ShopOfferResource;
use App\MoonShine\Resources\SkillResource;
use App\MoonShine\Resources\StatusEffectResource;
use App\MoonShine\Resources\TrainingResource;
use App\MoonShine\Support\StaffAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Ability;

class MoonShineServiceProvider extends ServiceProvider
{
    /** @param CoreContract<MoonShineConfigurator> $core */
    public function boot(CoreContract $core): void
    {
        $core->getConfig()->authorizationRules(
            static fn (ResourceContract $resource, Model $user, Ability $ability): bool => $resource instanceof ModelResource && $user instanceof MoonshineUser
                && StaffAccess::allows($resource->getModel()::class, $ability, $user)
        );

        $core->resources([
            MoonShineUserResource::class, MoonShineUserRoleResource::class,
            PlayerResource::class, PetResource::class, AdminAuditLogResource::class,
            CurrencyTransactionResource::class, ItemPurchaseResource::class, KennelPurchaseResource::class,
            PetCareActionResource::class, DogWorkShiftResource::class,
            DogResource::class, ItemResource::class, ShopOfferResource::class, GameAssetResource::class,
            TrainingResource::class, SkillResource::class, DogWorkTypeResource::class, StatusEffectResource::class,
            PetHistoryEventResource::class, PetHistoryPhraseResource::class,
        ])->pages([...$core->getConfig()->getPages()]);
    }
}
