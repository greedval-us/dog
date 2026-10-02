<?php

namespace App\MoonShine\Resources;

use App\Models\ItemPurchase;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;

/** @extends AdminResource<ItemPurchase> */
#[Icon('shopping-bag')]
final class ItemPurchaseResource extends AdminResource
{
    protected string $model = ItemPurchase::class;

    protected string $titleKey = 'purchases';

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('user_id'), $this->valueField('item_id'), $this->valueField('shop_offer_id'), $this->valueField('currency'), $this->valueField('price_paid'), $this->valueField('created_at')];
    }

    protected function search(): array
    {
        return ['id', 'token'];
    }
}
