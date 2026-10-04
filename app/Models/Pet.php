<?php

namespace App\Models;

use App\Modules\Pets\Calculators\PetDecayCalculator;
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
 *
 * @property list<Effect>|null $buffs
 * @property list<Effect>|null $debuffs
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
 * @property Collection<int, PetTitle> $titles
 * @property Collection<int, PetSportRecord> $sportRecords
 * @property CarbonImmutable $born_at
 * @property CarbonImmutable|null $retired_at
 * @property CarbonImmutable|null $died_at
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
        return $this->retired_at === null && $this->died_at === null && $this->health > 0;
    }

    public function archivedAt(): ?CarbonImmutable
    {
        return $this->died_at ?? $this->retired_at;
    }

    public function retirementEligibleAt(): CarbonImmutable
    {
        return $this->born_at->addMonthsNoOverflow(3);
    }

    public function automaticRetirementAt(): CarbonImmutable
    {
        return $this->born_at->addMonthsNoOverflow(6);
    }

    public function canRetire(CarbonImmutable $at): bool
    {
        return $this->isActive() && $at->greaterThanOrEqualTo($this->retirementEligibleAt());
    }

    public function clearActivity(): void
    {
        $this->forceFill(['activity' => null, 'activity_token' => null, 'activity_started_at' => null, 'activity_ends_at' => null]);
    }

    /** @param Builder<Pet> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('retired_at')->whereNull('died_at')->where('health', '>', 0);
    }

    /** @param Builder<Pet> $query */
    #[Scope]
    protected function availableForActivity(Builder $query): void
    {
        $query->active()->whereNull('activity');
    }

    /** Advance the in-memory snapshot; callers persist it only inside a locked transaction. */
    public function advanceTo(CarbonImmutable $at, PetDecayCalculator $calculator): void
    {
        $requestedAt = $at->startOfSecond();

        if ($this->archivedAt() !== null) {
            return;
        }

        $automaticRetirementAt = $this->automaticRetirementAt()->startOfSecond();
        $at = $requestedAt->min($automaticRetirementAt);

        $values = [];
        $maximums = [];

        foreach (PetState::cases() as $state) {
            $values[$state->value] = (float) $this->getAttribute($state->value);
            $maximums[$state->value] = (int) $this->getAttribute($state->maximumColumn());
        }

        $effects = [...($this->buffs ?? []), ...($this->debuffs ?? [])];
        $diedAt = $this->health <= 0 ? $this->state_updated_at->min($at) : null;
        if ($at->greaterThan($this->state_updated_at)) {
            $result = $calculator->statesUntilDeath($values, $maximums, $this->state_updated_at->getTimestamp(), $at->getTimestamp(), $effects);
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
            $result = $calculator->stats($stats, $this->stat_decay_remainders ?? [], $this->stats_updated_at->getTimestamp(), $at->getTimestamp(), $effects);
            $this->fill($result['values']);
            $this->stat_decay_remainders = $result['remainders'];
            $this->stats_updated_at = $at;
        }

        if ($diedAt !== null) {
            $this->died_at = $diedAt;
            $this->health = 0;
            $this->clearActivity();
        } elseif ($requestedAt->greaterThanOrEqualTo($automaticRetirementAt)) {
            $this->retired_at = $automaticRetirementAt;
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
            'sex' => PetSex::class,
            'size' => DogSize::class,
            'born_at' => 'datetime',
            'retired_at' => 'datetime',
            'died_at' => 'datetime',
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
