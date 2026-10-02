<?php

namespace App\MoonShine\Resources;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;

/** @extends AdminResource<Skill> */
#[Icon('academic-cap')]
final class SkillResource extends AdminResource
{
    protected string $model = Skill::class;

    protected string $titleKey = 'skills';

    protected string $column = 'code';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('code'), $this->valueField('name'), $this->valueField('is_active')];
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
