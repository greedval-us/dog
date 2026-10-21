<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\Enums\ExhibitionClass;
use App\Modules\Pets\Enums\GameEventDiscipline;
use DateTimeInterface;

final class GameEventAdmissionRules
{
    /**
     * @param  array{active:bool, retired:bool, ageMonths:int, retiresAt?:DateTimeInterface|null, health:float, hasDisease:bool, energyPercentage:float, energy:float, busy:bool, activityEndsAt:DateTimeInterface|null}  $pet
     * @param  array{startsAt:DateTimeInterface, closesAt:DateTimeInterface, energyCost:float, minimumMonths?:int}  $event
     * @return list<string>
     */
    public function blockingReasons(GameEventDiscipline $discipline, array $pet, array $event): array
    {
        $reasons = [];
        $minimumAge = $event['minimumMonths'] ?? ($discipline->isExhibition() ? ExhibitionClass::MINIMUM_MONTHS : PetAgeRules::SPORT_MINIMUM_MONTHS);
        if (! $pet['active'] || $pet['retired']) {
            $reasons[] = 'events.errors.archived';
        }
        if ($pet['ageMonths'] < $minimumAge) {
            $reasons[] = 'events.errors.young';
        }
        if ($discipline->isDocumentary()) {
            return $reasons;
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
