<?php

namespace App\Http\Telegram\Menus;

use App\Http\Telegram\TelegramPetImage;
use App\Models\User;
use App\Modules\Pets\Queries\GetPetCare;
use App\Modules\Pets\Queries\GetPrimaryPet;
use App\Modules\Players\Queries\GetPlayerDogs;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @phpstan-import-type Command from MenuView
 */
final class PetMenu
{
    public function __construct(
        private MenuView $view,
        private GetPlayerDogs $dogs,
        private GetPrimaryPet $pets,
        private GetPetCare $care,
        private TelegramPetImage $images,
    ) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command): TelegramScreen
    {
        $pets = $this->dogs->handle($user, $locale);
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $rows = [];
        foreach (array_slice($pets, $offset, MenuView::PAGE_SIZE) as $pet) {
            $rows[] = [$this->view->commandButton($pet['name'].' · '.$pet['breed'], ['action' => 'pet', 'petId' => $pet['id']])];
        }
        $rows = [...$rows, ...$this->view->offsetNavigation(count($pets), $offset, ['action' => 'pets'], $locale)];
        $rows[] = [$this->view->button('kennel.title', 'kennel', $locale), $this->view->button('slots.title', 'slots', $locale)];

        return $this->view->screen($this->view->translate($pets === [] ? 'pets_empty' : 'choose_pet', $locale), $rows, $locale);
    }

    public function show(User $user, string $locale, int $petId, ?string $notice = null): TelegramScreen
    {
        $pet = $this->pets->handle($user, $locale, $petId);
        if ($pet === null) {
            throw new ModelNotFoundException;
        }
        $care = $this->care->handle($user, $petId, $locale);
        $lines = ['🐾 '.$pet->name.' · '.$pet->breed,
            $this->view->translate('lifecycle.'.$pet->lifecycle['status'], $locale),
        ];
        if ($notice !== null) {
            $lines[] = $notice;
        }
        $lines[] = '';
        $lines[] = $this->view->translate('sections.needs', $locale);
        $energyPercent = $pet->energy['maximum'] > 0 ? $pet->energy['value'] / $pet->energy['maximum'] * 100 : 0;
        $lines[] = $this->view->translate('energy', $locale).' '.$this->view->bar($energyPercent).' '.round($pet->energy['value']).'/'.$pet->energy['maximum'];
        foreach ($pet->states as $key => $value) {
            $lines[] = $this->view->translate('stats.'.$key, $locale).' '.$this->view->bar($value).' '.round($value).'%';
        }
        $stats = [];
        foreach ($pet->stats as $key => $value) {
            $stats[] = $this->view->translate('stats.'.$key, $locale).': '.$value['value'].'/'.$value['potential'];
        }
        $lines[] = '';
        $lines[] = $this->view->translate('sections.stats', $locale);
        foreach (array_chunk($stats, 2) as $pair) {
            $lines[] = implode(' · ', $pair);
        }
        $rows = [];
        $state = [];
        if ($care['active'] !== null) {
            $active = $care['active'];
            $lines[] = '';
            $lines[] = $this->view->translate('activity_until', $locale, ['name' => $this->activeLabel($active['label'], $locale), 'time' => $this->view->dateTime($active['endsAt'], $user)]);
            $state['care'] = ['petId' => $petId, 'token' => $active['token'], 'endsAt' => $active['endsAt']];
            $rows[] = [$this->view->button('refresh', 'pet', $locale, ['petId' => $petId])];
            $rows[] = [$this->view->button('complete', 'care.complete', $locale, ['petId' => $petId, 'token' => $active['token']])];
        } elseif ($care['busy']) {
            $lines[] = '';
            $lines[] = $this->view->translate('pet_busy', $locale);
            $rows[] = [$this->view->button('refresh', 'pet', $locale, ['petId' => $petId])];
        } elseif (! $care['blocked']) {
            $rows[] = [$this->view->button('care', 'care.groups', $locale, ['petId' => $petId])];
            $rows[] = [$this->view->button('trainings', 'care.variants', $locale, ['petId' => $petId, 'group' => 'training'])];
        }

        if (in_array($pet->lifecycle['status'], ['active', 'retired'], true)) {
            $activities = [$this->view->button('work.title', 'work', $locale, ['petId' => $petId])];
            if ($pet->lifecycle['status'] === 'active') {
                array_unshift($activities, $this->view->button('skill.title', 'skills', $locale, ['petId' => $petId]));
            }
            $rows[] = $activities;
            $rows[] = [$this->view->button('clinic.title', 'clinic', $locale, ['petId' => $petId])];
            $rows[] = [$this->view->button('market.sell_title', 'market.sell.pet', $locale, ['petId' => $petId])];
        }

        $rows[] = [$this->view->button('history.title', 'pet.history', $locale, ['petId' => $petId])];

        return $this->view->screen(implode("\n", $lines), $rows, $locale, ['action' => 'pets'], $state, $this->images->forPet($user, $petId));
    }

    private function activeLabel(string $label, string $locale): string
    {
        $labels = ['A portion of food' => 'meal', 'Fresh water' => 'water', 'Walk outside' => 'walk', 'Move around at home' => 'home',
            'Play together' => 'attention', 'Play with a toy' => 'toy', 'Rinse paws' => 'wash', 'Use a care product' => 'care', 'Take a nap' => 'nap', 'Long sleep' => 'sleep'];

        return isset($labels[$label]) ? $this->view->translate('variants.'.$labels[$label], $locale) : $label;
    }
}
