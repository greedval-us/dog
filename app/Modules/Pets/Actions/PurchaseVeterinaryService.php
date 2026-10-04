<?php

namespace App\Modules\Pets\Actions;

use App\Models\CurrencyTransaction;
use App\Models\User;
use App\Models\VeterinaryVisit;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\VeterinaryRules;
use App\Modules\Pets\DTO\PetHistoryChange;
use App\Modules\Pets\DTO\PurchaseVeterinaryServiceData;
use App\Modules\Pets\Enums\VeterinaryService;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetLastVeterinaryVisit;
use App\Modules\Pets\Queries\GetVeterinaryServices;
use App\Modules\Pets\Services\PetHistoryRecorder;
use App\Modules\Pets\Services\PetLifecycleSynchronization;
use App\Modules\Pets\Services\VeterinaryCare;
use App\Modules\Players\DTO\PlayerProgressFact;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerProgress;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PurchaseVeterinaryService
{
    public function __construct(
        private PlayerWallet $wallet,
        private GetVeterinaryServices $services,
        private GetLastVeterinaryVisit $lastVisit,
        private VeterinaryRules $rules,
        private VeterinaryCare $care,
        private PetDecayCalculator $decay,
        private PetHistoryRecorder $history,
        private PlayerProgress $progress,
        private PetLifecycleSynchronization $lifecycle,
    ) {}

    public function handle(User $user, PurchaseVeterinaryServiceData $data): VeterinaryVisit
    {
        $token = strtolower($data->token);
        $treatment = $data->service === VeterinaryService::Treatment;
        if (! Str::isUuid($token) || $data->petId < 1 || $data->expectedPrice < 1
            || ($treatment ? ($data->diseaseEpisodeId ?? 0) < 1 : $data->diseaseEpisodeId !== null)) {
            throw new PetUnavailable('Invalid veterinary visit.');
        }
        $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $data, $token, $treatment): VeterinaryVisit {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }

            $existing = VeterinaryVisit::query()->where('user_id', $owner->id)->where('token', $token)->first();
            if ($existing !== null) {
                if ($existing->pet_id !== $data->petId || $existing->service !== $data->service
                    || $existing->disease_episode_id !== $data->diseaseEpisodeId || $existing->price_paid !== $data->expectedPrice) {
                    throw new PetUnavailable('This token was already used for a different veterinary visit.');
                }

                return $existing;
            }
            $operationKey = 'veterinarian:'.$token;
            if (CurrencyTransaction::query()->whereBelongsTo($owner)->whereRaw('LOWER(operation_key) = ?', [$operationKey])->exists()) {
                throw new PetUnavailable('This visit was already paid for, but its receipt is unavailable. Refresh the page.');
            }

            $pet = $owner->pets()->lockForUpdate()->findOrFail($data->petId);
            if (! $pet->isActive() && $pet->retired_at === null) {
                throw new PetUnavailable('This dog is no longer active.');
            }
            $reason = $this->rules->petUnavailableReason($owner->status, true, $pet->retired_at !== null, $pet->isBusy());
            if ($reason !== null) {
                throw new PetUnavailable($reason);
            }

            $definition = $this->services->forService($data->service);
            if (! $definition->validPrice || $definition->price !== $data->expectedPrice) {
                throw new PetUnavailable('The price has changed. Refresh the page before purchasing.');
            }
            $at = now()->startOfSecond();
            $pet->advanceTo($at, $this->decay);
            if (! $pet->isActive()) {
                throw new PetUnavailable('This dog is no longer active.');
            }
            $episode = null;
            $availableAt = null;
            if ($treatment) {
                $episode = $pet->diseaseEpisodes()->with('disease')->lockForUpdate()->find($data->diseaseEpisodeId);
                if ($episode === null || $episode->ended_at !== null) {
                    throw new PetUnavailable('This disease is no longer awaiting treatment. Refresh the page.');
                }
            } else {
                $last = $this->lastVisit->handle($pet->id, $data->service);
                $reason = $this->rules->cooldownReason($data->service, $last?->available_at?->getTimestamp(), $at->getTimestamp());
                if ($reason !== null) {
                    throw new PetUnavailable($reason);
                }
                $availableAt = $at->addDays(max(1, $definition->intervalDays));
            }

            try {
                $entry = $this->wallet->change($owner, 'coins', -$definition->price, $operationKey, 'veterinarian_'.$data->service->value);
            } catch (InsufficientFunds) {
                throw new PetUnavailable('You do not have enough coins for this veterinary service.');
            }

            $healthBefore = $pet->health / $pet->health_max * 100;
            $this->care->apply($pet, $definition, $episode, $at, $availableAt);
            $healthAfter = $pet->health / $pet->health_max * 100;
            $pet->save();

            $visit = VeterinaryVisit::query()->create([
                'user_id' => $owner->id, 'pet_id' => $pet->id, 'pet_name' => $pet->name,
                'service' => $data->service, 'disease_episode_id' => $episode?->id, 'disease_name' => $episode?->disease->name,
                'token' => $token, 'price_paid' => $definition->price, 'currency_transaction_id' => $entry->id,
                'performed_at' => $at, 'available_at' => $availableAt,
            ]);
            $experienceAwarded = $this->progress->award($owner, $visit, fn (VeterinaryVisit $completed): PlayerProgressFact => new PlayerProgressFact(
                code: 'veterinary.'.$completed->service->value, completedAt: $completed->performed_at,
            ));
            $this->history->record($pet, 'veterinary.'.$data->service->value, 'veterinary:'.$visit->id.':completed', $at, [
                'stage' => 'completed', 'diseaseName' => $visit->disease_name, 'durationSeconds' => 0, 'experienceAwarded' => $experienceAwarded,
                'coins' => -$visit->price_paid,
                'changes' => $healthAfter === $healthBefore ? [] : [PetHistoryChange::percent('health', $healthBefore, $healthAfter)->toArray()],
            ]);

            return $visit;
        }, attempts: 3);
    }
}
