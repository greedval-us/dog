<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\Enums\GameEventDiscipline;
use DateTimeInterface;

final class GameEventAdmissionRules
{
    /**
     * @param  array{active:bool, retiresAt:DateTimeInterface, health:float, hasDisease:bool, energyPercentage:float, energy:float, busy:bool, activityEndsAt:DateTimeInterface|null}  $pet
     * @param  array{endsAt:DateTimeInterface, closesAt:DateTimeInterface, energyCost:float}  $event
     * @return list<string>
     */
    public function blockingReasons(GameEventDiscipline $discipline, array $pet, array $event): array
    {
        if ($discipline->isDocumentary()) {
            return [];
        }

        $reasons = [];
        if (! $pet['active'] || $pet['retiresAt'] <= $event['endsAt']) {
            $reasons[] = 'events.errors.archived';
        }
        if ($pet['health'] < 60 || $pet['hasDisease']) {
            $reasons[] = 'events.errors.health';
        }
        if ($pet['energyPercentage'] < 35 || $pet['energy'] < $event['energyCost']) {
            $reasons[] = 'events.errors.energy';
        }
        if ($pet['busy'] && ($pet['activityEndsAt'] === null || $pet['activityEndsAt'] > $event['closesAt'])) {
            $reasons[] = 'events.errors.busy';
        }

        return $reasons;
    }
}
