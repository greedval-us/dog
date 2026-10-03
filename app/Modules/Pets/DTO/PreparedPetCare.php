<?php

namespace App\Modules\Pets\DTO;

/**
 * @phpstan-import-type Risk from \App\Modules\Pets\Calculators\ItemEffectRules
 * @phpstan-import-type Effect from \App\Modules\Pets\Calculators\PetStatusRules
 *
 * @phpstan-type SelectedItem array{category: string, id: int, name: array<string, string>, uses: int}
 */
final readonly class PreparedPetCare
{
    /**
     * @param  array<string, int|float>  $effects
     * @param  list<Effect>  $grantedEffects
     * @param  list<Risk>  $incidents
     * @param  array<string, int>  $statusRecovery
     * @param  array<string, int>  $qualities
     * @param  list<SelectedItem>  $items
     * @param  array<string, int>|null  $statGains
     */
    public function __construct(
        public CareOption $option,
        public array $effects,
        public array $grantedEffects,
        public array $incidents,
        public array $statusRecovery,
        public array $qualities,
        public array $items,
        public ?array $statGains = null,
    ) {}

    /** @param array<string, int> $statGains */
    public function withStatGains(array $statGains): self
    {
        return new self($this->option, $this->effects, $this->grantedEffects, $this->incidents,
            $this->statusRecovery, $this->qualities, $this->items, $statGains);
    }
}
