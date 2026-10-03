<?php

namespace App\Modules\Pets\Queries;

use App\Models\BreedingListing;
use App\Models\BreedingPartner;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\BreedingGeneticsCalculator;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Players\Calculators\PlayerLevelRules;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Str;

final class GetBreedingBoard
{
    public function __construct(private BreedingEligibility $eligibility, private BreedingGeneticsCalculator $genetics, private GetInheritedCoatWeights $coatWeights, private PetDecayCalculator $states) {}

    /** @return array<string,mixed> */
    public function handle(User $user, string $locale, ?int $petId = null, ?string $kind = null, ?int $partnerId = null): array
    {
        $level = PlayerLevelRules::progress($user->experience)['level'];
        $dogs = $user->pets()->active()->with('dog')->orderBy('id')->get();
        foreach ($dogs as $dog) {
            $dog->advanceTo(now(), $this->states);
        }
        $selected = $dogs->firstWhere('id', $petId);
        $listingQuery = BreedingListing::query()->where('is_active', true)->whereHas('user', fn ($query) => $query->where('status', PlayerStatus::Active))->with(['user', 'pet.dog'])->orderBy('id');
        $partnerQuery = BreedingPartner::query()->where('is_active', true)->with('pet.dog')->orderBy('id');
        if ($selected !== null) {
            $listingQuery->whereHas('pet', fn ($query) => $query->where('dog_id', $selected->dog_id)->where('sex', '!=', $selected->sex->value));
            $partnerQuery->whereHas('pet', fn ($query) => $query->where('dog_id', $selected->dog_id)->where('sex', '!=', $selected->sex->value));
        }
        $listingPage = $listingQuery->cursorPaginate(12);
        $listings = $listingPage->getCollection()->filter(fn (BreedingListing $listing): bool => $listing->pet->user_id === $listing->user_id && PlayerLevelRules::progress($listing->user->experience)['level'] >= config('doglive.breeding_minimum_level', 5))->values();
        if ($kind === 'listing' && $partnerId !== null && ! $listings->contains('id', $partnerId)) {
            $chosenListing = BreedingListing::query()->where('is_active', true)->whereHas('user', fn ($query) => $query->where('status', PlayerStatus::Active))->with(['user', 'pet.dog'])->find($partnerId);
            if ($chosenListing !== null && $chosenListing->pet->user_id === $chosenListing->user_id && PlayerLevelRules::progress($chosenListing->user->experience)['level'] >= config('doglive.breeding_minimum_level', 5)) {
                $listings->push($chosenListing);
            }
        }
        foreach ($listings as $listing) {
            $listing->pet->advanceTo(now(), $this->states);
        }
        $partners = $partnerQuery->get();
        $chosen = $kind === 'listing' ? $listings->firstWhere('id', $partnerId) : ($kind === 'partner' ? $partners->firstWhere('id', $partnerId) : null);
        $preview = null;
        if ($selected !== null && $chosen !== null) {
            $other = $chosen->pet;
            $reason = $this->eligibility->reason($selected, now()) ?? $this->eligibility->reason($other, now(), $kind === 'partner');
            if ($selected->dog_id !== $other->dog_id || $selected->sex === $other->sex || ! $selected->is_purebred || ! $other->is_purebred) {
                $reason = 'breeding.errors.incompatible';
            } elseif ($this->eligibility->related($selected, $other)) {
                $reason = 'breeding.errors.related';
            }
            $father = $selected->sex === PetSex::Male ? $selected : $other;
            $mother = $selected->sex === PetSex::Female ? $selected : $other;
            $ranges = [];
            foreach (PetStat::cases() as $stat) {
                $first = $this->genetics->range((int) $father->getAttribute($stat->value), (int) $father->getAttribute($stat->potentialColumn()));
                $second = $this->genetics->range((int) $mother->getAttribute($stat->value), (int) $mother->getAttribute($stat->potentialColumn()));
                $ranges[] = ['stat' => $stat->value, 'min' => max(1, min(2147483647, (int) round(($first['min'] + $second['min']) / 2 * 0.98))), 'max' => max(1, min(2147483647, (int) round(($first['max'] + $second['max']) / 2 * 1.02)))];
            }
            $weights = $this->coatWeights->handle($father, $mother);
            $total = array_sum($weights);
            $colors = [];
            foreach ($weights as $code => $weight) {
                $colors[] = ['code' => $code, 'label' => $selected->dog->coat_colors[$code][$locale] ?? $code, 'chance' => $weight / $total * 100, 'rare' => in_array($code, config('doglive.breeding_rare_colors', []), true)];
            }
            if ($colors === []) {
                $reason = 'breeding.errors.unavailable';
            }
            $preview = ['father' => $this->parent($father, $locale, $father->user_id === null), 'mother' => $this->parent($mother, $locale, $mother->user_id === null), 'ranges' => $ranges, 'colors' => $colors, 'reason' => $reason, 'ownPair' => $other->user_id === $user->id];
        }

        return [
            'access' => ['level' => $level, 'requiredLevel' => config('doglive.breeding_minimum_level', 5), 'allowed' => $level >= config('doglive.breeding_minimum_level', 5) && $user->status === PlayerStatus::Active],
            'dogs' => $dogs->map(fn (Pet $pet): array => $this->parent($pet, $locale))->all(),
            'ownListings' => BreedingListing::query()->where('user_id', $user->id)->orderBy('id')->get()->map(fn (BreedingListing $listing): array => ['id' => $listing->id, 'petId' => $listing->pet_id, 'price' => $listing->price, 'isActive' => $listing->is_active])->all(),
            'listingPagination' => ['nextCursor' => $listingPage->nextCursor()?->encode(), 'previousCursor' => $listingPage->previousCursor()?->encode()],
            'listings' => $listings->filter(fn (BreedingListing $listing): bool => $listing->pet->user_id === $listing->user_id && PlayerLevelRules::progress($listing->user->experience)['level'] >= config('doglive.breeding_minimum_level', 5))->map(fn (BreedingListing $listing): array => ['id' => $listing->id, 'price' => $listing->price, 'owner' => ['username' => $listing->user->username], 'pet' => $this->parent($listing->pet, $locale)])->values()->all(),
            'partners' => $partners->map(fn (BreedingPartner $partner): array => ['id' => $partner->id, 'price' => $partner->price, 'pet' => $this->parent($partner->pet, $locale, true)])->all(),
            'selection' => ['petId' => $selected?->id, 'kind' => $chosen === null ? null : $kind, 'partnerId' => $chosen?->id],
            'ownPair' => $chosen !== null && $chosen->pet->user_id === $user->id,
            'selectedPrice' => $chosen === null || $chosen->pet->user_id === $user->id ? 0 : $chosen->price,
            'preview' => $preview, 'operationToken' => (string) Str::uuid(),
        ];
    }

    /** @return array<string,mixed> */
    private function parent(Pet $pet, string $locale, bool $system = false): array
    {
        $stats = [];
        foreach (PetStat::cases() as $stat) {
            $stats[$stat->value] = ['value' => (int) $pet->getAttribute($stat->value), 'potential' => (int) $pet->getAttribute($stat->potentialColumn())];
        }

        return ['id' => $pet->id, 'name' => $pet->name, 'sex' => $pet->sex->value, 'breed' => $pet->dog->breed, 'breedName' => $pet->dog->localizedName($locale), 'coatColor' => $pet->coat_color, 'coatColorLabel' => $pet->dog->coat_colors[$pet->coat_color][$locale] ?? $pet->coat_color, 'generation' => $pet->generation, 'stats' => $stats, 'reason' => $this->eligibility->reason($pet, now(), $system), 'cooldownUntil' => $pet->breeding_available_at?->toIso8601String()];
    }
}
