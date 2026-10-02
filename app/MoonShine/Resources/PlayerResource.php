<?php

namespace App\MoonShine\Resources;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/** @extends AdminResource<User> */
#[Icon('users')]
final class PlayerResource extends AdminResource
{
    protected string $model = User::class;

    protected string $titleKey = 'players';

    protected string $column = 'username';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [
            ID::make()->sortable(), Text::make(__('admin.fields.username'), 'username')->sortable(),
            Text::make(__('admin.fields.name'), 'name'),
            $this->valueField('status'), Number::make(__('admin.fields.coins'), 'coins')->sortable(),
            Number::make(__('admin.fields.gems'), 'gems')->sortable(),
            Date::make(__('admin.created'), 'created_at')->format('d.m.Y H:i'),
        ];
    }

    public function editFields(): array
    {
        return [
            $this->valueField('username'),
            Text::make(__('admin.fields.name'), 'name')->required(),
            Textarea::make(__('admin.fields.bio'), 'bio'),
            Select::make(__('admin.fields.status'), 'status')->options([
                'active' => __('admin.active'), 'blocked' => __('admin.blocked'),
            ])->required(),
            Textarea::make(__('admin.moderation_reason'), 'moderation_reason')->required()
                ->onApply(static fn (mixed $item): mixed => $item),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,blocked'],
            'moderation_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function filterFields(): array
    {
        return [
            ...parent::filterFields(),
            Select::make(__('admin.fields.status'), 'status')->options([
                'active' => __('admin.active'), 'blocked' => __('admin.blocked'),
            ])->nullable(),
        ];
    }

    protected function search(): array
    {
        return ['id', 'username', 'name'];
    }
}
