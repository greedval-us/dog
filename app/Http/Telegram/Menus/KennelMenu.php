<?php

namespace App\Http\Telegram\Menus;

use App\Http\Telegram\TelegramPetImage;
use App\Models\User;
use App\Modules\Kennel\DTO\StarterBreedData;
use App\Modules\Kennel\Enums\KennelPetAge;
use App\Modules\Kennel\Exceptions\AdoptionUnavailable;
use App\Modules\Kennel\Exceptions\StarterBreedUnavailable;
use App\Modules\Kennel\Queries\GetStarterBreeds;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Support\Str;

final class KennelMenu
{
    public function __construct(private MenuView $view, private GetStarterBreeds $breeds, private KennelAdoptionMenu $adoption, private TelegramPetImage $images) {}

    /** @param array<string,mixed> $command */
    public function index(User $user, string $locale, array $command): TelegramScreen
    {
        $breeds = $this->breeds->handle($locale);
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $rows = [];
        foreach ($breeds->slice($offset, MenuView::PAGE_SIZE) as $breed) {
            $rows[] = [$this->view->commandButton($breed->name, ['action' => 'kennel.breed', 'dogId' => $breed->id])];
        }
        $rows = [...$rows, ...$this->view->offsetNavigation($breeds->count(), $offset, ['action' => 'kennel'], $locale)];
        $rows[] = [$this->view->button('slots.title', 'slots', $locale)];
        $free = $this->freeSlots($user);
        $lines = [$this->view->label('kennel.title', $locale), '', $this->view->translate('kennel.free_slots', $locale, ['count' => $free]),
            $this->priceLabel($user, $locale), $this->view->translate('kennel.random_traits', $locale)];
        $lines[] = $this->view->translate($free < 1 ? 'kennel.no_space' : ($breeds->isEmpty() ? 'kennel.empty' : 'kennel.choose_breed'), $locale);

        return $this->view->screen(implode("\n", $lines), $rows, $locale);
    }

    /** @param array<string,mixed> $command */
    public function breed(User $user, string $locale, array $command): TelegramScreen
    {
        $breed = $this->findBreed((int) ($command['dogId'] ?? 0), $locale);
        $lines = ['🐕 '.$breed->name, Str::limit($breed->description, 400), '', $this->priceLabel($user, $locale), '', $this->view->translate('kennel.potentials', $locale)];
        $potentials = [];
        foreach ($breed->potentials as $stat => $value) {
            $potentials[] = $this->view->translate('stats.'.$stat, $locale).': '.$value;
        }
        foreach (array_chunk($potentials, 2) as $pair) {
            $lines[] = implode(' · ', $pair);
        }
        $lines[] = '';
        $rows = [];
        if ($this->freeSlots($user) > 0) {
            foreach (KennelPetAge::cases() as $age) {
                $key = $age === KennelPetAge::Puppy ? 'puppy' : 'young';
                $rows[] = [$this->view->commandButton((string) trans('ownership.ages.'.$key, [], $locale), ['action' => 'kennel.prepare', 'dogId' => $breed->id, 'ageMonths' => (int) $age->value])];
                $lines[] = (string) trans('ownership.ages.'.$key.'_description', [], $locale);
            }
            $lines[] = $this->view->translate('kennel.choose_age', $locale);
        } else {
            $lines[] = $this->view->translate('kennel.no_space', $locale);
        }
        $rows[] = [$this->view->button('slots.title', 'slots', $locale)];

        return $this->view->screen(implode("\n", $lines), $rows, $locale, ['action' => 'kennel'], photoPath: $this->images->forBreed($breed->id));
    }

    /** @param array<string,mixed> $command */
    public function prepare(User $user, string $locale, array $command): TelegramScreen
    {
        $breed = $this->findBreed((int) ($command['dogId'] ?? 0), $locale);
        $age = $command['ageMonths'] ?? null;
        if (! is_int($age) || KennelPetAge::tryFrom((string) $age) === null) {
            throw new AdoptionUnavailable('ownership.errors.age');
        }
        if ($this->freeSlots($user) < 1) {
            throw new AdoptionUnavailable('You need a free dog slot. Unlock a place on the My dog page.');
        }
        $mode = $user->canClaimStarterPet() ? 'starter' : 'purchase';
        $price = 0;
        if ($mode === 'purchase') {
            $price = config('doglive.kennel_price');
            if (! is_int($price) || $price < 1) {
                throw new AdoptionUnavailable('telegram.errors.stale');
            }
        }

        return $this->adoption->prompt($user, $locale, ['kennel' => ['userId' => $user->id, 'dogId' => $breed->id, 'ageMonths' => $age,
            'mode' => $mode, 'expectedPrice' => $price, 'token' => (string) Str::uuid(),
            'name' => null, 'awaitingName' => true, 'nameRequestedAt' => now()->getTimestamp()]]);
    }

    private function findBreed(int $id, string $locale): StarterBreedData
    {
        return $this->breeds->handle($locale)->firstWhere('id', $id) ?? throw new StarterBreedUnavailable;
    }

    private function freeSlots(User $user): int
    {
        return max(0, (int) $user->fresh()?->pet_slots - $user->pets()->active()->count());
    }

    private function priceLabel(User $user, string $locale): string
    {
        return $user->canClaimStarterPet() ? $this->view->translate('kennel.free', $locale)
            : $this->view->translate('kennel.price', $locale, ['price' => (int) config('doglive.kennel_price')]);
    }
}
