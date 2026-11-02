<?php

namespace App\Http\Telegram\Menus;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Support\Str;

final class EventDraftView
{
    public function __construct(private MenuView $view, private EventDraftChoices $choices) {}

    /**
     * @param  list<array<string,mixed>>  $dogs
     */
    public function dogs(User $user, GameEvent $event, array $dogs, string $locale, int $offset): TelegramScreen
    {
        $lines = [$this->discipline($event, $locale), $this->view->translate('event.choose_dog', $locale)];
        $rows = [];
        if (! $this->choices->isOpen($event)) {
            $lines[] = $this->view->translate('event.registration_closed', $locale);
        } else {
            foreach (array_slice($dogs, $offset, MenuView::PAGE_SIZE) as $dog) {
                $name = Str::limit((string) $dog['name'], 60);
                $reasons = $this->choices->dogReasons($event, $dog);
                $lines[] = $name.($reasons === [] ? '' : ' · '.Str::limit($reasons[0], 200));
                $rows[] = [$this->view->commandButton($name, ['action' => 'events.prepare', 'eventId' => $event->id, 'petId' => $dog['id']])];
            }
            $rows = [...$rows, ...$this->view->offsetNavigation(count($dogs), $offset, ['action' => 'events.prepare', 'eventId' => $event->id], $locale)];
            if ($dogs === []) {
                $lines[] = $this->view->translate('event.no_dogs', $locale);
            }
        }

        return $this->view->screen(implode("\n", $lines), $rows, $locale, ['action' => 'events.show', 'eventId' => $event->id]);
    }

