<?php

namespace App\Modules\Pets\Queries;

use App\Models\BreedingListing;
use App\Models\BreedingPartner;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\BreedingGeneticsCalculator;
use App\Modules\Pets\Calculators\PetAgeRules;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PetTitleData;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Players\Calculators\PlayerLevelRules;
use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

final class GetBreedingBoard
{
    public function __construct(private BreedingEligibility $eligibility, private BreedingGeneticsCalculator $genetics, private GetInheritedCoatWeights $coatWeights, private PetDecayCalculator $states) {}

    /** @return array<string,mixed> */
    public function handle(User $user, string $locale, ?int $petId = null, ?string $kind = null, ?int $partnerId = null): array
    {
        $at = CarbonImmutable::now()->startOfSecond();
        $level = PlayerLevelRules::progress($user->experience)['level'];
        $dogs = $user->pets()->active()->with(['dog', 'titles'])->withExists('activeDiseaseEpisodes')->orderBy('id')->get();
        foreach ($dogs as $dog) {
            $dog->advanceTo($at, $this->states);
        }
        $selected = $dogs->firstWhere('id', $petId);
        $listingQuery = $this->activeListingsQuery();
        $partnerQuery = BreedingPartner::query()->where('is_active', true)->with(['pet' => fn (Relation $pets) => $pets->withExists('activeDiseaseEpisodes'), 'pet.dog', 'pet.titles'])->orderBy('id');
        if ($selected !== null) {
            $compatiblePartner = fn (Builder $query) => $query->where('dog_id', $selected->dog_id)->where('sex', '!=', $selected->sex->value);
            $listingQuery->whereHas('pet', $compatiblePartner);
            $partnerQuery->whereHas('pet', $compatiblePartner);
        }
        $listingPage = $listingQuery->cursorPaginate(12);
        $listings = $listingPage->getCollection()->filter(fn (BreedingListing $listing): bool => $this->listingIsEligible($listing))->values();
        if ($kind === 'listing' && $partnerId !== null && ! $listings->contains('id', $partnerId)) {
            $chosenListing = $this->activeListingsQuery()->find($partnerId);
            if ($chosenListing !== null && $this->listingIsEligible($chosenListing)) {
                $listings->push($chosenListing);
            }
        }
        foreach ($listings as $listing) {
            $listing->pet->advanceTo($at, $this->states);
        }
        $partners = $partnerQuery->get();
        $parents = $dogs->concat($listings->pluck('pet'))->concat($partners->pluck('pet'));
        $petIds = array_values($parents->filter(fn (Pet $pet): bool => $pet->user_id !== null)
            ->map(fn (Pet $pet): int => $pet->id)->unique()->all());
        $reservedPetIds = $this->reservedPetIds($petIds, $at);
        $parentReasons = [];
        foreach ($parents as $parent) {
            $parentReasons[$parent->id] = $this->eligibility->reason($parent, $at, $parent->user_id === null)
                ?? (isset($reservedPetIds[$parent->id]) ? 'breeding.errors.unavailable' : null);
        }
        $card = fn (Pet $pet): array => $this->parent($pet, $locale, $parentReasons[$pet->id] ?? null);
        $chosen = $kind === 'listing' ? $listings->firstWhere('id', $partnerId) : ($kind === 'partner' ? $partners->firstWhere('id', $partnerId) : null);
        $ownPair = $chosen !== null && $chosen->pet->user_id === $user->id;
        $preview = null;
        if ($selected !== null && $chosen !== null) {
            $other = $chosen->pet;
            $reason = $parentReasons[$selected->id] ?? $parentReasons[$other->id] ?? null;
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
                $ranges[] = [
                    'stat' => $stat->value,
                    'min' => $this->genetics->offspringPotential($first['min'], $second['min'], BreedingGeneticsCalculator::MIN_OFFSPRING_SPREAD),
                    'max' => $this->genetics->offspringPotential($first['max'], $second['max'], BreedingGeneticsCalculator::MAX_OFFSPRING_SPREAD),
                ];
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
            $preview = ['father' => $card($father), 'mother' => $card($mother), 'ranges' => $ranges, 'colors' => $colors, 'reason' => $reason, 'ownPair' => $ownPair];
        }

        return [
            'serverNow' => now()->toIso8601String(),
            'cooldownSeconds' => config('doglive.breeding_cooldown_seconds'),
            'pregnancySeconds' => config('doglive.breeding_birth_seconds'),
            'breedingMinimumMonths' => PetAgeRules::BREEDING_MINIMUM_MONTHS,
            'breedingMaximumMonths' => PetAgeRules::BREEDING_MAXIMUM_MONTHS,
            'access' => ['level' => $level, 'requiredLevel' => config('doglive.breeding_minimum_level', 5), 'allowed' => $level >= config('doglive.breeding_minimum_level', 5) && $user->status === PlayerStatus::Active],
            'dogs' => $dogs->map($card)->all(),
            'ownListings' => BreedingListing::query()->where('user_id', $user->id)->orderBy('id')->get()->map(fn (BreedingListing $listing): array => ['id' => $listing->id, 'petId' => $listing->pet_id, 'price' => $listing->price, 'isActive' => $listing->is_active])->all(),
            'listingPagination' => ['nextCursor' => $listingPage->nextCursor()?->encode(), 'previousCursor' => $listingPage->previousCursor()?->encode()],
            'listings' => $listings->map(fn (BreedingListing $listing): array => ['id' => $listing->id, 'price' => $listing->price, 'owner' => ['username' => $listing->user->username], 'pet' => $card($listing->pet)])->values()->all(),
            'partners' => $partners->map(fn (BreedingPartner $partner): array => ['id' => $partner->id, 'price' => $partner->price, 'pet' => $card($partner->pet)])->all(),
            'selection' => ['petId' => $selected?->id, 'kind' => $chosen === null ? null : $kind, 'partnerId' => $chosen?->id],
            'ownPair' => $ownPair,
            'selectedPrice' => $chosen === null || $ownPair ? 0 : $chosen->price,
            'preview' => $preview, 'operationToken' => (string) Str::uuid(),
        ];
    }

