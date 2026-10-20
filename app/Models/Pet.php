<?php

namespace App\Models;

use App\Modules\Pets\Calculators\PetAgeRules;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\PetEventProfileRules;
use App\Modules\Pets\Calculators\StatePercentageCalculator;
use App\Modules\Pets\Enums\DogSize;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Enums\PetState;
use Carbon\CarbonImmutable;
use Database\Factories\PetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @phpstan-import-type Effect from \App\Modules\Pets\Calculators\PetStatusRules
 * @phpstan-import-type BoardingPeriod from \App\Modules\Pets\Calculators\PetBoardingRules
 *
 * @property list<Effect>|null $buffs
 * @property list<Effect>|null $debuffs
 * @property list<BoardingPeriod>|null $boarding_periods
 * @property int $id
 * @property int|null $user_id
 * @property int $dog_id
 * @property int|null $portrait_asset_id
 * @property int|null $background_asset_id
 * @property int|null $father_id
 * @property int|null $mother_id
 * @property string $name
 * @property PetSex $sex
 * @property string $coat_color
 * @property DogSize $size
 * @property string|null $description
 * @property bool $is_purebred
 * @property bool $is_favorite
 * @property Collection<int, CharacterTrait> $characterTraits
 * @property Collection<int, Skill> $skills
 * @property Collection<int, PetDisease> $diseaseEpisodes
 * @property Collection<int, PetDisease> $activeDiseaseEpisodes
 * @property int $generation
 * @property array{type: int, structure: int, movement: int} $exterior
 * @property array<string,mixed>|null $event_profile
 * @property Collection<int, PetTitle> $titles
 * @property Collection<int, PetSportRecord> $sportRecords
 * @property CarbonImmutable $born_at
 * @property CarbonImmutable|null $retired_at
 * @property CarbonImmutable|null $died_at
 * @property CarbonImmutable|null $surrendered_at
 * @property int|null $lifespan_months
 * @property CarbonImmutable $state_updated_at
 * @property array<string, float>|null $stat_decay_remainders
 * @property CarbonImmutable $stats_updated_at
 * @property CarbonImmutable|null $last_activity_at
 * @property CarbonImmutable|null $breeding_available_at
 * @property PetActivity|null $activity
 * @property string|null $activity_token
 * @property CarbonImmutable|null $activity_started_at
 * @property CarbonImmutable|null $activity_ends_at
 * @property Dog $dog
 * @property User|null $user
 * @property Pet|null $father
 * @property Pet|null $mother
 * @property int $endurance
 * @property int $endurance_potential
 * @property int $speed
 * @property int $speed_potential
 * @property int $strength
 * @property int $strength_potential
 * @property int $agility
 * @property int $agility_potential
 * @property int $obedience
 * @property int $obedience_potential
 * @property int $intelligence
 * @property int $intelligence_potential
 * @property float $health
 * @property int $health_max
 * @property float $energy
 * @property int $energy_max
 * @property float $satiety
 * @property int $satiety_max
 * @property float $hydration
 * @property int $hydration_max
 * @property float $mood
 * @property int $mood_max
 * @property float $cleanliness
 * @property int $cleanliness_max
 * @property float $bond
 * @property int $bond_max
 */
#[Fillable(['user_id', 'dog_id', 'father_id', 'mother_id', 'name', 'sex', 'coat_color', 'description', 'size', 'born_at', 'generation', 'exterior', 'is_purebred', 'is_favorite', 'retired_at', 'activity', 'activity_started_at', 'activity_ends_at', 'activity_token', 'last_activity_at', 'state_updated_at', 'stats_updated_at', 'stat_decay_remainders', 'endurance', 'endurance_potential', 'speed', 'speed_potential', 'strength', 'strength_potential', 'agility', 'agility_potential', 'obedience', 'obedience_potential', 'intelligence', 'intelligence_potential', 'health', 'health_max', 'energy', 'energy_max', 'satiety', 'satiety_max', 'hydration', 'hydration_max', 'mood', 'mood_max', 'cleanliness', 'cleanliness_max', 'bond', 'bond_max', 'food_per_day', 'water_per_day', 'buffs', 'debuffs'])]
class Pet extends Model
{
    /** @use HasFactory<PetFactory> */
    use HasFactory;

    protected $attributes = ['is_purebred' => true];

