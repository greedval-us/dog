<?php

namespace App\Modules\Pets\DTO;

/**
 * @phpstan-import-type Plan from GameEventProtocol
 * @phpstan-import-type Gear from GameEventProtocol
 */
final readonly class GameEventAdmissionData
{
    /**
     * @param  Plan  $plan
     * @param  list<Gear>  $gear
     */
    public function __construct(public array $plan, public array $gear) {}
}
