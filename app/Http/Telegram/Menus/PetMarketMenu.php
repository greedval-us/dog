<?php

namespace App\Http\Telegram\Menus;

use App\Http\Telegram\TelegramPetImage;
use App\Models\User;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetMarket;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Str;

/** @phpstan-type Context array{tab:string,sex:string|null,cursor:string|null,offset:int} */
final class PetMarketMenu
{
    public function __construct(private MenuView $view, private GetPetMarket $market, private TelegramPetImage $images) {}

    /** @param array<string,mixed> $command */
    public function index(User $user, string $locale, array $command): TelegramScreen
    {
        $context = $this->context($command);
        $data = $this->market->handle($user, $locale, Cursor::fromEncoded($context['cursor']), $context['sex'] === null ? null : PetSex::from($context['sex']));
        $own = $context['tab'] === 'own';
        $cards = $own ? $data['ownListings'] : $data['listings'];
        $offset = $context['offset'];
        if ($offset >= count($cards) && $cards !== []) {
            $offset = intdiv(count($cards) - 1, MenuView::PAGE_SIZE) * MenuView::PAGE_SIZE;
        }
        $context['offset'] = $offset;
        $rows = [];
        foreach (array_slice($cards, $offset, MenuView::PAGE_SIZE) as $card) {
            $rows[] = [$this->view->commandButton($card['pet']['name'].' · '.$card['price'].' 🪙', ['action' => 'market.show', 'listingId' => $card['id'], ...$context])];
        }
        $navigation = ['action' => 'market', 'tab' => $context['tab'], 'sex' => $context['sex']];
        $rows = [...$rows, ...($own
            ? $this->view->offsetNavigation(count($cards), $offset, $navigation, $locale)
            : $this->view->pageNavigation(count($cards), $offset, $context['cursor'], $data['pagination']['nextCursor'], $data['pagination']['previousCursor'], $navigation, $locale, previousOffset: GetPetMarket::PAGE_SIZE - MenuView::PAGE_SIZE))];
        $rows[] = [
            $this->view->button('market.all', 'market', $locale, ['tab' => $context['tab']]),
            $this->view->button('market.sexes.male', 'market', $locale, ['tab' => $context['tab'], 'sex' => 'male']),
            $this->view->button('market.sexes.female', 'market', $locale, ['tab' => $context['tab'], 'sex' => 'female']),
        ];
        $rows[] = [$this->view->button($own ? 'market.offers' : 'market.own', 'market', $locale, ['tab' => $own ? 'offers' : 'own']), $this->view->button('market.sell_title', 'market.sell', $locale)];
        $lines = [$this->view->label('market.title', $locale), $this->view->translate($own ? 'market.own' : 'market.offers', $locale),
            $this->view->translate('market.filter', $locale, ['sex' => $this->view->translate('market.sexes.'.($context['sex'] ?? 'all'), $locale)]),
            $this->view->translate('market.balance', $locale, ['coins' => $user->fresh()->coins ?? 0]),
            $this->view->translate($cards === [] ? 'market.empty' : 'market.choose', $locale)];

        return $this->view->screen(implode("\n", $lines), $rows, $locale);
    }

    /** @param array<string,mixed> $command */
    public function show(User $user, string $locale, array $command): TelegramScreen
    {
        $context = $this->context($command);
        $selection = new SavedMenuState(['selection' => $command], 'selection');
        $card = $this->market->find($user, $selection->integer('listingId'), $locale);
        $own = $card['seller']['id'] === $user->id;
        $lines = ['🐕 '.$card['pet']['name'].' · '.$card['pet']['breed'],
            $this->view->translate('market.price', $locale, ['price' => $card['price']]),
            $this->view->translate('market.age', $locale, ['months' => $card['pet']['ageMonths']]),
            $this->view->translate('market.sex', $locale, ['sex' => $this->view->translate('market.sexes.'.$card['pet']['sex'], $locale)]),
            $this->view->translate('lifecycle.'.($card['pet']['retired'] ? 'retired' : 'active'), $locale),
            $this->view->translate('market.seller', $locale, ['username' => '@'.$card['seller']['username']])];
        foreach (array_slice($card['pet']['titles'], 0, 3) as $title) {
            $lines[] = '🏅 '.Str::limit($title['name'], 80);
        }
        $rows = $own ? [
            [$this->view->button('market.edit_price', 'market.sell.pet', $locale, ['petId' => $card['pet']['id']])],
            [$this->view->button('market.withdraw_action', 'market.withdraw-confirm', $locale, ['petId' => $card['pet']['id']])],
        ] : [[$this->view->button('buy', 'market.confirm', $locale, ['listingId' => $card['id'], ...$context])]];
        $rows[] = [$this->view->button('slots.title', 'slots', $locale)];

        return $this->view->screen(implode("\n", $lines), $rows, $locale, ['action' => 'market', ...$context], photoPath: $this->images->forBreed($card['pet']['dogId']));
    }

    /**
     * @param  array<string,mixed>  $command
     * @return Context
     */
    private function context(array $command): array
    {
        $saved = new SavedMenuState(['selection' => $command], 'selection');
        $tab = $saved->has('tab') ? $saved->string('tab') : 'offers';
        $sex = $saved->nullableString('sex');
        if (! in_array($tab, ['offers', 'own'], true) || ($sex !== null && PetSex::tryFrom($sex) === null)) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return ['tab' => $tab, 'sex' => $sex, 'cursor' => $saved->nullableString('cursor'), 'offset' => $saved->integer('offset', minimum: 0, default: 0)];
    }
}
