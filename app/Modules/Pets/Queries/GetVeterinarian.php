<?php

namespace App\Modules\Pets\Queries;

use App\Models\Pet;
use App\Models\PetDisease;
use App\Models\User;
use App\Models\VeterinaryVisit;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\VeterinaryRules;
use App\Modules\Pets\Enums\VeterinaryService;
use Illuminate\Support\Str;

final class GetVeterinarian
{
    public function __construct(
        private PetDecayCalculator $decay,
        private GetVeterinaryServices $catalogue,
        private GetLastVeterinaryVisit $lastVisit,
        private VeterinaryRules $rules,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $user, ?int $petId, string $locale): array
    {
        $pets = $user->pets()->orderBy('id')->get();
        $pet = $petId === null ? ($pets->first(fn (Pet $dog): bool => $dog->retired_at === null) ?? $pets->first()) : $pets->firstWhere('id', $petId);
        if ($petId !== null && $pet === null) {
            $user->pets()->findOrFail($petId);
        }
        $at = now();
        $pet?->advanceTo($at, $this->decay);
        $reason = $this->rules->petUnavailableReason($user->status, $pet !== null, $pet?->retired_at !== null, $pet?->isBusy() ?? false);
        $definitions = $this->catalogue->handle();
        $services = [];
        foreach ($definitions as $definition) {
            $last = $pet === null ? null : $this->lastVisit->handle($pet->id, $definition->service);
            $active = $last?->available_at?->greaterThan($at) ?? false;
            $unavailable = $reason ?? $this->rules->serviceUnavailableReason($definition, $user->coins, $last?->available_at?->getTimestamp(), $at->getTimestamp());
            $services[] = ['code' => $definition->service->value, 'price' => $definition->price, 'reason' => $unavailable,
                'lastVisitAt' => $last?->performed_at->toIso8601String(),
                'availableAt' => $active ? $last->available_at->toIso8601String() : null];
        }

        return [
            'token' => (string) Str::uuid(), 'selectedPetId' => $pet?->id,
            'dogs' => $pets->map(fn (Pet $dog): array => ['id' => $dog->id, 'name' => $dog->name])->all(),
            'health' => $pet?->statePercentages()['health'], 'reason' => $reason,
            'checkupDays' => $definitions[VeterinaryService::Checkup->value]->intervalDays,
            'checkupHealth' => $definitions[VeterinaryService::Checkup->value]->healthRestorePercent,
            'vaccinationDays' => $definitions[VeterinaryService::Vaccination->value]->intervalDays,
            'vaccinationBonus' => $definitions[VeterinaryService::Vaccination->value]->healthRecoveryBonus,
            'services' => $services,
            'diseases' => $pet === null ? [] : $pet->activeDiseaseEpisodes()->with('disease')->orderBy('id')->get()
                ->map(fn (PetDisease $episode): array => [
                    'id' => $episode->id,
                    'name' => $episode->effect_snapshot['name'][$locale] ?? $episode->disease->name[$locale] ?? $episode->disease->name['en'] ?? $episode->disease->code,
                    'startedAt' => $episode->started_at->toIso8601String(),
                ])->all(),
            'history' => $pet === null ? [] : VeterinaryVisit::query()->where('user_id', $user->id)->where('pet_id', $pet->id)
                ->orderByDesc('id')->limit(5)->get()->map(fn (VeterinaryVisit $visit): array => [
                    'id' => $visit->id, 'service' => $visit->service->value, 'price' => $visit->price_paid,
                    'diseaseName' => $visit->disease_name[$locale] ?? $visit->disease_name['en'] ?? null,
                    'performedAt' => $visit->performed_at->toIso8601String(),
                ])->all(),
        ];
    }
}