    /**
     * @param  array<string,mixed>  $dog
     * @param  list<array<string,mixed>>  $equipment
     * @param  array<string,mixed>  $values
     */
    public function render(User $user, GameEvent $event, array $dog, array $equipment, array $values, string $step, int $offset, string $locale, ?GameEventEntry $entry): TelegramScreen
    {
        $steps = $this->choices->steps($event);
        $position = array_search($step, $steps, true);
        $lines = [$this->discipline($event, $locale), $this->view->translate('event.dog', $locale).': '.Str::limit((string) $dog['name'], 80)];
        $rows = [];
        switch ($step) {
            case 'option':
                $lines[] = $this->view->translate('event.choose_option', $locale);
                $options = $this->choices->options($event, $dog);
                foreach (array_slice($options, $offset, MenuView::PAGE_SIZE) as $option) {
                    $selected = $this->choices->matchesOption($option, $values['plan']);
                    $label = ($selected ? '✓ ' : '').Str::limit((string) $option['label'], 70);
                    $lines[] = $label.($option['eligible'] ? '' : ' · '.Str::limit((string) $option['reason'], 170));
                    if ($option['eligible']) {
                        $rows[] = [$this->view->commandButton($label, ['action' => 'events.draft.option', 'optionKey' => $option['key'], 'offset' => $offset])];
                    }
                }
                $rows = [...$rows, ...$this->view->offsetNavigation(count($options), $offset, ['action' => 'events.draft.show', 'step' => $step], $locale)];
                if ($options === []) {
                    $lines[] = $this->view->translate('event.no_options', $locale);
                }
                break;
            case 'members':
                $members = $this->choices->members($event, $dog);
                $ids = $values['plan'][$this->choices->memberKey($event)] ?? [];
                [$minimum, $maximum] = $this->choices->memberLimits($event);
                $lines[] = $this->view->translate('event.choose_members', $locale, ['minimum' => $minimum, 'maximum' => $maximum]);
                $lines[] = $this->view->translate('event.selected_members', $locale, ['count' => count($ids)]);
                foreach (array_slice($members, $offset, MenuView::PAGE_SIZE) as $member) {
                    $selected = in_array($member['id'], $ids, true);
                    $label = ($selected ? '✓ ' : '').Str::limit((string) $member['name'], 60);
                    $lines[] = $label.(isset($member['ownerName']) ? ' · '.Str::limit((string) $member['ownerName'], 45) : '')
                        .(empty($member['reason']) ? '' : ' · '.Str::limit((string) $member['reason'], 150));
                    if ($selected || ($member['eligible'] ?? true) === true) {
                        $rows[] = [$this->view->commandButton($label, ['action' => 'events.draft.member', 'memberId' => $member['id'], 'offset' => $offset])];
                    }
                }
                $rows = [...$rows, ...$this->view->offsetNavigation(count($members), $offset, ['action' => 'events.draft.show', 'step' => $step], $locale)];
                if ($members === []) {
                    $lines[] = $this->view->translate('event.no_members', $locale);
                }
                break;
            case 'stages':
                $lines[] = $this->view->translate('event.choose_stages', $locale);
                foreach ($event->rules['stages'] as $index => $stage) {
                    $lines[] = ($index + 1).'. '.$this->stageLabel($stage, $locale).': '.$this->view->translate('event.decisions.'.$values['plan']['stages'][$index], $locale);
                    $decisions = [];
                    foreach (['careful', 'balanced', 'bold'] as $decision) {
                        $decisions[] = $this->view->commandButton(($values['plan']['stages'][$index] === $decision ? '✓ ' : '').($index + 1).' '.$this->view->translate('event.decisions.'.$decision, $locale),
                            ['action' => 'events.draft.stage', 'stageIndex' => $index, 'decision' => $decision]);
                    }
                    $rows[] = $decisions;
                }
                break;
            case 'gear':
                $lines[] = $this->view->translate('event.choose_gear', $locale);
                if ($event->discipline === 'canicross') {
                    $lines[] = $this->view->translate('event.required_kit', $locale);
                }
                $choices = $equipment;
                foreach (array_diff($values['gearIds'], array_column($equipment, 'id')) as $id) {
                    $choices[] = ['id' => $id, 'name' => $this->view->translate('event.remove_gear', $locale, ['id' => $id]), 'slot' => null];
                }
                foreach (array_slice($choices, $offset, MenuView::PAGE_SIZE) as $item) {
                    $label = (in_array($item['id'], $values['gearIds'], true) ? '✓ ' : '').Str::limit((string) $item['name'], 60);
                    $lines[] = $label.($item['slot'] === null ? '' : ' · '.$this->view->translate('event.slots.'.$item['slot'], $locale).' · '.$item['remainingUses']);
                    $rows[] = [$this->view->commandButton($label, ['action' => 'events.draft.gear', 'gearId' => $item['id'], 'offset' => $offset])];
                }
                $rows = [...$rows, ...$this->view->offsetNavigation(count($choices), $offset, ['action' => 'events.draft.show', 'step' => $step], $locale)];
                if ($choices === []) {
                    $lines[] = $this->view->translate('event.no_gear', $locale);
                }
                break;
            case 'review':
                $lines[] = $this->view->translate('event.review', $locale);
                $lines[] = $this->view->translate('event.entry_fee', $locale, ['fee' => $values['fee']]);
                $lines[] = $this->view->translate('event.deadline', $locale, ['time' => $this->view->dateTime($event->closes_at->toIso8601String(), $user)]);
                $option = collect($this->choices->options($event, $dog))->first(fn (array $option): bool => $this->choices->matchesOption($option, $values['plan']));
                if ($option !== null) {
                    $lines[] = Str::limit((string) $option['label'], 130);
                }
                if ($this->choices->hasMembers($event)) {
                    $ids = $values['plan'][$this->choices->memberKey($event)] ?? [];
                    $names = collect($this->choices->members($event, $dog))->whereIn('id', $ids)->map(fn (array $member): string => Str::limit((string) $member['name'], 45))->all();
                    $lines[] = $this->view->translate('event.selected_members', $locale, ['count' => count($ids)]).': '.implode(', ', $names);
                    $lines[] = $this->view->translate('event.balanced_plan', $locale);
                } else {
                    foreach ($event->rules['stages'] as $index => $stage) {
                        $lines[] = $this->stageLabel($stage, $locale).': '.$this->view->translate('event.decisions.'.$values['plan']['stages'][$index], $locale);
                    }
                    $names = collect($equipment)->whereIn('id', $values['gearIds'])->map(fn (array $item): string => Str::limit((string) $item['name'], 50))->all();
                    $lines[] = $this->view->translate('event.selected_gear', $locale).': '.($names === [] ? $this->view->translate('event.no_gear_selected', $locale) : implode(', ', $names));
                    if ($values['gearIds'] !== []) {
                        $lines[] = $this->view->translate('event.gear_note', $locale);
                    }
                }
                $reasons = $this->reviewReasons($user, $event, $dog, $equipment, $values, $locale, $entry);
                $lines = [...$lines, ...$reasons];
                if ($reasons === []) {
                    $rows[] = [$this->view->button($entry === null ? 'event.register' : 'event.update', $entry === null ? 'events.register' : 'events.update', $locale)];
                }
                foreach (array_slice($steps, 0, -1) as $editStep) {
                    $rows[] = [$this->view->button('event.edit_'.$editStep, 'events.draft.show', $locale, ['step' => $editStep])];
                }
                break;
        }
        if ($step !== 'review') {
            $rows[] = [$this->view->button('event.continue', 'events.draft.show', $locale, ['step' => $steps[$position + 1]])];
        }
        $back = $entry === null ? ['action' => 'events.prepare', 'eventId' => $event->id]
            : ['action' => 'events.entry', 'eventId' => $event->id, 'entryId' => $entry->id];
        if ($position > 0) {
            $back = ['action' => 'events.draft.show', 'step' => $steps[$position - 1]];
        }

        return $this->view->screen(implode("\n", $lines), $rows, $locale, $back, ['eventDraft' => $values]);
    }

