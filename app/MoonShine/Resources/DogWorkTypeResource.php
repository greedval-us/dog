<?php

namespace App\MoonShine\Resources;

use App\Models\DogWorkType;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;

/** @extends AdminResource<DogWorkType> */
#[Icon('briefcase')]
final class DogWorkTypeResource extends AdminResource
{
    protected string $model = DogWorkType::class;

    protected string $titleKey = 'work_types';

    protected string $column = 'code';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('code'), $this->valueField('name'), $this->valueField('required_skill_level'), $this->valueField('coins_reward'), $this->valueField('gems_reward'), $this->valueField('daily_limit'), $this->valueField('is_active')];
    }

    public function editFields(): array
    {
        return [
            ...$this->catalogueFields(),
            Number::make(__('admin.fields.required_skill_level'), 'required_skill_level')->min(1)->max(5)->required(),
            Number::make(__('admin.fields.coins_reward'), 'coins_reward')->min(0)->max(2147483647)->required(),
            Number::make(__('admin.fields.gems_reward'), 'gems_reward')->min(0)->max(2147483647)->required(),
            Number::make(__('admin.fields.daily_limit'), 'daily_limit')->min(1)->max(100000)->required(),
            Number::make(__('admin.fields.duration_seconds'), 'duration_seconds')->min(1)->max(86400)->required(),
            Number::make(__('admin.fields.energy_cost'), 'energy_cost')->min(0)->max(100)->required(),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            ...$this->catalogueRules(),
            'required_skill_level' => ['required', 'integer', 'between:1,5'],
            'coins_reward' => ['required', 'integer', 'between:0,2147483647'],
            'gems_reward' => ['required', 'integer', 'between:0,2147483647'],
            'daily_limit' => ['required', 'integer', 'between:1,100000'],
            'duration_seconds' => ['required', 'integer', 'between:1,86400'],
            'energy_cost' => ['required', 'integer', 'between:0,100'],
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
