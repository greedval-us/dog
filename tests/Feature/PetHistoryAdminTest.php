<?php

use App\Models\PetHistoryEvent;
use App\Models\PetHistoryPhrase;
use App\MoonShine\Enums\StaffRole;
use App\MoonShine\Resources\PetHistoryEventResource;
use App\MoonShine\Resources\PetHistoryPhraseResource;
use App\MoonShine\Support\StaffAccess;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Support\Enums\Ability;

function petHistoryStaff(StaffRole $role = StaffRole::Administrator): MoonshineUser
{
    return MoonshineUser::factory()->create([
        'moonshine_user_role_id' => MoonshineUserRole::query()->where('code', $role->value)->value('id'),
    ]);
}

/** @return array<string, mixed> */
function petHistoryEventPayload(): array
{
    return [
        'code' => 'custom_hunger', 'kind' => 'thought',
        'name' => ['ru' => 'Хочу есть', 'en' => 'Hungry'],
        'conditions' => [
            ['field' => 'satiety', 'operator' => 'lte', 'value' => '30.5'],
            ['field' => 'activity', 'operator' => 'eq', 'value' => 'idle'],
            ['field' => 'has_disease', 'operator' => 'eq', 'value' => '0'],
        ],
        'cooldown_minutes' => 120, 'priority' => 50, 'is_active' => 1,
    ];
}

/** @param class-string $resource */
function petHistoryStoreUrl(string $resource): string
{
    return route('moonshine.crud.store', ['resourceUri' => app($resource)->getUriKey()]);
}

/** @param class-string $resource */
function petHistoryUpdateUrl(string $resource, int $id): string
{
    return route('moonshine.crud.update', ['resourceUri' => app($resource)->getUriKey(), 'resourceItem' => $id]);
}

test('history catalogues grant administrators creation and updates and analysts read access', function (StaffRole $role, array $allowed) {
    $staff = petHistoryStaff($role);

    foreach ([PetHistoryEvent::class, PetHistoryPhrase::class] as $model) {
        foreach ([Ability::VIEW_ANY, Ability::VIEW, Ability::CREATE, Ability::UPDATE, Ability::DELETE, Ability::MASS_DELETE] as $ability) {
            expect(StaffAccess::allows($model, $ability, $staff))->toBe(in_array($ability->value, $allowed, true));
        }
    }
})->with([
    'administrator' => [StaffRole::Administrator, ['viewAny', 'view', 'create', 'update']],
    'analyst' => [StaffRole::Analyst, ['viewAny', 'view']],
    'moderator' => [StaffRole::Moderator, []],
]);

test('guests must sign in to open history catalogues', function () {
    $this->get(app(PetHistoryEventResource::class)->getIndexPage()->getUrl())
        ->assertRedirect(route('moonshine.login'));
});

test('administrators can open event and phrase creation forms', function (string $resource) {
    $instance = app($resource);

    $this->actingAs(petHistoryStaff(), 'moonshine')->get($instance->getFormPage()->getUrl())
        ->assertOk()->assertSee('Сохранить');
})->with([PetHistoryEventResource::class, PetHistoryPhraseResource::class]);

test('administrators create configurable thoughts with typed conditions and an audit record', function () {
    $staff = petHistoryStaff();

    $this->actingAs($staff, 'moonshine')->post(petHistoryStoreUrl(PetHistoryEventResource::class), petHistoryEventPayload())
        ->assertRedirect()->assertSessionHasNoErrors();

    $event = PetHistoryEvent::query()->where('code', 'custom_hunger')->sole();
    expect($event->name)->toBe(['ru' => 'Хочу есть', 'en' => 'Hungry']);
    expect($event->conditions)->toBe([
        ['field' => 'satiety', 'operator' => 'lte', 'value' => 30.5],
        ['field' => 'activity', 'operator' => 'eq', 'value' => 'idle'],
        ['field' => 'has_disease', 'operator' => 'eq', 'value' => false],
    ]);
    $this->assertDatabaseHas('admin_audit_logs', [
        'actor_id' => $staff->getKey(), 'target_type' => PetHistoryEvent::class, 'target_id' => $event->id, 'action' => 'created',
    ]);
});

