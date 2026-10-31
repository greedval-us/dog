<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Inventory\Queries\CatalogueItemPresentation;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Queries\GetCareItems;
use App\Modules\Pets\Queries\GetPetCare;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 * @phpstan-import-type OptionView from GetPetCare
 *
 * @phpstan-type Draft array{petId: int, variant: string, itemIds: array<string, int>, skipped: list<string>, token: string}
 */
final class CareMenu
{
    public function __construct(
        private MenuView $view,
        private PetMenu $pets,
        private GetPetCare $care,
        private GetCareItems $careItems,
        private StartPetCare $startCare,
        private CompletePetCare $completeCare,
        private CatalogueItemPresentation $itemPresentation,
    ) {}

    /** @param Command $command */
    public function options(User $user, string $locale, array $command): TelegramScreen
    {
        $petId = (int) $command['petId'];
        $care = $this->care->handle($user, $petId, $locale);
        if ($care['busy'] || $care['blocked']) {
            return $this->pets->show($user, $locale, $petId);
        }
        $group = $command['group'] ?? null;
        $rows = [];
        if ($group === null) {
            foreach (array_unique(array_column($care['options'], 'group')) as $name) {
                if ($name !== 'training') {
                    $rows[] = [$this->view->commandButton($this->view->translate('groups.'.$name, $locale), ['action' => 'care.variants', 'petId' => $petId, 'group' => $name])];
                }
            }
        } else {
            $options = array_values(array_filter($care['options'], fn (array $option): bool => $option['group'] === $group));
            $offset = max(0, (int) ($command['offset'] ?? 0));
            foreach (array_slice($options, $offset, MenuView::PAGE_SIZE) as $option) {
                $rows[] = [$this->view->commandButton($this->optionLabel($option, $locale), ['action' => 'care.select', 'petId' => $petId, 'variant' => $option['id']])];
            }
            $rows = [...$rows, ...$this->view->offsetNavigation(count($options), $offset, $command, $locale)];
        }

        return $this->view->screen($this->view->translate($rows === [] ? 'actions_empty' : 'choose_action', $locale), $rows, $locale,
            $group === null || $group === 'training' ? ['action' => 'pet', 'petId' => $petId] : ['action' => 'care.groups', 'petId' => $petId]);
    }

