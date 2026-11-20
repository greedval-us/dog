<?php

namespace App\Modules\Pets\Queries;

use App\Models\PetSaleListing;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PetSaleData;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;

/** @phpstan-import-type Card from PetSaleData */
final class GetPetMarket
{
    public const PAGE_SIZE = 12;

    public function __construct(private PetDecayCalculator $decay) {}

    /** @return array{listings:list<Card>, ownListings:list<Card>, pagination:array{nextCursor:string|null,previousCursor:string|null}} */
    public function handle(User $viewer, string $locale, ?Cursor $cursor = null, ?PetSex $sex = null): array
    {
        $query = $this->baseQuery($sex);
        $page = (clone $query)->where('user_id', '!=', $viewer->id)->orderBy('id')->cursorPaginate(self::PAGE_SIZE, cursor: $cursor);
        $own = $query->where('user_id', $viewer->id)->orderBy('id')->get();
        $cards = fn (PetSaleListing $listing): ?array => $this->project($listing, $locale);

        return [
            'listings' => array_values(array_filter($page->getCollection()->map($cards)->all())),
            'ownListings' => array_values(array_filter($own->map($cards)->all())),
            'pagination' => ['nextCursor' => $page->nextCursor()?->encode(), 'previousCursor' => $page->previousCursor()?->encode()],
        ];
    }

    /** @return Card */
    public function find(User $viewer, int $listingId, string $locale): array
    {
        $listing = $this->baseQuery()->findOrFail($listingId);

        return $this->project($listing, $locale)
            ?? throw (new ModelNotFoundException)->setModel(PetSaleListing::class, [$listingId]);
    }

    /** @return Builder<PetSaleListing> */
    private function baseQuery(?PetSex $sex = null): Builder
    {
        return PetSaleListing::query()->with(['pet.dog', 'pet.titles', 'user:id,username'])
            ->where('is_active', true)
            ->whereHas('user', fn (Builder $query): Builder => $query->where('status', PlayerStatus::Active))
            ->whereHas('pet', fn (Builder $query): Builder => $query->active()->when($sex !== null, fn (Builder $query): Builder => $query->where('sex', $sex)));
    }

    /** @return Card|null */
    private function project(PetSaleListing $listing, string $locale): ?array
    {
        $listing->pet->advanceTo(now(), $this->decay);
        if (! $listing->pet->isActive() || $listing->pet->user_id !== $listing->user_id) {
            return null;
        }

        return PetSaleData::fromModel($listing, $locale);
    }
}