    protected static function booted(): void
    {
        static::creating(function (self $pet): void {
            $identity = implode(':', [$pet->dog_id, $pet->born_at->getTimestamp(), $pet->name, $pet->sex->value]);
            $pet->event_profile = (new PetEventProfileRules)->initialSnapshot($identity, $pet->sex->value, $pet->is_purebred, $pet->dog->eventProfileDefaults(), $pet->event_profile ?? []);
            if ($pet->lifespan_months === null) {
                $pet->lifespan_months = (new PetAgeRules)->lifespanMonths(
                    $identity,
                    $pet->size->value,
                    $pet->dog->breed,
                    config('pet_age'),
                );
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Dog, $this> */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    /** @return BelongsTo<GameAsset, $this> */
    public function portraitAsset(): BelongsTo
    {
        return $this->belongsTo(GameAsset::class, 'portrait_asset_id');
    }

    /** @return BelongsTo<GameAsset, $this> */
    public function backgroundAsset(): BelongsTo
    {
        return $this->belongsTo(GameAsset::class, 'background_asset_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function father(): BelongsTo
    {
        return $this->belongsTo(self::class, 'father_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function mother(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mother_id');
    }

    /** @return HasMany<Pet, $this> */
    public function paternalOffspring(): HasMany
    {
        return $this->hasMany(self::class, 'father_id');
    }

    /** @return HasMany<Pet, $this> */
    public function maternalOffspring(): HasMany
    {
        return $this->hasMany(self::class, 'mother_id');
    }

    /** @return HasMany<PetTitle, $this> */
    public function titles(): HasMany
    {
        return $this->hasMany(PetTitle::class)->orderByDesc('awarded_at')->orderByDesc('id');
    }

    /** @return HasMany<PetSportRecord, $this> */
    public function sportRecords(): HasMany
    {
        return $this->hasMany(PetSportRecord::class);
    }

    /** @return BelongsToMany<CharacterTrait, $this> */
    public function characterTraits(): BelongsToMany
    {
        return $this->belongsToMany(CharacterTrait::class)->withTimestamps()->orderByPivot('id');
    }

    /** @return BelongsToMany<Skill, $this, PetSkill> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->using(PetSkill::class)
            ->withPivot(['level', 'experience', 'last_trained_at', 'cooldown_until'])->withTimestamps();
    }

    /** @return HasMany<PetDisease, $this> */
    public function diseaseEpisodes(): HasMany
    {
        return $this->hasMany(PetDisease::class);
    }

    /** @return HasMany<PetDisease, $this> */
    public function activeDiseaseEpisodes(): HasMany
    {
        return $this->diseaseEpisodes()->active();
    }

    public function isBusy(): bool
    {
        return $this->activity !== null;
    }

    public function isActive(): bool
    {
        return $this->surrendered_at === null && $this->died_at === null && $this->health > 0;
    }

    public function archivedAt(): ?CarbonImmutable
    {
        return $this->died_at ?? $this->surrendered_at;
    }

    public function retirementEligibleAt(): CarbonImmutable
    {
        return $this->born_at;
    }

    public function automaticRetirementAt(): ?CarbonImmutable
    {
        return null;
    }

    public function sportRetirementAt(): ?CarbonImmutable
    {
        return null;
    }

    public function naturalDeathAt(): CarbonImmutable
    {
        return $this->born_at->addSeconds(($this->lifespan_months ?? PetAgeRules::DEFAULT_LIFESPAN_MONTHS) * PetAgeRules::SECONDS_PER_MONTH);
    }

    public function ageMonthsAt(CarbonImmutable $at): int
    {
        return (new PetAgeRules)->monthsAt($this->born_at, $at);
    }

    public function canRetire(CarbonImmutable $at): bool
    {
        return $this->isActive() && $this->retired_at === null && $at->greaterThanOrEqualTo($this->retirementEligibleAt());
    }

    public function clearActivity(): void
    {
        $this->forceFill(['activity' => null, 'activity_token' => null, 'activity_started_at' => null, 'activity_ends_at' => null]);
    }

    /** @param Builder<Pet> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('surrendered_at')->whereNull('died_at')->where('health', '>', 0);
    }

    /** @param Builder<Pet> $query */
    #[Scope]
    protected function availableForActivity(Builder $query): void
    {
        $query->active()->whereNull('activity');
    }

    /**
     * Advance the in-memory snapshot; callers persist it only inside a locked transaction.
     *
     * @phpstan-impure
     */
    public function advanceTo(CarbonImmutable $at, PetDecayCalculator $calculator): void
    {
        $requestedAt = $at->startOfSecond();

        if ($this->archivedAt() !== null) {
            return;
        }

        $naturalDeathAt = $this->naturalDeathAt()->max($this->state_updated_at)->max($this->stats_updated_at)->startOfSecond();
        $at = $requestedAt->min($naturalDeathAt);

        $values = [];
        $maximums = [];

        foreach (PetState::cases() as $state) {
            $values[$state->value] = (float) $this->getAttribute($state->value);
            $maximums[$state->value] = (int) $this->getAttribute($state->maximumColumn());
        }

        $effects = [...($this->buffs ?? []), ...($this->debuffs ?? [])];
        $age = ['born_at' => $this->born_at->getTimestamp(), 'lifespan_months' => $this->lifespan_months ?? PetAgeRules::DEFAULT_LIFESPAN_MONTHS];
        $diedAt = $this->health <= 0 ? $this->state_updated_at->min($at) : null;
        if ($at->greaterThan($this->state_updated_at)) {
            $result = $calculator->statesUntilDeath($values, $maximums, $this->state_updated_at->getTimestamp(), $at->getTimestamp(), $effects, $this->boarding_periods ?? [], $age);
            $this->fill($result['values']);
            if ($result['diedAt'] !== null) {
                $diedAt = CarbonImmutable::createFromTimestampUTC($result['diedAt']);
                $at = $diedAt;
            }
            $this->state_updated_at = $at;
        }
        $at = $diedAt ?? $at;
        if ($at->greaterThan($this->stats_updated_at)) {
            $stats = [];
            foreach (PetStat::cases() as $stat) {
                $stats[$stat->value] = (int) $this->getAttribute($stat->value);
            }
            $result = $calculator->stats($stats, $this->stat_decay_remainders ?? [], $this->stats_updated_at->getTimestamp(), $at->getTimestamp(), $effects, $this->boarding_periods ?? [], $age);
            $this->fill($result['values']);
            $this->stat_decay_remainders = $result['remainders'];
            $this->stats_updated_at = $at;
        }
        if ($this->boarding_periods !== null) {
            $consumedThrough = min($this->state_updated_at->getTimestamp(), $this->stats_updated_at->getTimestamp());
            $this->boarding_periods = array_values(array_filter($this->boarding_periods,
                fn (array $period): bool => $period['ends_at'] > $consumedThrough));
        }

        if ($diedAt === null && $requestedAt->greaterThanOrEqualTo($naturalDeathAt)) {
            $diedAt = $naturalDeathAt;
        }
        if ($diedAt !== null) {
            $this->died_at = $diedAt;
            $this->health = 0;
            $this->clearActivity();
        }
    }

    /** @return array<string, float> */
    public function statePercentages(?int $precision = 1): array
    {
        $percentages = [];
        $calculator = new StatePercentageCalculator;

        foreach (PetState::cases() as $state) {
            $percentages[$state->value] = $calculator->calculate(
                $this->getAttribute($state->value),
                $this->getAttribute($state->maximumColumn()),
                $precision,
            );
        }

        return $percentages;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'buffs' => 'array',
            'debuffs' => 'array',
            'boarding_periods' => 'array',
            'sex' => PetSex::class,
            'size' => DogSize::class,
            'born_at' => 'datetime',
            'retired_at' => 'datetime',
            'died_at' => 'datetime',
            'surrendered_at' => 'datetime',
            'lifespan_months' => 'integer',
            'state_updated_at' => 'datetime',
            'stats_updated_at' => 'datetime',
            'stat_decay_remainders' => 'array',
            'last_activity_at' => 'datetime',
            'breeding_available_at' => 'immutable_datetime',
            'activity' => PetActivity::class,
            'activity_started_at' => 'datetime',
            'activity_ends_at' => 'datetime',
            'portrait_asset_id' => 'integer',
            'background_asset_id' => 'integer',
            'generation' => 'integer',
            'exterior' => 'array',
            'event_profile' => 'array',
            'is_purebred' => 'boolean',
            'is_favorite' => 'boolean',
            'endurance' => 'integer',
            'endurance_potential' => 'integer',
            'speed' => 'integer',
            'speed_potential' => 'integer',
            'strength' => 'integer',
            'strength_potential' => 'integer',
            'agility' => 'integer',
            'agility_potential' => 'integer',
            'obedience' => 'integer',
            'obedience_potential' => 'integer',
            'intelligence' => 'integer',
            'intelligence_potential' => 'integer',
            'health' => 'float',
            'health_max' => 'integer',
            'energy' => 'float',
            'energy_max' => 'integer',
            'satiety' => 'float',
            'satiety_max' => 'integer',
            'hydration' => 'float',
            'hydration_max' => 'integer',
            'mood' => 'float',
            'mood_max' => 'integer',
            'cleanliness' => 'float',
            'cleanliness_max' => 'integer',
            'bond' => 'float',
            'bond_max' => 'integer',
            'food_per_day' => 'integer',
            'water_per_day' => 'integer',
        ];
    }
}
