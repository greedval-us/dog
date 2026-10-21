<?php

namespace App\Modules\Pets\Queries;

use App\Models\PetSaleListing;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PetSaleData;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Database\Eloquent\Builder;

/** @phpstan-import-type Card from PetSaleData */
final class GetPetMarket
{
    public function __construct(private PetDecayCalculator $decay) {}

    /** @return array{listings:list<Card>, ownListings:list<Card>, pagination:array{nextCursor:string|null,previousCursor:string|null}} */
    public function handle(User $viewer, string $locale): array
    {
        $query = PetSaleListing::query()->with(['pet.dog', 'pet.titles', 'user:id,username'])
            ->where('is_active', true)
            ->whereHas('user', fn (Builder $query): Builder => $query->where('status', PlayerStatus::Active))
            ->whereHas('pet', fn (Builder $query): Builder => $query->active());
        $page = (clone $query)->where('user_id', '!=', $viewer->id)->orderBy('id')->cursorPaginate(12);
        $own = $query->where('user_id', $viewer->id)->orderBy('id')->get();
        $cards = function (PetSaleListing $listing) use ($locale): ?array {
            $listing->pet->advanceTo(now(), $this->decay);
            if (! $listing->pet->isActive() || $listing->pet->user_id !== $listing->user_id) {
                return null;
            }

            return PetSaleData::fromModel($listing, $locale);
        };

        return [
            'listings' => array_values(array_filter($page->getCollection()->map($cards)->all())),
            'ownListings' => array_values(array_filter($own->map($cards)->all())),
            'pagination' => ['nextCursor' => $page->nextCursor()?->encode(), 'previousCursor' => $page->previousCursor()?->encode()],
        ];
    }
}
