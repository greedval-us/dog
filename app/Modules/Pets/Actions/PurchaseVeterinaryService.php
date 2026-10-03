<?php

namespace App\Modules\Pets\Actions;

use App\Models\CurrencyTransaction;
use App\Models\User;
use App\Models\VeterinaryVisit;
use App\Modules\Pets\Calculators\VeterinaryRules;
use App\Modules\Pets\DTO\PurchaseVeterinaryServiceData;
use App\Modules\Pets\Enums\VeterinaryService;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetLastVeterinaryVisit;
use App\Modules\Pets\Queries\GetVeterinaryServices;
use App\Modules\Pets\Services\VeterinaryCare;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
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
    ) {}

    public function handle(User $user, PurchaseVeterinaryServiceData $data): VeterinaryVisit
    {
        $token = strtolower($data->token);
        $treatment = $data->service === VeterinaryService::Treatment;
        if (! Str::isUuid($token) || $data->petId < 1 || $data->expectedPrice < 1
            || ($treatment ? ($data->diseaseEpisodeId ?? 0) < 1 : $data->diseaseEpisodeId !== null)) {
            throw new PetUnavailable('Invalid veterinary visit.');
        }

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
            $reason = $this->rules->petUnavailableReason($owner->status, true, $pet->retired_at !== null, $pet->isBusy());
            if ($reason !== null) {
                throw new PetUnavailable($reason);
            }

            $definition = $this->services->forService($data->service);
            if (! $definition->validPrice || $definition->price !== $data->expectedPrice) {
                throw new PetUnavailable('The price has changed. Refresh the page before purchasing.');
            }
            $at = now()->startOfSecond();
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

            $this->care->apply($pet, $definition, $episode, $at, $availableAt);
            $pet->save();

            return VeterinaryVisit::query()->create([
                'user_id' => $owner->id, 'pet_id' => $pet->id, 'pet_name' => $pet->name,
                'service' => $data->service, 'disease_episode_id' => $episode?->id, 'disease_name' => $episode?->disease->name,
                'token' => $token, 'price_paid' => $definition->price, 'currency_transaction_id' => $entry->id,
                'performed_at' => $at, 'available_at' => $availableAt,
            ]);
        }, attempts: 3);
    }
}
