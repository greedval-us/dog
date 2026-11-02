<?php

namespace App\Http\Telegram;

use App\Models\User;
use App\Modules\Kennel\Actions\AdoptStarterPet;
use App\Modules\Kennel\DTO\AdoptStarterPetData;
use App\Modules\Pets\Exceptions\PetUnavailable;

final class AdoptTelegramPet
{
    public function __construct(private AdoptStarterPet $adopt, private TelegramMutationReceipt $receipts) {}

    public function handle(User $user, AdoptStarterPetData $data, string $token): int
    {
        $receipt = $this->receipts->handle(
            $user,
            'kennelAdoption',
            $token,
            [$data->dogId, $data->name, $data->ageMonths],
            fn (User $owner): array => ['petId' => $this->adopt->handle($owner, $data)->id],
        );
        if (! is_int($receipt['petId'] ?? null)) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return $receipt['petId'];
    }
}
