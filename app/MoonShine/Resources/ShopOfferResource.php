<?php

namespace App\MoonShine\Resources;

use App\Models\ShopOffer;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;

/** @extends AdminResource<ShopOffer> */
#[Icon('shopping-cart')]
final class ShopOfferResource extends AdminResource
{
    protected string $model = ShopOffer::class;

    protected string $titleKey = 'offers';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('item_id'), $this->valueField('currency'),
            $this->valueField('price'), $this->valueField('stock'), $this->valueField('is_active')];
    }

    public function editFields(): array
    {
        return [
            $this->valueField('item_id'), $this->valueField('currency'),
            Number::make(__('admin.fields.price'), 'price')->min(1)->required(),
            Number::make(__('admin.fields.stock'), 'stock')->min(0)->nullable()->hint(__('admin.unlimited_stock')),
            Number::make(__('admin.fields.sort_order'), 'sort_order')->min(0)->required(),
            Switcher::make(__('admin.fields.is_active'), 'is_active'),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            'price' => ['required', 'integer', 'between:1,2147483647'],
            'stock' => ['nullable', 'integer', 'between:0,2147483647'],
            'sort_order' => ['required', 'integer', 'between:0,2147483647'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function filterFields(): array
    {
        return [
            Number::make(__('admin.fields.item_id'), 'item_id'),
            Select::make(__('admin.fields.is_active'), 'is_active')->options([1 => __('admin.active'), 0 => __('admin.inactive')])->nullable(),
        ];
    }
}
