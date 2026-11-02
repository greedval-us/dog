<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\DTO\GameEventProtocol;
use App\Modules\Pets\Enums\ExhibitionClass;
use App\Modules\Pets\Exceptions\PetUnavailable;

/**
 * @phpstan-import-type Plan from GameEventProtocol
 *
 * @phpstan-type Draft array{userId:int,eventId:int,petId:int,entryId:int|null,fee:int,token:string,plan:Plan,gearIds:list<int>}
 */
final readonly class SavedEventDraft
{
    public int $userId;

    public int $eventId;

    public int $petId;

    public ?int $entryId;

    public int $fee;

    public string $token;

    /** @var Plan */
    public array $plan;

    /** @var list<int> */
    public array $gearIds;

    /** @param array<string, mixed> $state */
    public function __construct(User $user, array $state)
    {
        $values = $state['eventDraft'] ?? null;
        if (! is_array($values)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $saved = new SavedMenuState($state, 'eventDraft');
        $this->userId = $saved->integer('userId');
        $this->eventId = $saved->integer('eventId');
        $this->petId = $saved->integer('petId');
        $this->entryId = $saved->nullableInteger('entryId');
        $this->fee = $saved->integer('fee');
        $this->token = $saved->uuid();
        if ($this->userId !== $user->id || ! $saved->has('entryId')) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $plan = $values['plan'] ?? null;
        $gearIds = $values['gearIds'] ?? null;
        if (! is_array($plan) || array_diff(array_keys($plan), ['stages', 'offspring_ids', 'member_ids', 'exhibition_class', 'sport_class', 'program', 'test_type', 'handler_category']) !== []) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $stages = $plan['stages'] ?? null;
        if (! is_array($stages) || ! array_is_list($stages) || count($stages) < 1 || count($stages) > 6) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        foreach ($stages as $decision) {
            if (! in_array($decision, ['careful', 'balanced', 'bold'], true)) {
                throw new PetUnavailable('telegram.errors.stale');
            }
        }
        foreach (['exhibition_class', 'sport_class', 'program', 'test_type', 'handler_category'] as $key) {
            if (array_key_exists($key, $plan) && (! is_string($plan[$key]) || $plan[$key] === '' || mb_strlen($plan[$key]) > 40)) {
                throw new PetUnavailable('telegram.errors.stale');
            }
        }
        if (isset($plan['exhibition_class']) && (! is_string($plan['exhibition_class']) || ExhibitionClass::tryFrom($plan['exhibition_class']) === null)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        if (isset($plan['program']) && ! in_array($plan['program'], ['pr1', 'pr2'], true)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        if (isset($plan['test_type']) && ! in_array($plan['test_type'], ['birch', 'lavender', 'anise', 'cinnamon', 'orange', 'clove'], true)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        foreach (['offspring_ids' => 5, 'member_ids' => 4] as $key => $maximum) {
            if (array_key_exists($key, $plan)) {
                $this->validateIds($plan[$key], $maximum);
            }
        }
        $this->validateIds($gearIds, 4);
        /** @var Plan $plan */
        $this->plan = $plan;
        /** @var list<int> $gearIds */
        $this->gearIds = $gearIds;
    }

    /** @return Draft */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId, 'eventId' => $this->eventId, 'petId' => $this->petId, 'entryId' => $this->entryId,
            'fee' => $this->fee, 'token' => $this->token, 'plan' => $this->plan, 'gearIds' => $this->gearIds,
        ];
    }

    private function validateIds(mixed $values, int $maximum): void
    {
        if (! is_array($values) || ! array_is_list($values) || count($values) > $maximum) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        foreach ($values as $id) {
            if (! is_int($id) || $id < 1) {
                throw new PetUnavailable('telegram.errors.stale');
            }
        }
        if (count(array_unique($values)) !== count($values)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
    }
}
