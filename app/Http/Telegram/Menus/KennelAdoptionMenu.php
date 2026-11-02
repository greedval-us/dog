<?php

namespace App\Http\Telegram\Menus;

use App\Http\Telegram\AdoptTelegramPet;
use App\Models\User;
use App\Modules\Kennel\Actions\PurchaseKennelPet;
use App\Modules\Kennel\DTO\AdoptStarterPetData;
use App\Modules\Kennel\DTO\PurchaseKennelPetData;
use App\Modules\Kennel\Queries\GetStarterBreeds;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Support\Str;

/** @phpstan-import-type Draft from SavedKennelDraft */
final class KennelAdoptionMenu
{
    public function __construct(private MenuView $view, private GetStarterBreeds $breeds, private AdoptTelegramPet $starter, private PurchaseKennelPet $purchase) {}

    /** @param array<string,mixed> $state */
    public function prompt(User $user, string $locale, array $state, ?string $notice = null): TelegramScreen
    {
        $draft = (new SavedKennelDraft($user, $state))->data;
        $draft['awaitingName'] = true;
        $draft['nameRequestedAt'] = now()->getTimestamp();
        $lines = array_filter([$notice, $this->summary($draft, $locale), $this->view->translate('kennel.enter_name', $locale)]);

        return $this->view->screen(implode("\n\n", $lines), [], $locale, ['action' => 'kennel.breed', 'dogId' => $draft['dogId']], ['kennel' => $draft]);
    }

    /** @param array<string,mixed> $command
     * @param  array<string,mixed>  $state
     */
    public function name(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        $draft = (new SavedKennelDraft($user, $state))->data;
        if (! $draft['awaitingName']) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $name = is_string($command['text'] ?? null) ? trim($command['text']) : '';
        if (! SavedKennelDraft::validName($name)) {
            return $this->prompt($user, $locale, $state, $this->view->translate('kennel.invalid_name', $locale));
        }
        if ($draft['name'] !== null && $draft['name'] !== $name) {
            $draft['token'] = (string) Str::uuid();
        }
        $draft['name'] = $name;
        $draft['awaitingName'] = false;

        return $this->confirm($user, $locale, ['kennel' => $draft]);
    }

    /** @param array<string,mixed> $state */
    public function confirm(User $user, string $locale, array $state): TelegramScreen
    {
        $draft = $this->confirmed($user, $state);

        return $this->view->screen($this->summary($draft, $locale)."\n\n".$this->view->translate('kennel.confirmation', $locale), [
            [$this->view->button($draft['mode'] === 'starter' ? 'kennel.adopt_free' : 'kennel.buy', 'kennel.adopt', $locale)],
            [$this->view->button('kennel.rename', 'kennel.rename', $locale)],
        ], $locale, ['action' => 'kennel.breed', 'dogId' => $draft['dogId']], ['kennel' => $draft]);
    }

    /** @param array<string,mixed> $state */
    public function adopt(User $user, string $locale, array $state): TelegramScreen
    {
        $draft = $this->confirmed($user, $state);
        $petId = $draft['mode'] === 'starter'
            ? $this->starter->handle($user, new AdoptStarterPetData($draft['dogId'], $draft['name'], $draft['ageMonths']), $draft['token'])
            : $this->purchase->handle($user, new PurchaseKennelPetData($draft['dogId'], $draft['name'], $draft['expectedPrice'], $draft['token'], $draft['ageMonths']))->pet_id;
        $rows = [];
        if ($user->pets()->active()->whereKey($petId)->exists()) {
            $rows[] = [$this->view->button('kennel.open_pet', 'pet', $locale, ['petId' => $petId])];
        }
        $rows[] = [$this->view->button('pets', 'pets', $locale), $this->view->button('slots.title', 'slots', $locale)];

        return $this->view->screen($this->view->translate('kennel.adopted', $locale, ['name' => $draft['name']]), $rows, $locale, ['action' => 'kennel']);
    }

    /** @param array<string,mixed> $state
     * @return array{userId:int,dogId:int,ageMonths:int,mode:string,expectedPrice:int,token:string,name:string,awaitingName:bool,nameRequestedAt:int}
     */
    private function confirmed(User $user, array $state): array
    {
        $draft = (new SavedKennelDraft($user, $state))->data;
        if ($draft['name'] === null || $draft['awaitingName']) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return $draft;
    }

    /** @param Draft $draft */
    private function summary(array $draft, string $locale): string
    {
        $breed = $this->breeds->handle($locale)->firstWhere('id', $draft['dogId']);
        if ($breed === null) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return implode("\n", array_filter([$this->view->translate('kennel.title', $locale), $breed->name,
            $draft['name'] === null ? null : $this->view->translate('kennel.name', $locale, ['name' => $draft['name']]),
            (string) trans('ownership.ages.'.($draft['ageMonths'] === 3 ? 'puppy' : 'young'), [], $locale),
            $draft['mode'] === 'starter' ? $this->view->translate('kennel.free', $locale) : $this->view->translate('kennel.price', $locale, ['price' => $draft['expectedPrice']]),
            $this->view->translate('kennel.random_traits', $locale)]));
    }
}
