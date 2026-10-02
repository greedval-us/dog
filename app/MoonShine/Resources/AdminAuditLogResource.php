<?php

namespace App\MoonShine\Resources;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\MoonShine\Enums\StaffRole;
use App\MoonShine\Support\StaffAccess;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/** @extends AdminResource<AdminAuditLog> */
#[Icon('clipboard-document-list')]
final class AdminAuditLogResource extends AdminResource
{
    protected string $model = AdminAuditLog::class;

    protected string $titleKey = 'audit';

    public function listFields(): array
    {
        return [
            ID::make()->sortable(), Date::make(__('admin.created'), 'created_at')->format('d.m.Y H:i'),
            Text::make(__('admin.fields.actor_name'), 'actor_name'),
            Text::make(__('admin.fields.target_type'), 'target_type'),
            Number::make(__('admin.fields.target_id'), 'target_id'),
            Text::make(__('admin.fields.action'), 'action'), Text::make(__('admin.fields.reason'), 'reason'),
        ];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return StaffAccess::role() === StaffRole::Moderator ? $builder->where('target_type', User::class) : $builder;
    }

    protected function modifyItemQueryBuilder(Builder $builder): Builder
    {
        return $this->modifyQueryBuilder($builder);
    }

    public function filterFields(): array
    {
        return [...parent::filterFields(), Number::make(__('admin.fields.target_id'), 'target_id')];
    }

    protected function search(): array
    {
        return ['actor_name', 'reason'];
    }
}