    /** @param Command $command */
    public function select(User $user, string $locale, array $command): TelegramScreen
    {
        $draft = ['petId' => (int) $command['petId'], 'variant' => (string) $command['variant'], 'itemIds' => [], 'skipped' => [], 'token' => (string) Str::uuid()];

        return $this->items($user, $locale, ['action' => 'care.items'], ['draft' => $draft]);
    }

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function items(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        $draft = $this->draft($state);
        $care = $this->care->handle($user, $draft['petId'], $locale);
        $option = collect($care['options'])->firstWhere('id', $draft['variant']);
        if ($option === null) {
            throw new ModelNotFoundException;
        }
        $back = ['action' => 'care.variants', 'petId' => $draft['petId'], 'group' => $option['group']];
        if ($care['busy'] || $care['blocked']) {
            return $this->pets->show($user, $locale, $draft['petId']);
        }
        if ($option['reasonCode'] !== null || $care['cooldowns']->has($option['group'])) {
            $reason = $option['reasonCode'] ?? 'cooldown';

            return $this->view->screen($this->view->translate('refusals.'.$reason, $locale), [], $locale, $back);
        }
        $categories = [...$option['requirements'], ...$option['optional']];
        $remaining = array_values(array_filter($categories, fn (string $category): bool => ! isset($draft['itemIds'][$category]) && ! in_array($category, $draft['skipped'], true)));
        $category = $remaining[0] ?? null;
        if ($category !== null && in_array($command['action'], ['care.choose', 'care.skip'], true)) {
            if ($command['action'] === 'care.skip') {
                if (! in_array($category, $option['optional'], true)) {
                    throw new InvalidArgumentException('A required care item cannot be skipped.');
                }
                $draft['skipped'][] = $category;
            } else {
                $draft['itemIds'][$category] = (int) $command['itemId'];
            }

            return $this->items($user, $locale, ['action' => 'care.items'], ['draft' => $draft]);
        }
        $text = $this->optionLabel($option, $locale)."\n".$this->view->translate('care_cost', $locale, ['seconds' => $option['duration'], 'energy' => $option['energy']]);
        $text .= "\n".$this->view->translate('base_effects', $locale).': '.$this->valuesText($option['effects'], $locale);
        if ($category === null) {
            $risks = $option['risks'] ?? [];
            foreach ($draft['itemIds'] as $name => $id) {
                $instance = $user->inventoryItems()->with('item.category')->findOrFail($id);
                $text .= "\n".$this->view->translate('categories.'.$name, $locale).': '.($instance->name[$locale] ?? $instance->name['en'] ?? '#'.$id);
                $presentation = $this->itemPresentation->forInstance($instance, $locale);
                $risks = [...$risks, ...$presentation['risks']];
                if ($name === 'sports' && isset($option['gainsByQuality'][$instance->quality])) {
                    $text .= "\n".$this->view->translate('stat_gains', $locale).': '.$this->valuesText($option['gainsByQuality'][$instance->quality], $locale);
                }
            }
            $text .= $this->view->riskText($risks, $locale);

            return $this->view->screen($text."\n\n".$this->view->translate('confirm_care', $locale), [[$this->view->button('start', 'care.start', $locale)], [$this->view->button('refresh', 'care.confirm', $locale)]], $locale, $back, ['draft' => $draft]);
        }
        $uses = $option['uses'][$category] ?? 1;
        $page = $this->careItems->handle($user, $locale, $category, $uses, $command['cursor'] ?? null);
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $rows = [];
        foreach (array_slice($page['items'], $offset, MenuView::PAGE_SIZE) as $item) {
            $rows[] = [$this->view->commandButton($item['name'].' · '.$item['remainingUses'].'×', ['action' => 'care.choose', 'itemId' => $item['id']])];
        }
        $rows = [...$rows, ...$this->view->pageNavigation(count($page['items']), $offset, $command['cursor'] ?? null, $page['nextCursor'], null, ['action' => 'care.items'], $locale)];
        if (in_array($category, $option['optional'], true)) {
            $rows[] = [$this->view->button('skip', 'care.skip', $locale)];
        }
        if ($page['items'] === []) {
            $text .= "\n".$this->view->translate('items_empty', $locale);
            $rows[] = [$this->view->button('shop', 'shop', $locale)];
        }
        $text .= "\n".$this->view->translate('choose_item', $locale, ['category' => $this->view->translate('categories.'.$category, $locale), 'uses' => $uses,
            'step' => count($categories) - count($remaining) + 1, 'total' => count($categories)]);

        return $this->view->screen($text, $rows, $locale, $back, ['draft' => $draft]);
    }

    /** @param State $state */
    public function start(User $user, string $locale, array $state): TelegramScreen
    {
        $draft = $this->draft($state);
        try {
            $this->startCare->handle($user, $draft['petId'], $draft['variant'], $draft['itemIds'], $draft['token']);
        } catch (InvalidArgumentException $exception) {
            $available = $this->care->handle($user, $draft['petId'], $locale);
            if (collect($available['options'])->firstWhere('id', $draft['variant']) === null) {
                throw new ModelNotFoundException(previous: $exception);
            }

            throw $exception;
        }

        return $this->pets->show($user, $locale, $draft['petId'], $this->view->translate('care_started', $locale));
    }

    /** @param Command $command */
    public function complete(User $user, string $locale, array $command): TelegramScreen
    {
        $this->completeCare->handle($user, (int) $command['petId'], (string) $command['token']);

        return $this->pets->show($user, $locale, (int) $command['petId']);
    }

    /**
     * @param  array<string, int|float>  $values
     */
    private function valuesText(array $values, string $locale): string
    {
        $parts = [];
        foreach ($values as $key => $value) {
            $parts[] = $this->view->translate('stats.'.$key, $locale).' '.($value > 0 ? '+' : '').$value;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  State  $state
     * @return Draft
     */
    private function draft(array $state): array
    {
        $draft = $state['draft'] ?? throw new InvalidArgumentException('Missing care draft.');
        if (! is_array($draft) || ! isset($draft['petId'], $draft['variant'], $draft['itemIds'], $draft['skipped'], $draft['token']) || ! Str::isUuid($draft['token'])) {
            throw new InvalidArgumentException('Invalid care draft.');
        }

        return $draft;
    }

    /** @param OptionView $option */
    private function optionLabel(array $option, string $locale): string
    {
        return str_starts_with($option['id'], 'training:') ? $option['label'] : $this->view->translate('variants.'.$option['id'], $locale);
    }
}
