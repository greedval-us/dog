<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Kennel\Enums\KennelPetAge;
use App\Modules\Pets\Exceptions\PetUnavailable;

/** @phpstan-type Draft array{userId:int,dogId:int,ageMonths:int,mode:string,expectedPrice:int,token:string,name:string|null,awaitingName:bool,nameRequestedAt:int} */
final readonly class SavedKennelDraft
{
    /** @var Draft */
    public array $data;

    /** @param array<string,mixed> $state */
    public function __construct(User $user, array $state)
    {
        $saved = new SavedMenuState($state, 'kennel');
        $mode = $saved->string('mode');
        $price = $saved->integer('expectedPrice', minimum: 0);
        $months = $saved->integer('ageMonths');
        $name = $saved->nullableString('name');
        $awaiting = $state['kennel']['awaitingName'] ?? null;
        if ($saved->integer('userId') !== $user->id || ! in_array($mode, ['starter', 'purchase'], true)
            || ($mode === 'starter' ? $price !== 0 : $price < 1) || ! is_bool($awaiting)
            || KennelPetAge::tryFrom((string) $months) === null || ($name !== null && ! self::validName($name))) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $this->data = ['userId' => $user->id, 'dogId' => $saved->integer('dogId'), 'ageMonths' => $months,
            'mode' => $mode, 'expectedPrice' => $price, 'token' => $saved->uuid(), 'name' => $name,
            'awaitingName' => $awaiting, 'nameRequestedAt' => $saved->integer('nameRequestedAt')];
    }

    public static function validName(string $name): bool
    {
        return trim($name) !== '' && mb_strlen($name) <= 64 && ! preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $name);
    }
}
