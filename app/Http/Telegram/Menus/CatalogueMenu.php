<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Inventory\Actions\PurchaseItem;
use App\Modules\Inventory\DTO\PurchaseItemData;
use App\Modules\Inventory\Queries\CatalogueItemPresentation;
use App\Modules\Inventory\Queries\GetInventoryCatalogue;
use App\Modules\Inventory\Queries\GetShopCatalogue;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\PaginationState;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class CatalogueMenu
{
    public function __construct(
        private MenuView $view,
        private GetInventoryCatalogue $inventory,
        private GetShopCatalogue $shop,
        private CatalogueItemPresentation $itemPresentation,
        private PurchaseItem $purchase,
    ) {}

    /** @param Command $command */
    public function browse(User $user, string $locale, array $command): TelegramScreen
    {
        $kind = (string) $command['action'];
        $categoryId = isset($command['categoryId']) ? (int) $command['categoryId'] : null;
        $cursor = $command['cursor'] ?? null;
        $data = $this->withCursor($cursor, fn (): array => $kind === 'shop'
            ? $this->shop->handle($user, $locale, $categoryId)
            : $this->inventory->handle($user, $locale, $categoryId));
        $rows = [];
        $offset = max(0, (int) ($command['offset'] ?? 0));
        if ($categoryId === null) {
            foreach (array_slice($data['categories'], $offset, MenuView::PAGE_SIZE) as $category) {
                $rows[] = [$this->view->commandButton($category['name'], ['action' => $kind, 'categoryId' => $category['id']])];
            }
            $rows = [...$rows, ...$this->view->offsetNavigation(count($data['categories']), $offset, ['action' => $kind], $locale)];
            $empty = $data['categories'] === [];
        } else {
            $items = array_values(isset($data['offers']) ? $data['offers'] : $data['items']);
            foreach (array_slice($items, $offset, MenuView::PAGE_SIZE) as $item) {
                $rows[] = [$this->view->commandButton($item['name'], ['action' => $kind === 'shop' ? 'shop.offer' : 'inventory.item',
                    $kind === 'shop' ? 'offerId' : 'itemId' => $item['id'], 'categoryId' => $categoryId, 'cursor' => $cursor])];
            }
            $rows = [...$rows, ...$this->view->pageNavigation(count($items), $offset, $cursor, $data['nextCursor'], $data['previousCursor'], ['action' => $kind, 'categoryId' => $categoryId], $locale)];
            $empty = $items === [];
        }
        if ($empty) {
            $rows[] = [$this->view->button($kind === 'shop' ? 'inventory' : 'shop', $kind === 'shop' ? 'inventory' : 'shop', $locale)];
        }

        return $this->view->screen($this->view->translate($kind, $locale)."\n".$this->view->translate($empty ? $kind.'_empty' : ($categoryId === null ? 'choose_category' : 'choose_offer'), $locale), $rows, $locale,
            ['action' => $categoryId === null ? 'home' : $kind]);
    }

    /** @param Command $command */
    public function inventoryItem(User $user, string $locale, array $command): TelegramScreen
    {
        $item = $user->inventoryItems()->with('item.category')->findOrFail((int) $command['itemId']);
        $presentation = $this->itemPresentation->forInstance($item, $locale);
        $text = $this->itemText($presentation, $locale)."\n".$this->view->translate('remaining_uses', $locale).': '.$item->remaining_uses;

        return $this->view->screen($text, [[$this->view->button('use_for_pet', 'pets', $locale)]], $locale,
            ['action' => 'inventory', 'categoryId' => $item->item->item_category_id, 'cursor' => $command['cursor'] ?? null]);
    }

    /** @param Command $command */
    public function offer(User $user, string $locale, array $command): TelegramScreen
    {
        $cursor = $command['cursor'] ?? null;
        $data = $this->withCursor($cursor, fn (): array => $this->shop->handle($user, $locale, isset($command['categoryId']) ? (int) $command['categoryId'] : null));
        $offer = collect($data['offers'])->firstWhere('id', (int) $command['offerId']);
        if ($offer === null) {
            throw new ModelNotFoundException;
        }
        $text = $this->itemText($offer, $locale)."\n".$this->view->translate('price', $locale).': '.$offer['price'].' '.$this->view->translate($offer['currency'], $locale)
            ."\n".$this->view->translate('stock', $locale).': '.($offer['stock'] ?? $this->view->translate('unlimited', $locale));
        if ($offer['purchaseLimit'] !== null) {
            $text .= "\n".$this->view->translate('purchase_limit', $locale).': '.$offer['purchasedThisPeriod'].'/'.$offer['purchaseLimit'];
        }
        $purchase = ['offerId' => $offer['id'], 'itemId' => $offer['itemId'], 'expectedCurrency' => $offer['currency'], 'expectedPrice' => $offer['price'],
            'name' => $offer['name'], 'categoryId' => $command['categoryId'] ?? null, 'cursor' => $cursor];

        return $this->view->screen($text, $offer['soldOut'] ? [] : [[$this->view->button('buy', 'shop.confirm', $locale)]], $locale,
            ['action' => 'shop', 'categoryId' => $command['categoryId'] ?? null, 'cursor' => $cursor], ['purchase' => $purchase]);
    }

    /** @param State $state */
    public function purchaseConfirmation(string $locale, array $state): TelegramScreen
    {
        $purchase = $state['purchase'] ?? throw new InvalidArgumentException('Missing purchase quote.');
        $purchase['token'] ??= (string) Str::uuid();

        return $this->view->screen($this->view->translate('confirm_purchase', $locale, ['name' => $purchase['name'], 'price' => $purchase['expectedPrice'], 'currency' => $this->view->translate($purchase['expectedCurrency'], $locale)]),
            [[$this->view->button('confirm', 'shop.buy', $locale)]], $locale,
            ['action' => 'shop.offer', 'offerId' => $purchase['offerId'], 'categoryId' => $purchase['categoryId'], 'cursor' => $purchase['cursor']], ['purchase' => $purchase]);
    }

    /** @param State $state */
    public function buy(User $user, string $locale, array $state): TelegramScreen
    {
        $quote = $state['purchase'] ?? throw new InvalidArgumentException('Missing purchase quote.');
        $this->purchase->handle($user, new PurchaseItemData((int) $quote['offerId'], (int) $quote['itemId'], (string) $quote['expectedCurrency'], (int) $quote['expectedPrice'], (string) $quote['token']));

        return $this->view->screen($this->view->translate('purchased', $locale, ['name' => $quote['name']]), [[$this->view->button('inventory', 'inventory', $locale)], [$this->view->button('shop', 'shop', $locale)]], $locale, ['action' => 'shop']);
    }

    /** @param array<string, mixed> $item */
    private function itemText(array $item, string $locale): string
    {
        $text = $item['name']."\n".$item['category']."\n".$this->view->translate('quality', $locale).': '.$item['quality'].'/10'
            ."\n".$this->view->translate('usage_limit', $locale).': '.$item['usageLimit'];
        foreach ($item['bonuses'] as $key => $value) {
            $text .= "\n".$this->view->translate('stats.'.$key, $locale).': +'.$value;
        }
        if (isset($item['description']) && $item['description'] !== '') {
            $text .= "\n".Str::limit($item['description'], 350);
        }
        if ($item['competition'] !== null) {
            $text .= "\n".Str::limit($item['competition']['description'][$locale] ?? $item['competition']['description']['en'], 350);
        }
        foreach (array_slice($item['characteristics'], 0, 6) as $key => $value) {
            if (is_scalar($value)) {
                $text .= "\n".$this->view->translate('stats.'.$key, $locale).': '.Str::limit((string) $value, 80);
            }
        }
        foreach (array_slice($item['grantedEffects'], 0, 4) as $effect) {
            $text .= "\n".$this->view->translate('granted_effect', $locale).': '.($effect['name'][$locale] ?? $effect['name']['en'] ?? $effect['code']);
        }
        $text .= $this->view->riskText($item['risks'], $locale);

        return $text;
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function withCursor(?string $cursor, callable $callback): mixed
    {
        CursorPaginator::currentCursorResolver(fn (): ?Cursor => Cursor::fromEncoded($cursor));
        try {
            return $callback();
        } finally {
            PaginationState::resolveUsing(app());
        }
    }
}
