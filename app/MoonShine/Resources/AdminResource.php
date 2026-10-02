<?php

namespace App\MoonShine\Resources;

use App\MoonShine\Pages\AdminDetailPage;
use App\MoonShine\Pages\AdminFormPage;
use App\MoonShine\Pages\AdminIndexPage;
use App\MoonShine\Traits\AuditsAdminChanges;
use BackedEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @template TModel of Model
 *
 * @extends ModelResource<TModel, AdminIndexPage, AdminFormPage, AdminDetailPage>
 */
abstract class AdminResource extends ModelResource
{
    use AuditsAdminChanges;

    protected string $titleKey;

    protected string $sortColumn = 'id';

    protected bool $simplePaginate = true;

    protected bool $editable = false;

    public function getTitle(): string
    {
        return __('admin.resources.'.$this->titleKey);
    }

    protected function pages(): array
    {
        return [AdminIndexPage::class, AdminFormPage::class, AdminDetailPage::class];
    }

    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, $this->editable ? [Action::VIEW, Action::UPDATE] : [Action::VIEW]);
    }

    /** @return list<FieldContract> */
    abstract public function listFields(): array;

    /** @return list<FieldContract> */
    public function editFields(): array
    {
        return [];
    }

    /** @return list<FieldContract> */
    public function viewFields(): array
    {
        return [
            ...$this->listFields(),
            Preview::make(__('admin.snapshot'), 'snapshot', static fn (Model $model): HtmlString => new HtmlString('<pre class="admin-json">'.e(json_encode($model->attributesToArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)).'</pre>')
            ),
        ];
    }

    /** @return list<FieldContract> */
    public function filterFields(): array
    {
        $fields = [DateRange::make(__('admin.created'), 'created_at')];
        foreach (['user_id', 'pet_id', 'dog_id'] as $column) {
            if ($this->getModel()->isFillable($column)) {
                $fields[] = Number::make(__('admin.fields.'.$column), $column)->min(1);
            }
        }

        return $fields;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function validationRules(Model $model): array
    {
        return [];
    }

    protected function search(): array
    {
        return ['id'];
    }

    protected function valueField(string $column, ?string $label = null): Preview
    {
        return Preview::make($label ?? __('admin.fields.'.$column), $column, static function (Model $model) use ($column): string {
            $value = data_get($model, $column);
            $value = $value instanceof BackedEnum ? $value->value : $value;

            if (is_array($value) && array_key_exists('ru', $value)) {
                $value = $value[app()->getLocale()] ?? $value['en'] ?? $value['ru'];
            }

            if (is_bool($value)) {
                return e(__($value ? 'admin.active' : 'admin.inactive'));
            }

            if ($column === 'status' && in_array($value, ['active', 'blocked'], true)) {
                return e(__('admin.'.$value));
            }

            return e(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : (string) $value);
        });
    }

    /** @return list<FieldContract> */
    protected function catalogueFields(): array
    {
        return [
            ID::make(),
            $this->valueField('code'),
            $this->nameField(),
            Switcher::make(__('admin.fields.is_active'), 'is_active'),
        ];
    }

    protected function nameField(): Json
    {
        return Json::make(__('admin.fields.name'), 'name')->fields([
            Text::make(__('admin.name_ru'), 'ru')->required(),
            Text::make(__('admin.name_en'), 'en')->required(),
        ])->object();
    }

    /** @return array<string, list<string>> */
    protected function catalogueRules(): array
    {
        return [
            'name' => ['required', 'array:ru,en'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
