<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Exceptions\PetUnavailable;

/** @phpstan-type Draft array{userId:int,petId:int,price:int|null,token:string,awaitingPrice:bool,priceRequestedAt:int} */
final readonly class SavedPetSaleDraft
{
    /** @var Draft */
    public array $data;

    /** @param array<string,mixed> $state */
    public function __construct(User $user, array $state)
    {
        $saved = new SavedMenuState($state, 'marketSale');
        $price = $saved->nullableInteger('price');
        $awaiting = $state['marketSale']['awaitingPrice'] ?? null;
        if ($saved->integer('userId') !== $user->id || ! $saved->has('price')
            || ! is_bool($awaiting) || ($price !== null && $price > PublishPetSale::MAXIMUM_PRICE)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $this->data = ['userId' => $user->id, 'petId' => $saved->integer('petId'), 'price' => $price,
            'token' => $saved->uuid(), 'awaitingPrice' => $awaiting, 'priceRequestedAt' => $saved->integer('priceRequestedAt')];
    }
}