    /**
     * @param  array<string,mixed>  $dog
     * @param  list<array<string,mixed>>  $equipment
     * @param  array<string,mixed>  $values
     * @return list<string>
     */
    private function reviewReasons(User $user, GameEvent $event, array $dog, array $equipment, array $values, string $locale, ?GameEventEntry $entry): array
    {
        $reasons = $this->choices->dogReasons($event, $dog);
        if (! $this->choices->isOpen($event) || $entry !== null && $entry->status !== 'registered') {
            $reasons[] = $this->view->translate('event.registration_closed', $locale);
        }
        if ($entry === null && (int) $user->coins < $values['fee']) {
            $reasons[] = $this->view->reason('events.errors.funds', $locale);
        }
        if (in_array('option', $this->choices->steps($event), true)
            && ! collect($this->choices->options($event, $dog))->contains(fn (array $option): bool => $option['eligible'] && $this->choices->matchesOption($option, $values['plan']))) {
            $reasons[] = $this->view->translate('event.no_options', $locale);
        }
        if ($this->choices->hasMembers($event)) {
            $ids = $values['plan'][$this->choices->memberKey($event)] ?? [];
            [$minimum, $maximum] = $this->choices->memberLimits($event);
            $eligibleIds = collect($this->choices->members($event, $dog))->filter(fn (array $member): bool => ($member['eligible'] ?? true) === true)->pluck('id')->all();
            if (count($ids) < $minimum || count($ids) > $maximum || array_diff($ids, $eligibleIds) !== []) {
                $reasons[] = $this->view->translate('event.choose_members', $locale, ['minimum' => $minimum, 'maximum' => $maximum]);
            }
        } else {
            if (array_diff($values['gearIds'], array_column($equipment, 'id')) !== []) {
                $reasons[] = $this->view->translate('event.incompatible_gear', $locale);
            }
            if ($event->discipline === 'canicross') {
                $slots = collect($equipment)->whereIn('id', $values['gearIds'])->pluck('slot')->all();
                if (array_diff(['body', 'line', 'handler'], $slots) !== []) {
                    $reasons[] = $this->view->translate('event.required_kit', $locale);
                }
            }
        }

        return array_values(array_unique($reasons));
    }

    private function discipline(GameEvent $event, string $locale): string
    {
        return $this->view->reason('events.disciplines.'.$event->discipline, $locale);
    }

    private function stageLabel(string $stage, string $locale): string
    {
        $label = $this->view->reason('events.stages.'.$stage, $locale);

        return $label === 'events.stages.'.$stage ? $stage : $label;
    }
}
