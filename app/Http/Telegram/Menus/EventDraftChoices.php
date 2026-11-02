<?php

namespace App\Http\Telegram\Menus;

use App\Models\GameEvent;
use App\Models\User;
use App\Modules\Pets\DTO\EventRegulationData;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetGameEvents;

/** @phpstan-import-type Command from MenuView */
final class EventDraftChoices
{
    private const CLASSIFICATION_KEYS = ['exhibition_class', 'sport_class', 'program', 'test_type', 'handler_category'];

    public function __construct(private GetGameEvents $events) {}

    /**
     * @param  array<string,mixed>  $dog
     * @return array<string,mixed>
     */
    public function initialPlan(GameEvent $event, array $dog): array
    {
        $plan = ['stages' => array_fill(0, count($event->rules['stages']), 'balanced')];
        if ($event->discipline === 'progeny') {
            $plan['offspring_ids'] = [];
        } elseif (EventRegulationData::isGroupRing($event)) {
            $plan['member_ids'] = [];
        } else {
            $option = collect($this->options($event, $dog))->firstWhere('eligible', true);
            foreach (self::CLASSIFICATION_KEYS as $key) {
                if (isset($option['plan'][$key])) {
                    $plan[$key] = $option['plan'][$key];
                }
            }
        }

        return $plan;
    }

    /**
     * @param  array<string,mixed>  $dog
     * @param  list<array<string,mixed>>  $equipment
     * @param  array<string,mixed>  $values
     * @param  Command  $command
     * @return array<string,mixed>
     */
    public function select(GameEvent $event, array $dog, array $equipment, array $values, array $command): array
    {
        switch ($command['action']) {
            case 'events.draft.option':
                $option = collect($this->options($event, $dog))->firstWhere('key', $command['optionKey'] ?? null);
                if ($option === null || $option['eligible'] !== true) {
                    throw new PetUnavailable('telegram.errors.stale');
                }
                $plan = $values['plan'];
                foreach (self::CLASSIFICATION_KEYS as $key) {
                    if (isset($option['plan'][$key])) {
                        $plan[$key] = $option['plan'][$key];
                    } else {
                        unset($plan[$key]);
                    }
                }
                $values['plan'] = $plan;
                break;
            case 'events.draft.member':
                if (! $this->hasMembers($event)) {
                    throw new PetUnavailable('telegram.errors.stale');
                }
                $key = $this->memberKey($event);
                $id = (int) ($command['memberId'] ?? 0);
                $ids = $values['plan'][$key] ?? [];
                $member = collect($this->members($event, $dog))->firstWhere('id', $id);
                if (in_array($id, $ids, true)) {
                    $values['plan'][$key] = array_values(array_diff($ids, [$id]));
                } elseif ($member !== null && ($member['eligible'] ?? true) === true && count($ids) < $this->memberLimits($event)[1]) {
                    $values['plan'][$key][] = $id;
                } else {
                    throw new PetUnavailable('telegram.errors.stale');
                }
                break;
            case 'events.draft.stage':
                $index = $command['stageIndex'] ?? null;
                $decision = $command['decision'] ?? null;
                if ($this->hasMembers($event) || ! is_int($index) || ! array_key_exists($index, $values['plan']['stages'])
                    || ! in_array($decision, ['careful', 'balanced', 'bold'], true)) {
                    throw new PetUnavailable('telegram.errors.stale');
                }
                $values['plan']['stages'][$index] = $decision;
                break;
            case 'events.draft.gear':
                $id = (int) ($command['gearId'] ?? 0);
                $ids = $values['gearIds'];
                if ($this->hasMembers($event)) {
                    throw new PetUnavailable('telegram.errors.stale');
                }
                if (in_array($id, $ids, true)) {
                    $values['gearIds'] = array_values(array_diff($ids, [$id]));
                    break;
                }
                $item = collect($equipment)->firstWhere('id', $id);
                if ($item === null) {
                    throw new PetUnavailable('telegram.errors.stale');
                }
                $sameSlot = collect($equipment)->where('slot', $item['slot'])->pluck('id')->all();
                $ids = array_values(array_diff($ids, $sameSlot));
                if (count($ids) >= 4) {
                    throw new PetUnavailable('telegram.event.gear_limit');
                }
                $values['gearIds'] = [...$ids, $id];
                break;
            default:
                throw new PetUnavailable('telegram.errors.stale');
        }

        return $values;
    }

