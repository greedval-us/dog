<?php

namespace App\MoonShine\Resources;

use App\Models\GameAsset;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;

/** @extends AdminResource<GameAsset> */
#[Icon('photo')]
final class GameAssetResource extends AdminResource
{
    protected string $model = GameAsset::class;

    protected string $titleKey = 'assets';

    protected string $column = 'code';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('code'), $this->valueField('kind'),
            $this->valueField('name'), $this->valueField('coins_price'), $this->valueField('gems_price'), $this->valueField('is_active')];
    }

    public function editFields(): array
    {
        return [
            ...$this->catalogueFields(), $this->valueField('kind'),
            $this->valueField('image_path'), $this->valueField('icon_path'),
            Number::make(__('admin.fields.coins_price'), 'coins_price')->min(1)->nullable()->hint(__('admin.free_asset')),
            Number::make(__('admin.fields.gems_price'), 'gems_price')->min(1)->nullable(),
            Number::make(__('admin.fields.sort_order'), 'sort_order')->min(0)->required(),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            ...$this->catalogueRules(),
            'coins_price' => ['nullable', 'integer', 'between:1,2147483647'],
            'gems_price' => ['nullable', 'integer', 'between:1,2147483647'],
            'sort_order' => ['required', 'integer', 'between:0,2147483647'],
        ];
    }

    public function filterFields(): array
    {
        return [Select::make(__('admin.fields.kind'), 'kind')->options(['portrait' => __('admin.portrait'), 'background' => __('admin.background')])->nullable()];
    }

    protected function search(): array
    {
        return ['id', 'code'];
    }
}
