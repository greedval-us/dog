<?php

namespace App\MoonShine\Resources;

use App\Models\PetHistoryEvent;
use App\MoonShine\Support\ValidPetHistoryConditions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/** @extends AdminResource<PetHistoryEvent> */
#[Icon('chat-bubble-left-right')]
final class PetHistoryEventResource extends AdminResource
{
    protected string $model = PetHistoryEvent::class;

    protected string $titleKey = 'history_events';

    protected string $column = 'code';

    protected bool $editable = true;

    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, [Action::VIEW, Action::CREATE, Action::UPDATE]);
    }

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('code'), $this->valueField('name'),
            $this->valueField('kind'), $this->valueField('cooldown_minutes'), $this->valueField('priority'), $this->valueField('is_active')];
    }

    public function editFields(): array
    {
        return [
            Text::make(__('admin.fields.code'), 'code')->required(),
            $this->nameField(),
            Select::make(__('admin.fields.kind'), 'kind')->options($this->kindOptions())->required(),
            Json::make(__('admin.fields.conditions'), 'conditions')->fields([
                Select::make(__('admin.fields.condition_field'), 'field')->options($this->conditionFields())->required(),
                Select::make(__('admin.fields.condition_operator'), 'operator')->options([
                    'lt' => '<', 'lte' => '≤', 'gt' => '>', 'gte' => '≥', 'eq' => '=', 'neq' => '≠',
                ])->required(),
                Text::make(__('admin.fields.condition_value'), 'value')->required(),
            ])->creatable(limit: 20)->removable()->hint(__('admin.history_conditions_hint'))
                ->onApply(static function (mixed $item, mixed $value): mixed {
                    if (! $item instanceof PetHistoryEvent) {
                        return $item;
                    }

                    /** @var list<array{field: string, operator: string, value: int|float|string|bool}> $conditions */
                    $conditions = $value ?? [];
                    $item->conditions = array_map(static function (array $condition): array {
                        $condition['value'] = match ($condition['field']) {
                            'has_disease' => (bool) $condition['value'],
                            'activity' => (string) $condition['value'],
                            default => (float) $condition['value'],
                        };

                        return $condition;
                    }, $conditions);

                    return $item;
                }),
            Number::make(__('admin.fields.cooldown_minutes'), 'cooldown_minutes')->min(0)->max(44640)->default(60)->hint(__('admin.history_cooldown_hint'))->required(),
            Number::make(__('admin.fields.priority'), 'priority')->min(0)->default(100)->required(),
            Switcher::make(__('admin.fields.is_active'), 'is_active')->default(true),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            ...$this->catalogueRules(),
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_.]*$/', Rule::unique('pet_history_events', 'code')->ignore($model->getKey())],
            'kind' => ['required', Rule::in(['action', 'thought'])],
            'conditions' => ['nullable', 'array', 'list', 'max:20', new ValidPetHistoryConditions],
            'conditions.*' => ['required', 'array:field,operator,value'],
            'conditions.*.field' => ['required', 'string'],
            'conditions.*.operator' => ['required', 'string'],
            'conditions.*.value' => ['required'],
            'cooldown_minutes' => ['required', 'integer', 'between:0,44640', 'min:'.(request('kind') === 'thought' ? 15 : 0)],
            'priority' => ['required', 'integer', 'between:0,2147483647'],
        ];
    }

    public function filterFields(): array
    {
        return [
            Select::make(__('admin.fields.kind'), 'kind')->options($this->kindOptions())->nullable(),
            Select::make(__('admin.fields.is_active'), 'is_active')->options([1 => __('admin.active'), 0 => __('admin.inactive')])->nullable(),
        ];
    }

    protected function search(): array
    {
        return ['id', 'code'];
    }

    /** @return array<string, string> */
    private function kindOptions(): array
    {
        return ['action' => __('admin.history_action'), 'thought' => __('admin.history_thought')];
    }

    /** @return array<string, string> */
    private function conditionFields(): array
    {
        $fields = [];

        foreach ([...ValidPetHistoryConditions::STATE_FIELDS, 'activity', 'has_disease'] as $field) {
            $fields[$field] = __('admin.history_condition_fields.'.$field);
        }

        return $fields;
    }
}
