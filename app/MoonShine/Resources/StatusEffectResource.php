<?php

namespace App\MoonShine\Resources;

use App\Models\StatusEffect;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;

/** @extends AdminResource<StatusEffect> */
#[Icon('bolt')]
final class StatusEffectResource extends AdminResource
{
    protected string $model = StatusEffect::class;

    protected string $titleKey = 'effects';

    protected string $column = 'code';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('code'), $this->valueField('name'), $this->valueField('kind'), $this->valueField('duration_seconds'), $this->valueField('is_active')];
    }

    public function editFields(): array
    {
        return [
            ...$this->catalogueFields(),

        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            ...$this->catalogueRules(),

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