    /** @return Builder<BreedingListing> */
    private function activeListingsQuery(): Builder
    {
        return BreedingListing::query()->where('is_active', true)
            ->whereHas('user', fn (Builder $query) => $query->where('status', PlayerStatus::Active))
            ->with(['user', 'pet' => fn (Relation $pets) => $pets->withExists('activeDiseaseEpisodes'), 'pet.dog', 'pet.titles'])->orderBy('id');
    }

    private function listingIsEligible(BreedingListing $listing): bool
    {
        return $listing->pet->user_id === $listing->user_id
            && PlayerLevelRules::progress($listing->user->experience)['level'] >= config('doglive.breeding_minimum_level', 5);
    }

    /** @return array<string,mixed> */
    private function parent(Pet $pet, string $locale, ?string $reason): array
    {
        $stats = [];
        foreach (PetStat::cases() as $stat) {
            $stats[$stat->value] = ['value' => (int) $pet->getAttribute($stat->value), 'potential' => (int) $pet->getAttribute($stat->potentialColumn())];
        }

        return ['id' => $pet->id, 'name' => $pet->name, 'sex' => $pet->sex->value, 'breed' => $pet->dog->breed, 'breedName' => $pet->dog->localizedName($locale), 'coatColor' => $pet->coat_color, 'coatColorLabel' => $pet->dog->coat_colors[$pet->coat_color][$locale] ?? $pet->coat_color, 'generation' => $pet->generation, 'stats' => $stats, 'exterior' => $pet->exterior, 'titles' => PetTitleData::fromPet($pet, $locale), 'reason' => $reason, 'cooldownUntil' => $pet->breeding_available_at?->toIso8601String()];
    }

    /**
     * @param  list<int>  $petIds
     * @return array<int, true>
     */
    private function reservedPetIds(array $petIds, CarbonImmutable $at): array
    {
        if ($petIds === []) {
            return [];
        }

        $reserved = GameEventEntry::query()->whereIn('pet_id', $petIds)->whereIn('status', ['registered', 'frozen'])
            ->whereHas('event', fn (Builder $events) => $events->whereIn('status', ['registration', 'frozen'])
                ->where('discipline', '!=', GameEventDiscipline::Progeny->value)
                ->where('closes_at', '<=', $at->addSecond())->where('ends_at', '>', $at))
            ->distinct()->pluck('pet_id')->all();

        return array_fill_keys($reserved, true);
    }
}
