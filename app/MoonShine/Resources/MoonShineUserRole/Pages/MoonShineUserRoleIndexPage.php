<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MoonShineUserRole\Pages;

use App\MoonShine\Enums\StaffRole;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<MoonShineUserRoleResource>
 */
final class MoonShineUserRoleIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Preview::make(__('moonshine::ui.resource.role_name'), 'name', static fn (MoonshineUserRole $model): string => e(StaffRole::tryFrom((string) $model->getAttribute('code'))?->label() ?? $model->name)),
            Text::make(__('admin.fields.code'), 'code'),
            Preview::make(__('admin.permissions'), 'permissions', static fn (MoonshineUserRole $model): string => e(__('admin.permissions_'.$model->getAttribute('code')))),
        ];
    }
}