    /**
     * @param  array<string,mixed>  $dog
     * @return list<array<string,mixed>>
     */
    public function options(GameEvent $event, array $dog): array
    {
        if (EventRegulationData::isRegulated($event)) {
            return $dog['entryOptions'] ?? [];
        }

        return $event->discipline !== 'conformation' ? [] : array_values(array_map(static fn (array $class): array => [
            ...$class, 'eligible' => true, 'plan' => ['exhibition_class' => $class['key']],
        ], $dog['availableClasses']));
    }

    /**
     * @param  array<string,mixed>  $option
     * @param  array<string,mixed>  $plan
     */
    public function matchesOption(array $option, array $plan): bool
    {
        foreach (self::CLASSIFICATION_KEYS as $key) {
            if (($option['plan'][$key] ?? null) !== ($plan[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string,mixed>  $dog
     * @return list<array<string,mixed>>
     */
    public function members(GameEvent $event, array $dog): array
    {
        return $dog[$event->discipline === 'progeny' ? 'offspring' : 'groupMembers'] ?? [];
    }

    /**
     * @param  array<string,mixed>  $dog
     * @return list<array<string,mixed>>
     */
    public function equipment(User $user, GameEvent $event, array $dog, string $locale): array
    {
        if ($this->hasMembers($event)) {
            return [];
        }

        return array_values(array_filter($this->events->registrationEquipment($user, $locale), static fn (array $item): bool => in_array($event->discipline, $item['disciplines'], true) && in_array($dog['size'], $item['sizes'], true)
            && (! EventRegulationData::isRegulated($event) || $event->discipline !== 'agility' || $item['phase'] === 'preparation')
            && ($event->discipline !== 'canicross' || ! in_array($item['slot'], ['body', 'line', 'handler'], true) || $item['phase'] === 'performance')));
    }

    /**
     * @param  array<string,mixed>  $dog
     * @return list<string>
     */
    public function dogReasons(GameEvent $event, array $dog): array
    {
        $reasons = $dog['preparation']['blockingReasons'] ?? [];
        $eligibility = $dog[$event->discipline === 'progeny' ? 'progenyEligibility' : 'groupEligibility'] ?? null;
        if (is_array($eligibility) && ($eligibility['eligible'] ?? true) === false && is_string($eligibility['reason'] ?? null)) {
            $reasons[] = $eligibility['reason'];
        }

        return $reasons;
    }

    /** @return list<string> */
    public function steps(GameEvent $event): array
    {
        if ($this->hasMembers($event)) {
            return ['members', 'review'];
        }

        return [...(EventRegulationData::isRegulated($event) || $event->discipline === 'conformation' ? ['option'] : []), 'stages', 'gear', 'review'];
    }

    public function hasMembers(GameEvent $event): bool
    {
        return $event->discipline === 'progeny' || EventRegulationData::isGroupRing($event);
    }

    public function memberKey(GameEvent $event): string
    {
        return $event->discipline === 'progeny' ? 'offspring_ids' : 'member_ids';
    }

    /** @return array{int,int} */
    public function memberLimits(GameEvent $event): array
    {
        return match ($event->discipline) {
            'couple' => [1, 1], 'breeders' => [2, 4], default => [3, 5],
        };
    }

    public function isOpen(GameEvent $event): bool
    {
        return $event->status === 'registration' && now()->greaterThanOrEqualTo($event->registration_opens_at) && now()->lessThan($event->closes_at);
    }
}
