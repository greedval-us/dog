<?php

namespace App\MoonShine\Resources;

use App\Models\Item;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;

/** @extends AdminResource<Item> */
#[Icon('cube')]
final class ItemResource extends AdminResource
{
    protected string $model = Item::class;

    protected string $titleKey = 'items';

    protected string $column = 'code';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('code'), $this->valueField('name'), $this->valueField('quality'), $this->valueField('usage_limit'), $this->valueField('is_active')];
    }

    public function editFields(): array
    {
        return [
            ...$this->catalogueFields(),
            Number::make(__('admin.fields.quality'), 'quality')->min(1)->max(10)->required(),
            Number::make(__('admin.fields.usage_limit'), 'usage_limit')->min(1)->max(2147483647)->required(),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            ...$this->catalogueRules(),
            'quality' => ['required', 'integer', 'between:1,10'],
            'usage_limit' => ['required', 'integer', 'between:1,2147483647'],
        ];
    }

    public function filterFields(): array
    {
        return [Select::make(__('admin.fields.is_active'), 'is_active')->options([1 => __('admin.active'), 0 => __('admin.inactive')])->nullable()];
    }

    protected function search(): array
    {
        return ['id', 'code'];
    }
}