test('administrators update thought conditions without a duplicate code error', function () {
    $event = PetHistoryEvent::factory()->create(['code' => 'custom_hunger', 'kind' => 'thought']);

    $this->actingAs(petHistoryStaff(), 'moonshine')->put(petHistoryUpdateUrl(PetHistoryEventResource::class, $event->id), [
        ...petHistoryEventPayload(), 'conditions' => [], 'is_active' => 0,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($event->fresh()->conditions)->toBe([]);
    expect($event->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('admin_audit_logs', ['target_type' => PetHistoryEvent::class, 'target_id' => $event->id, 'action' => 'updated']);
});

test('invalid condition fields comparisons and values cannot create events', function (array $condition) {
    $this->actingAs(petHistoryStaff(), 'moonshine')->post(petHistoryStoreUrl(PetHistoryEventResource::class), [
        ...petHistoryEventPayload(), 'conditions' => [$condition],
    ])->assertSessionHasErrors(['conditions' => __('admin.history_invalid_condition')], null, app(PetHistoryEventResource::class)->getUriKey());

    $this->assertDatabaseMissing('pet_history_events', ['code' => 'custom_hunger']);
    $this->assertDatabaseCount('admin_audit_logs', 0);
})->with([
    'unknown field' => [['field' => 'coins', 'operator' => 'lt', 'value' => 10]],
    'unknown operator' => [['field' => 'satiety', 'operator' => 'contains', 'value' => 10]],
    'negative state' => [['field' => 'satiety', 'operator' => 'lt', 'value' => -1]],
    'state over one hundred percent' => [['field' => 'energy', 'operator' => 'gte', 'value' => 101]],
    'non numeric state' => [['field' => 'mood', 'operator' => 'lt', 'value' => 'low']],
    'unsupported activity' => [['field' => 'activity', 'operator' => 'eq', 'value' => 'shopping']],
    'ordered activity comparison' => [['field' => 'activity', 'operator' => 'lt', 'value' => 'idle']],
    'invalid boolean' => [['field' => 'has_disease', 'operator' => 'eq', 'value' => 'yes']],
    'ordered disease comparison' => [['field' => 'has_disease', 'operator' => 'gt', 'value' => 1]],
]);

test('event validation rejects duplicate codes and a thought without a cooldown', function () {
    PetHistoryEvent::factory()->create(['code' => 'custom_hunger']);

    $this->actingAs(petHistoryStaff(), 'moonshine')->post(petHistoryStoreUrl(PetHistoryEventResource::class), [
        ...petHistoryEventPayload(), 'cooldown_minutes' => 0,
    ])->assertSessionHasErrors(['code', 'cooldown_minutes'], null, app(PetHistoryEventResource::class)->getUriKey());

    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('administrators add alternate phrases and update both translations', function () {
    $event = PetHistoryEvent::factory()->create();
    $this->actingAs(petHistoryStaff(), 'moonshine');

    $this->post(petHistoryStoreUrl(PetHistoryPhraseResource::class), [
        'pet_history_event_id' => $event->id, 'text' => ['ru' => 'Хозяин, хочу есть!', 'en' => 'I am hungry!'],
        'sort_order' => 2, 'is_active' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $phrase = PetHistoryPhrase::query()->where('pet_history_event_id', $event->id)->sole();
    expect($phrase->text)->toBe(['ru' => 'Хозяин, хочу есть!', 'en' => 'I am hungry!']);
    $this->assertDatabaseHas('admin_audit_logs', ['target_type' => PetHistoryPhrase::class, 'target_id' => $phrase->id, 'action' => 'created']);

    $this->put(petHistoryUpdateUrl(PetHistoryPhraseResource::class, $phrase->id), [
        'pet_history_event_id' => $event->id, 'text' => ['ru' => 'Покорми меня!', 'en' => 'Please feed me!'],
        'sort_order' => 3, 'is_active' => 0,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($phrase->fresh()->text)->toBe(['ru' => 'Покорми меня!', 'en' => 'Please feed me!']);
    expect($phrase->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('admin_audit_logs', ['target_type' => PetHistoryPhrase::class, 'target_id' => $phrase->id, 'action' => 'updated']);
});

test('invalid phrase translations and events do not save a phrase', function () {
    $this->actingAs(petHistoryStaff(), 'moonshine')->post(petHistoryStoreUrl(PetHistoryPhraseResource::class), [
        'pet_history_event_id' => 999999, 'text' => ['ru' => '', 'en' => 'Hungry', 'fr' => 'Extra'],
        'sort_order' => -1, 'is_active' => 1,
    ])->assertSessionHasErrors(['pet_history_event_id', 'text', 'text.ru', 'sort_order'], null, app(PetHistoryPhraseResource::class)->getUriKey());

    $this->assertDatabaseCount('pet_history_phrases', 0);
    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('phrase order must be unique within its event', function () {
    $phrase = PetHistoryPhrase::factory()->create(['sort_order' => 2]);

    $this->actingAs(petHistoryStaff(), 'moonshine')->post(petHistoryStoreUrl(PetHistoryPhraseResource::class), [
        'pet_history_event_id' => $phrase->pet_history_event_id, 'text' => ['ru' => 'Хочу есть!', 'en' => 'Hungry!'],
        'sort_order' => 2, 'is_active' => 1,
    ])->assertSessionHasErrors('sort_order', null, app(PetHistoryPhraseResource::class)->getUriKey());

    $this->assertDatabaseCount('pet_history_phrases', 1);
    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('phrase previews escape HTML from the catalogue', function () {
    $phrase = PetHistoryPhrase::factory()->create(['text' => ['ru' => '<script>alert(1)</script>', 'en' => 'Hungry!']]);

    $this->actingAs(petHistoryStaff(), 'moonshine')->get(app(PetHistoryPhraseResource::class)->getIndexPage()->getUrl())
        ->assertDontSee($phrase->text['ru'], false)->assertSee('&lt;script&gt;', false);
});

test('analysts can read history catalogues but cannot create or update them', function () {
    $event = PetHistoryEvent::factory()->create();
    PetHistoryPhrase::factory()->create(['pet_history_event_id' => $event->id]);
    $this->actingAs(petHistoryStaff(StaffRole::Analyst), 'moonshine');

    $this->get(app(PetHistoryEventResource::class)->getIndexPage()->getUrl())->assertOk();
    $this->get(app(PetHistoryPhraseResource::class)->getIndexPage()->getUrl())->assertOk();
    $this->post(petHistoryStoreUrl(PetHistoryEventResource::class), petHistoryEventPayload())->assertForbidden();
    $this->put(petHistoryUpdateUrl(PetHistoryEventResource::class, $event->id), petHistoryEventPayload())->assertForbidden();

    expect($event->fresh()->name)->toBe($event->name);
    $this->assertDatabaseMissing('pet_history_events', ['code' => 'custom_hunger']);
    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('moderators cannot access history catalogues directly', function () {
    $this->actingAs(petHistoryStaff(StaffRole::Moderator), 'moonshine')
        ->get(app(PetHistoryEventResource::class)->getIndexPage()->getUrl())->assertForbidden();
});
