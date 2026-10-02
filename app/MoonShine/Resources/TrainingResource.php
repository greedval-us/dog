<?php

namespace App\MoonShine\Resources;

use App\Models\Training;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;

/** @extends AdminResource<Training> */
#[Icon('arrow-trending-up')]
final class TrainingResource extends AdminResource
{
    protected string $model = Training::class;

    protected string $titleKey = 'trainings';

    protected string $column = 'code';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('code'), $this->valueField('name'), $this->valueField('energy_cost'), $this->valueField('duration_seconds'), $this->valueField('cooldown_seconds'), $this->valueField('risk_chance'), $this->valueField('is_active')];
    }

    public function editFields(): array
    {
        return [
            ...$this->catalogueFields(),
            Number::make(__('admin.fields.energy_cost'), 'energy_cost')->min(0)->max(100)->required(),
            Number::make(__('admin.fields.duration_seconds'), 'duration_seconds')->min(1)->max(86400)->required(),
            Number::make(__('admin.fields.cooldown_seconds'), 'cooldown_seconds')->min(0)->max(604800)->required(),
            Number::make(__('admin.fields.risk_chance'), 'risk_chance')->min(0)->max(100)->required(),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            ...$this->catalogueRules(),
            'energy_cost' => ['required', 'integer', 'between:0,100'],
            'duration_seconds' => ['required', 'integer', 'between:1,86400'],
            'cooldown_seconds' => ['required', 'integer', 'between:0,604800'],
            'risk_chance' => ['required', 'integer', 'between:0,100'],
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
