<?php

namespace App\Http\Telegram;

use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Exceptions\PetUnavailable;

final class ApplyTelegramPetSale
{
    public function __construct(private PublishPetSale $sales, private TelegramMutationReceipt $receipts) {}

    public function handle(User $user, int $petId, ?int $price, string $token): string
    {
        $receipt = $this->receipts->handle(
            $user,
            'marketMutation',
            $token,
            [$petId, $price],
            function (User $owner) use ($petId, $price): array {
                $pet = $owner->pets()->findOrFail($petId);
                if ($price === null) {
                    $this->sales->withdraw($owner, $petId);
                } else {
                    $this->sales->handle($owner, $petId, $price);
                }

                return ['name' => $pet->name];
            },
        );
        if (! is_string($receipt['name'] ?? null)) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return $receipt['name'];
    }
}
