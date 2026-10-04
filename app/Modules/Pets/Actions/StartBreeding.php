<?php

namespace App\Modules\Pets\Actions;

use App\Models\BreedingListing;
use App\Models\BreedingLitter;
use App\Models\BreedingPartner;
use App\Models\CurrencyTransaction;
use App\Models\Pet;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Exceptions\BreedingUnavailable;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Generators\PuppyGenerator;
use App\Modules\Pets\Queries\BreedingEligibility;
use App\Modules\Pets\Queries\GetInheritedCoatWeights;
use App\Modules\Pets\Services\PetEventReservation;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Calculators\PlayerLevelRules;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Randomizer;

final class StartBreeding
{
    public function __construct(private PetLifecycle $lifecycle, private BreedingEligibility $eligibility, private PuppyGenerator $generator, private PlayerWallet $wallet, private Randomizer $randomizer, private GetInheritedCoatWeights $coatWeights, private PetDecayCalculator $states, private PetEventReservation $reservations) {}

    public function handle(User $user, int $petId, string $kind, int $partnerId, int $expectedPrice, string $token): BreedingLitter
    {
        $token = strtolower($token);
        if (! Str::isUuid($token) || $petId < 1 || $partnerId < 1 || $expectedPrice < 0 || ! in_array($kind, ['listing', 'partner'], true)) {
            throw new BreedingUnavailable('breeding.errors.invalid');
        }
        $candidate = $kind === 'listing' ? BreedingListing::query()->find($partnerId) : BreedingPartner::query()->find($partnerId);
        $ownerIds = array_values(array_unique(array_filter([$user->id, $candidate instanceof BreedingListing ? $candidate->user_id : null])));
        sort($ownerIds);
        foreach (User::query()->whereKey($ownerIds)->orderBy('id')->get() as $owner) {
            $this->lifecycle->synchronizeOwner($owner);
        }
        $rolls = [];

        return DB::transaction(function () use ($user, $petId, $kind, $partnerId, $expectedPrice, $token, $ownerIds, &$rolls): BreedingLitter {
            $owners = User::query()->whereKey($ownerIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $owner = $owners->get($user->id);
            if ($owner === null || $owner->status !== PlayerStatus::Active) {
                throw new BreedingUnavailable('breeding.errors.blocked');
            }
            $existing = BreedingLitter::query()->where('operation_token', $token)->first();
            if ($existing !== null) {
                if ($existing->initiator_id !== $owner->id || $existing->own_pet_id !== $petId || $existing->price !== $expectedPrice
                    || ($kind === 'listing' ? $existing->listing_id !== $partnerId : $existing->partner_id !== $partnerId)) {
                    throw new BreedingUnavailable('breeding.errors.token');
                }

                return $existing;
            }
            if (PlayerLevelRules::progress($owner->experience)['level'] < config('doglive.breeding_minimum_level', 5)) {
                throw new BreedingUnavailable('breeding.errors.level');
            }
            if (CurrencyTransaction::query()->whereIn('user_id', $ownerIds)->where('operation_key', 'breeding:'.$token)->exists()) {
                throw new BreedingUnavailable('breeding.errors.token');
            }
            $offer = $kind === 'listing' ? BreedingListing::query()->find($partnerId) : BreedingPartner::query()->find($partnerId);
            if ($offer === null || ! $offer->is_active || $offer->price !== $expectedPrice) {
                throw new BreedingUnavailable($offer !== null && $offer->price !== $expectedPrice ? 'breeding.errors.price' : 'breeding.errors.unavailable');
            }
            $pets = Pet::query()->whereKey([$petId, $offer->pet_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $own = $pets->get($petId);
            $other = $pets->get($offer->pet_id);
            $offer = $kind === 'listing' ? BreedingListing::query()->lockForUpdate()->find($partnerId) : BreedingPartner::query()->lockForUpdate()->find($partnerId);
            if ($own === null || $other === null || $own->user_id !== $owner->id || $offer === null || ! $offer->is_active || $offer->pet_id !== $other->id) {
                throw new BreedingUnavailable('breeding.errors.unavailable');
            }
            if ($offer->price !== $expectedPrice) {
                throw new BreedingUnavailable('breeding.errors.price');
            }
            $otherOwner = $other->user_id === null ? null : $owners->get($other->user_id);
            if ($offer instanceof BreedingListing && ($offer->user_id !== $other->user_id || $otherOwner === null || $otherOwner->status !== PlayerStatus::Active
                || PlayerLevelRules::progress($otherOwner->experience)['level'] < config('doglive.breeding_minimum_level', 5)
                || $own->sex !== PetSex::Female || $other->sex !== PetSex::Male)) {
                throw new BreedingUnavailable('breeding.errors.unavailable');
            }
            if ($kind === 'partner' && $other->user_id !== null) {
                throw new BreedingUnavailable('breeding.errors.unavailable');
            }
            $at = now()->startOfSecond();
            $own->advanceTo($at, $this->states);
            if ($kind === 'listing') {
                $other->advanceTo($at, $this->states);
            }
            foreach ([[$own, false], [$other, $kind === 'partner']] as [$pet, $system]) {
                if (! $system) {
                    try {
                        $this->reservations->assertAvailable($pet, $at->toImmutable(), $at->toImmutable()->addSecond());
                    } catch (PetUnavailable) {
                        throw new BreedingUnavailable('breeding.errors.unavailable');
                    }
                }
                $reason = $this->eligibility->reason($pet, $at, $system);
                if ($reason !== null) {
                    throw new BreedingUnavailable($reason);
                }
            }
            if ($own->dog_id !== $other->dog_id || $own->sex === $other->sex || $own->id === $other->id || ! $own->is_purebred || ! $other->is_purebred) {
                throw new BreedingUnavailable('breeding.errors.incompatible');
            }
            if ($this->eligibility->related($own, $other)) {
                throw new BreedingUnavailable('breeding.errors.related');
            }
            $father = $own->sex === PetSex::Male ? $own : $other;
            $mother = $own->sex === PetSex::Female ? $own : $other;
            $weights = $this->coatWeights->handle($father, $mother);
            if ($weights === [] || array_sum($weights) < 1) {
                throw new BreedingUnavailable('breeding.errors.unavailable');
            }
            $fatherStats = $this->stats($father);
            $motherStats = $this->stats($mother);
            $rollKey = hash('sha256', serialize([$fatherStats, $motherStats, $weights, $father->exterior, $mother->exterior]));
            if (! isset($rolls[$rollKey])) {
                $puppies = $this->generator->generate($fatherStats, $motherStats, $weights, $father->exterior, $mother->exterior);
                $rolls[$rollKey] = ['puppies' => $puppies, 'sirePuppyIndex' => $this->randomizer->getInt(0, count($puppies) - 1)];
            }
            $puppies = $rolls[$rollKey]['puppies'];
            $sirePuppyIndex = $rolls[$rollKey]['sirePuppyIndex'];
            if ($expectedPrice > 0 && $other->user_id !== $owner->id) {
                try {
                    $this->wallet->change($owner, 'coins', -$expectedPrice, 'breeding:'.$token, 'breeding_payment');
                    if ($otherOwner !== null) {
                        $this->wallet->change($otherOwner, 'coins', $expectedPrice, 'breeding:'.$token, 'breeding_income');
                    }
                } catch (InsufficientFunds) {
                    throw new BreedingUnavailable('breeding.errors.funds');
                }
            }
            $bornAt = $at->addHours(config('doglive.breeding_birth_hours', 24));
            $expiresAt = $bornAt->addDays(config('doglive.puppy_decision_days', 7));
            $litter = BreedingLitter::query()->create([
                'operation_token' => $token, 'initiator_id' => $owner->id, 'own_pet_id' => $own->id,
                'listing_id' => $kind === 'listing' ? $offer->id : null, 'partner_id' => $kind === 'partner' ? $offer->id : null,
                'father_id' => $father->id, 'mother_id' => $mother->id, 'price' => $expectedPrice,
                'snapshots' => ['father' => $fatherStats, 'mother' => $motherStats, 'colors' => $weights, 'sirePuppyIndex' => $sirePuppyIndex, 'exterior' => ['father' => $father->exterior, 'mother' => $mother->exterior]],
                'born_at' => $bornAt, 'expires_at' => $expiresAt,
            ]);
            foreach ($puppies as $index => $puppy) {
                $caps = [];
                foreach ($puppy['potentials'] as $stat => $potential) {
                    $caps[$stat.'_potential'] = $potential;
                }
                Puppy::query()->create([
                    'litter_id' => $litter->id, 'dog_id' => $own->dog_id, 'father_id' => $father->id, 'mother_id' => $mother->id,
                    'user_id' => $index === $sirePuppyIndex ? $father->user_id : $mother->user_id,
                    'status' => 'unborn', 'name' => '№'.($index + 1), 'sex' => $puppy['sex'], 'coat_color' => $puppy['coat_color'],
                    'generation' => max($father->generation, $mother->generation) + 1, 'exterior' => $puppy['exterior'], 'expires_at' => $expiresAt, ...$caps,
                ]);
            }
            $own->breeding_available_at = $at->addDays(config('doglive.breeding_cooldown_days', 7));
            $own->save();
            if ($kind === 'listing') {
                $other->breeding_available_at = $own->breeding_available_at;
                $other->save();
            }

            return $litter;
        }, attempts: 3);
    }

    /** @return array<string, array{value:int,potential:int}> */
    private function stats(Pet $pet): array
    {
        $stats = [];
        foreach (PetStat::cases() as $stat) {
            $stats[$stat->value] = ['value' => (int) $pet->getAttribute($stat->value), 'potential' => (int) $pet->getAttribute($stat->potentialColumn())];
        }

        return $stats;
    }
}
