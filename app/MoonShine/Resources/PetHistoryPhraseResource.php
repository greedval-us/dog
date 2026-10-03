<?php

namespace App\MoonShine\Resources;

use App\Models\PetHistoryEvent;
use App\Models\PetHistoryPhrase;
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
use MoonShine\UI\Fields\Textarea;

/** @extends AdminResource<PetHistoryPhrase> */
#[Icon('chat-bubble-left-ellipsis')]
final class PetHistoryPhraseResource extends AdminResource
{
    protected string $model = PetHistoryPhrase::class;

    protected string $titleKey = 'history_phrases';

    protected string $column = 'id';

    protected bool $editable = true;

    protected array $with = ['event'];

    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, [Action::VIEW, Action::CREATE, Action::UPDATE]);
    }

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('event.code', __('admin.fields.pet_history_event_id')),
            $this->valueField('text'), $this->valueField('sort_order'), $this->valueField('is_active')];
    }

    public function editFields(): array
    {
        return [
            Select::make(__('admin.fields.pet_history_event_id'), 'pet_history_event_id')->options($this->eventOptions())->searchable()->required(),
            Json::make(__('admin.fields.text'), 'text')->fields([
                Textarea::make(__('admin.text_ru'), 'ru')->required(),
                Textarea::make(__('admin.text_en'), 'en')->required(),
            ])->object(),
            Number::make(__('admin.fields.sort_order'), 'sort_order')->min(0)->default(0)->required(),
            Switcher::make(__('admin.fields.is_active'), 'is_active')->default(true),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            'pet_history_event_id' => ['required', 'integer', 'exists:pet_history_events,id'],
            'text' => ['required', 'array:ru,en'],
            'text.ru' => ['required', 'string', 'max:1000'],
            'text.en' => ['required', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'between:0,2147483647',
                Rule::unique('pet_history_phrases', 'sort_order')->where('pet_history_event_id', request()->integer('pet_history_event_id'))->ignore($model->getKey())],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function filterFields(): array
    {
        return [
            Select::make(__('admin.fields.pet_history_event_id'), 'pet_history_event_id')->options($this->eventOptions())->searchable()->nullable(),
            Select::make(__('admin.fields.is_active'), 'is_active')->options([1 => __('admin.active'), 0 => __('admin.inactive')])->nullable(),
        ];
    }

    /** @return array<int, string> */
    private function eventOptions(): array
    {
        return PetHistoryEvent::query()->orderBy('code')->pluck('code', 'id')->all();
    }
}
