<?php

namespace App\Modules\Pets\DTO;

use App\Modules\Pets\Enums\PetActivity;

/**
 * @phpstan-import-type Risk from \App\Modules\Pets\Calculators\ItemEffectRules
 *
 * @phpstan-type CareOptionView array{group: string, label: string, duration: int, cooldown: int, energy: int, requirements: list<string>, optional: list<string>, uses: array<string, int>, effects: array<string, int>, statGains?: array<string, int>, trainingName?: array<string, string>, risks?: list<Risk>}
 */
final readonly class CareOption
{
    /**
     * @param  list<string>  $requirements
     * @param  list<string>  $optional
     * @param  array<string, int>  $uses
     * @param  array<string, int>  $effects
     * @param  array<string, int>|null  $statGains
     * @param  array<string, string>|null  $trainingName
     * @param  list<Risk>  $risks
     */
    public function __construct(
        public PetActivity $group,
        public string $label,
        public int $duration,
        public int $cooldown,
        public int $energy,
        public array $requirements,
        public array $optional,
        public array $uses,
        public array $effects,
        public ?array $statGains = null,
        public ?array $trainingName = null,
        public array $risks = [],
    ) {}

    public function withEnergy(int $energy): self
    {
        return new self($this->group, $this->label, $this->duration, $this->cooldown, $energy,
            $this->requirements, $this->optional, $this->uses, $this->effects,
            $this->statGains, $this->trainingName, $this->risks);
    }

    /** @return CareOptionView */
    public function toArray(): array
    {
        $data = [
            'group' => $this->group->value, 'label' => $this->label,
            'duration' => $this->duration, 'cooldown' => $this->cooldown, 'energy' => $this->energy,
            'requirements' => $this->requirements, 'optional' => $this->optional,
            'uses' => $this->uses, 'effects' => $this->effects,
        ];
        if ($this->statGains !== null) {
            $data['statGains'] = $this->statGains;
        }
        if ($this->trainingName !== null) {
            $data['trainingName'] = $this->trainingName;
        }
        if ($this->statGains !== null || $this->risks !== []) {
            $data['risks'] = $this->risks;
        }

        return $data;
    }
}
