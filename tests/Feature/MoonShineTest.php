<?php

use App\Models\AdminAuditLog;
use App\Models\CurrencyTransaction;
use App\Models\DogWorkShift;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemPurchase;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\ShopOffer;
use App\Models\User;
use App\Models\WorkShift;
use App\MoonShine\Enums\StaffRole;
use App\MoonShine\Queries\DashboardMetrics;
use App\MoonShine\Resources\AdminAuditLogResource;
use App\MoonShine\Resources\CurrencyTransactionResource;
use App\MoonShine\Resources\DogResource;
use App\MoonShine\Resources\DogWorkShiftResource;
use App\MoonShine\Resources\DogWorkTypeResource;
use App\MoonShine\Resources\GameAssetResource;
use App\MoonShine\Resources\ItemPurchaseResource;
use App\MoonShine\Resources\ItemResource;
use App\MoonShine\Resources\KennelPurchaseResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\PetCareActionResource;
use App\MoonShine\Resources\PetResource;
use App\MoonShine\Resources\PlayerResource;
use App\MoonShine\Resources\ShopOfferResource;
use App\MoonShine\Resources\SkillResource;
use App\MoonShine\Resources\StatusEffectResource;
use App\MoonShine\Resources\TrainingResource;
use App\MoonShine\Support\StaffAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Support\Enums\Ability;

function moonshineStaff(StaffRole $role = StaffRole::Administrator): MoonshineUser
{
    return MoonshineUser::factory()->create([
        'moonshine_user_role_id' => MoonshineUserRole::query()->where('code', $role->value)->value('id'),
    ]);
}

function moonshineUpdateUrl(string $resource, int $id): string
{
    return route('moonshine.crud.update', ['resourceUri' => app($resource)->getUriKey(), 'resourceItem' => $id]);
}

test('guests and game accounts must sign in through the separate staff guard', function () {
    $this->get(route('moonshine.index'))->assertRedirect(route('moonshine.login'));

    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('moonshine.index'))->assertRedirect(route('moonshine.login'));
});

test('staff can sign in using their assigned role', function (StaffRole $role) {
    $staff = moonshineStaff($role);
    $staff->forceFill(['password' => Hash::make('staff-password')])->save();

    $this->post(route('moonshine.authenticate'), ['username' => $staff->email, 'password' => 'staff-password'])
        ->assertRedirect(route('moonshine.index'));

    $this->assertAuthenticatedAs($staff, 'moonshine');
})->with(StaffRole::cases());

test('the dashboard shows the workspace appropriate to each role', function (StaffRole $role) {
    $this->actingAs(moonshineStaff($role), 'moonshine')->get(route('moonshine.index'))
        ->assertOk()->assertSee($role->label())->assertSee(__('admin.workspace'));
})->with(StaffRole::cases());

test('staff login uses the chosen language', function (string $locale, string $title, string $button) {
    $this->get(route('moonshine.login', ['_lang' => $locale]))
        ->assertOk()->assertSee($title)->assertSee($button);
})->with([
    'Russian' => ['ru', 'Вход в DogLive', 'Войти'],
    'English' => ['en', 'Welcome to DogLive!', 'Log in'],
]);

test('unknown roles cannot enter the staff workspace', function () {
    $custom = MoonshineUserRole::query()->create(['name' => 'Unassigned']);
    $staff = MoonshineUser::factory()->create(['moonshine_user_role_id' => $custom->getKey()]);

    $this->actingAs($staff, 'moonshine')->get(route('moonshine.index'))->assertForbidden();
});

test('permission groups grant only the operations assigned to each role', function (StaffRole $role, array $expected) {
    $staff = moonshineStaff($role);
    $models = [
        MoonshineUser::class, MoonshineUserRole::class, User::class, Pet::class,
        AdminAuditLog::class, CurrencyTransaction::class, Item::class,
    ];

    foreach ($models as $index => $model) {
        foreach ([Ability::VIEW_ANY, Ability::VIEW, Ability::CREATE, Ability::UPDATE, Ability::DELETE, Ability::MASS_DELETE] as $ability) {
            expect(StaffAccess::allows($model, $ability, $staff))
                ->toBe(in_array($ability->value, $expected[$index], true));
        }
    }
})->with([
    'administrator' => [StaffRole::Administrator, [
        ['viewAny', 'view', 'create', 'update'], ['viewAny', 'view'], ['viewAny', 'view', 'update'],
        ['viewAny', 'view'], ['viewAny', 'view'], ['viewAny', 'view'], ['viewAny', 'view', 'update'],
    ]],
    'analyst' => [StaffRole::Analyst, [[], [], [], [], [], ['viewAny', 'view'], ['viewAny', 'view']]],
    'moderator' => [StaffRole::Moderator, [[], [], ['viewAny', 'view', 'update'], ['viewAny', 'view'], ['viewAny', 'view'], [], []]],
]);

test('administrator can open every registered resource with actual data', function (string $resource) {
    $instance = app($resource);
    $model = $instance->getModel();
    $model::factory()->create();

    $this->actingAs(moonshineStaff(), 'moonshine')->get($instance->getIndexPage()->getUrl())->assertOk();
})->with([
    PlayerResource::class, PetResource::class, AdminAuditLogResource::class,
    CurrencyTransactionResource::class, ItemPurchaseResource::class, KennelPurchaseResource::class,
    PetCareActionResource::class, DogWorkShiftResource::class,
    DogResource::class, ItemResource::class, ShopOfferResource::class, GameAssetResource::class,
    TrainingResource::class, SkillResource::class, DogWorkTypeResource::class, StatusEffectResource::class,
    MoonShineUserResource::class,
]);

test('administrator can inspect the fixed staff role capabilities', function () {
    $this->actingAs(moonshineStaff(), 'moonshine')
        ->get(app(MoonShineUserRoleResource::class)->getIndexPage()->getUrl())
        ->assertOk()->assertSee('Администратор')->assertSee('Аналитик')->assertSee('Модератор');
});

test('administrator can open catalogue and moderation forms for existing records', function (string $resource) {
    $instance = app($resource);
    $model = $instance->getModel()::factory()->create();

    $this->actingAs(moonshineStaff(), 'moonshine')->get(route('moonshine.resource.page', [
        'resourceUri' => $instance->getUriKey(), 'pageUri' => $instance->getFormPage()->getUriKey(),
        'resourceItem' => $model->getKey(),
    ]))->assertOk()->assertSee('Сохранить');
})->with([
    PlayerResource::class, DogResource::class, ItemResource::class, ShopOfferResource::class,
    GameAssetResource::class, TrainingResource::class, SkillResource::class, DogWorkTypeResource::class,
    StatusEffectResource::class, MoonShineUserResource::class,
]);

test('analyst can filter wallet history by player without changing records', function () {
    $selected = CurrencyTransaction::factory()->create(['reason' => 'selected-player-operation']);
    $other = CurrencyTransaction::factory()->create(['reason' => 'other-player-operation']);
    $url = app(CurrencyTransactionResource::class)->getIndexPage()->getUrl();

    $this->actingAs(moonshineStaff(StaffRole::Analyst), 'moonshine')
        ->get($url.'?'.http_build_query(['filter' => ['user_id' => $selected->user_id]]))
        ->assertOk()->assertSee($selected->reason)->assertDontSee($other->reason);
});

test('analyst cannot read profiles or change catalogues through direct URLs', function () {
    $item = Item::factory()->create();
    $this->actingAs(moonshineStaff(StaffRole::Analyst), 'moonshine');

    $this->get(app(PlayerResource::class)->getIndexPage()->getUrl())->assertForbidden();
    $this->get(app(MoonShineUserResource::class)->getIndexPage()->getUrl())->assertForbidden();
    $this->put(moonshineUpdateUrl(ItemResource::class, $item->id), ['quality' => 10])->assertForbidden();
    expect($item->fresh()->quality)->toBe($item->quality);
});

test('moderator cannot read economic history or edit staff roles', function () {
    $staff = moonshineStaff();
    $this->actingAs(moonshineStaff(StaffRole::Moderator), 'moonshine');

    $this->get(app(CurrencyTransactionResource::class)->getIndexPage()->getUrl())->assertForbidden();
    $this->put(moonshineUpdateUrl(MoonShineUserResource::class, $staff->getKey()), ['moonshine_user_role_id' => 1])->assertForbidden();
});

test('moderation updates public fields and status with an audit entry and ignores privileged input', function () {
    $player = User::factory()->create(['coins' => 70, 'gems' => 3]);
    $moderator = moonshineStaff(StaffRole::Moderator);

    $this->actingAs($moderator, 'moonshine')
        ->put(moonshineUpdateUrl(PlayerResource::class, $player->id), [
            'name' => 'Reviewed name', 'bio' => 'Reviewed bio', 'status' => 'blocked',
            'moderation_reason' => 'Repeated profile violations.',
            'coins' => 999999, 'gems' => 999999, 'email' => 'attacker@example.com',
            'password' => 'attacker-password', 'moonshine_user_role_id' => 1,
        ])->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $player->id, 'name' => 'Reviewed name', 'status' => 'blocked', 'coins' => 70, 'gems' => 3, 'email' => $player->email, 'password' => $player->password]);
    $entry = AdminAuditLog::query()->sole();
    expect($entry->actor_id)->toBe($moderator->getKey());
    expect($entry->reason)->toBe('Repeated profile violations.');
    expect($entry->changes['status'])->toBe(['before' => 'active', 'after' => 'blocked']);
    expect($entry->changes)->not->toHaveKeys(['coins', 'password', 'email']);
});

test('moderation requires a valid status and an explanation', function (array $payload, string $field) {
    $player = User::factory()->create();

    $this->actingAs(moonshineStaff(StaffRole::Moderator), 'moonshine')
        ->put(moonshineUpdateUrl(PlayerResource::class, $player->id), [
            'name' => $player->name, 'status' => 'blocked', 'moderation_reason' => 'Reviewed profile.',
            ...$payload,
        ])->assertSessionHasErrors($field, null, app(PlayerResource::class)->getUriKey());

    expect($player->fresh()->status->value)->toBe('active');
    $this->assertDatabaseCount('admin_audit_logs', 0);
})->with([
    'missing explanation' => [['moderation_reason' => ''], 'moderation_reason'],
    'short explanation' => [['moderation_reason' => 'x'], 'moderation_reason'],
    'invalid status' => [['status' => 'administrator'], 'status'],
]);

test('moderation can restore a blocked player', function () {
    $player = User::factory()->create(['status' => 'blocked']);

    $this->actingAs(moonshineStaff(StaffRole::Moderator), 'moonshine')
        ->put(moonshineUpdateUrl(PlayerResource::class, $player->id), [
            'name' => $player->name, 'status' => 'active', 'moderation_reason' => 'Appeal reviewed and approved.',
        ])->assertRedirect();

    expect($player->fresh()->status->value)->toBe('active');
});

test('player changes roll back if the audit entry cannot be saved', function () {
    $player = User::factory()->create();
    if (DB::getDriverName() === 'pgsql') {
        DB::unprepared("CREATE FUNCTION reject_admin_audit() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''Simulated failure''; END'; CREATE TRIGGER reject_admin_audit BEFORE INSERT ON admin_audit_logs FOR EACH ROW EXECUTE FUNCTION reject_admin_audit()");
    } else {
        DB::statement("CREATE TRIGGER reject_admin_audit BEFORE INSERT ON admin_audit_logs BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END");
    }

    $this->actingAs(moonshineStaff(StaffRole::Moderator), 'moonshine')
        ->put(moonshineUpdateUrl(PlayerResource::class, $player->id), [
            'name' => 'Changed name', 'status' => 'blocked', 'moderation_reason' => 'Reviewed profile violation.',
        ])->assertInternalServerError();

    expect($player->fresh()->name)->toBe($player->name);
    expect($player->fresh()->status->value)->toBe('active');
    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('administrator can create an analyst with a hashed password and a redacted audit', function () {
    $role = MoonshineUserRole::query()->where('code', 'analyst')->firstOrFail();

    $this->actingAs(moonshineStaff(), 'moonshine')->post(route('moonshine.crud.store', [
        'resourceUri' => app(MoonShineUserResource::class)->getUriKey(),
    ]), [
        'name' => 'Reporting colleague', 'email' => 'analyst@example.com',
        'moonshine_user_role_id' => $role->getKey(),
        'password' => 'strong-password-123', 'password_confirmation' => 'strong-password-123',
    ])->assertRedirect();

    $analyst = MoonshineUser::query()->where('email', 'analyst@example.com')->firstOrFail();
    expect(Hash::check('strong-password-123', $analyst->password))->toBeTrue();
    expect(StaffAccess::role($analyst))->toBe(StaffRole::Analyst);
    expect(AdminAuditLog::query()->sole()->changes['password'])->toBe(['before' => '[redacted]', 'after' => '[redacted]']);
});

test('administrator cannot change their own role or delete access roles', function () {
    $staff = moonshineStaff();
    $role = MoonshineUserRole::query()->where('code', 'analyst')->firstOrFail();

    $this->actingAs($staff, 'moonshine')->put(moonshineUpdateUrl(MoonShineUserResource::class, $staff->getKey()), [
        'name' => $staff->name, 'email' => $staff->email, 'moonshine_user_role_id' => $role->getKey(),
    ])->assertSessionHasErrors('moonshine_user_role_id', null, app(MoonShineUserResource::class)->getUriKey());

    $this->delete(route('moonshine.crud.destroy', [
        'resourceUri' => app(MoonShineUserRoleResource::class)->getUriKey(), 'resourceItem' => 1,
    ]))->assertForbidden();
    expect(StaffAccess::role($staff->fresh()))->toBe(StaffRole::Administrator);
    $this->assertModelExists($staff);
});

test('administrator cannot alter or remove historical wallet records', function () {
    $transaction = CurrencyTransaction::factory()->create();
    $this->actingAs(moonshineStaff(), 'moonshine');

    $this->put(moonshineUpdateUrl(CurrencyTransactionResource::class, $transaction->id), ['amount' => 10000])->assertForbidden();
    $this->delete(route('moonshine.crud.destroy', [
        'resourceUri' => app(CurrencyTransactionResource::class)->getUriKey(), 'resourceItem' => $transaction->id,
    ]))->assertForbidden();
    expect($transaction->fresh()->amount)->toBe(100);
});

test('administrator updates shop offers with validation and audit history', function () {
    $offer = ShopOffer::factory()->create(['price' => 50, 'stock' => 2]);

    $this->actingAs(moonshineStaff(), 'moonshine')->put(moonshineUpdateUrl(ShopOfferResource::class, $offer->id), [
        'price' => 75, 'stock' => '', 'sort_order' => 0, 'is_active' => 1,
    ])->assertRedirect();

    expect($offer->fresh()->price)->toBe(75);
    expect($offer->fresh()->stock)->toBeNull();
    expect(AdminAuditLog::query()->sole()->changes['price'])->toBe(['before' => 50, 'after' => 75]);
});

test('invalid shop prices cannot change the offer', function () {
    $offer = ShopOffer::factory()->create();

    $this->actingAs(moonshineStaff(), 'moonshine')->put(moonshineUpdateUrl(ShopOfferResource::class, $offer->id), [
        'price' => 0, 'stock' => -1, 'sort_order' => 0, 'is_active' => 1,
    ])->assertSessionHasErrors(['price', 'stock'], null, app(ShopOfferResource::class)->getUriKey());

    expect($offer->fresh()->price)->toBe($offer->price);
    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('catalogue changes save both translations and preserve purchased item snapshots', function () {
    $item = Item::factory()->create(['quality' => 3, 'usage_limit' => 5]);
    $owned = InventoryItem::factory()->for($item)->create();
    $snapshot = $owned->fresh()->getAttributes();

    $this->actingAs(moonshineStaff(), 'moonshine')->put(moonshineUpdateUrl(ItemResource::class, $item->id), [
        'name' => ['ru' => 'Новый мяч', 'en' => 'New ball'],
        'quality' => 7, 'usage_limit' => 10, 'is_active' => 0,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($item->fresh()->name)->toBe(['ru' => 'Новый мяч', 'en' => 'New ball']);
    expect($item->fresh()->quality)->toBe(7);
    expect($item->fresh()->is_active)->toBeFalse();
    expect($owned->fresh()->getAttributes())->toBe($snapshot);
    expect(AdminAuditLog::query()->sole()->target_type)->toBe(Item::class);
});

test('invalid translations and item limits cannot change a catalogue record', function () {
    $item = Item::factory()->create();

    $this->actingAs(moonshineStaff(), 'moonshine')->put(moonshineUpdateUrl(ItemResource::class, $item->id), [
        'name' => ['ru' => '', 'en' => 'Ball', 'fr' => 'Extra translation'],
        'quality' => 11, 'usage_limit' => 0, 'is_active' => 1,
    ])->assertSessionHasErrors(['name', 'name.ru', 'quality', 'usage_limit'], null, app(ItemResource::class)->getUriKey());

    expect($item->fresh()->name)->toBe($item->name);
    expect($item->fresh()->quality)->toBe($item->quality);
    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('moderator sees only moderation audit entries including when opening a detail directly', function () {
    $private = AdminAuditLog::factory()->create(['target_type' => Item::class, 'target_id' => Item::factory(), 'reason' => 'Private catalogue adjustment.']);
    $public = AdminAuditLog::factory()->create(['reason' => 'Profile complaint reviewed.']);
    $resource = app(AdminAuditLogResource::class);
    $this->actingAs(moonshineStaff(StaffRole::Moderator), 'moonshine');

    $this->get($resource->getIndexPage()->getUrl())->assertSee($public->reason)->assertDontSee($private->reason);
    $this->get(route('moonshine.resource.page', [
        'resourceUri' => $resource->getUriKey(), 'pageUri' => $resource->getDetailPage()->getUriKey(), 'resourceItem' => $private->id,
    ]))->assertNotFound();
});

test('record snapshots and moderation reasons escape user supplied HTML', function () {
    $player = User::factory()->create(['name' => '<script>alert(1)</script>']);
    $audit = AdminAuditLog::factory()->create(['reason' => '<script>alert(2)</script>']);
    $this->actingAs(moonshineStaff(), 'moonshine');

    $this->get(route('moonshine.resource.page', [
        'resourceUri' => app(PlayerResource::class)->getUriKey(),
        'pageUri' => app(PlayerResource::class)->getDetailPage()->getUriKey(), 'resourceItem' => $player->id,
    ]))->assertDontSee($player->name, false)->assertSee('&lt;script&gt;', false);
    $this->get(route('moonshine.index'))->assertDontSee($audit->reason, false)->assertSee('&lt;script&gt;', false);
});

test('analytics use the selected period and count each participant once across game operations', function () {
    $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 0));
    $first = User::factory()->create();
    $second = User::factory()->create();
    $old = User::factory()->create(['created_at' => now()->subDays(20)]);
    CurrencyTransaction::factory()->for($first)->create();
    CurrencyTransaction::factory()->for($first)->create(['amount' => -30, 'balance_before' => 100, 'balance_after' => 70]);
    CurrencyTransaction::factory()->for($first)->create(['currency' => 'gems', 'amount' => 5, 'balance_after' => 5]);
    CurrencyTransaction::factory()->for($old)->create(['created_at' => now()->subDays(20)]);
    CurrencyTransaction::factory()->for($first)->create(['created_at' => now()->addDay()]);
    WorkShift::factory()->for($second)->create();
    PetCareAction::factory()->create(['pet_id' => Pet::factory()->for($first), 'user_id' => $first->id]);
    ItemPurchase::factory()->for($first)->create(['price_paid' => 25]);

    $metrics = app(DashboardMetrics::class)->get(StaffRole::Analyst, 7);

    expect($metrics['period']['registrations'])->toBe(2);
    expect($metrics['period']['active_players'])->toBe(2);
    expect($metrics['period']['shop_spent'])->toBe(25);
    expect($metrics['economy']['coins'])->toBe(['issued' => 100, 'spent' => 30]);
    expect($metrics['economy']['gems'])->toBe(['issued' => 5, 'spent' => 0]);
    expect($metrics['registrations'])->toHaveCount(7)->toMatchArray(['2026-09-26' => 0, '2026-10-02' => 2]);
    expect($metrics['system'])->toBe([]);
    expect($metrics['recent'])->toBe([]);
});

test('invalid dashboard periods are rejected', function () {
    $this->actingAs(moonshineStaff(StaffRole::Analyst), 'moonshine')
        ->get(route('moonshine.index', ['days' => 100000]))->assertSessionHasErrors('days');
});

test('pending results are all time counts independent of the analytics period', function () {
    $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 0));
    PetCareAction::factory()->create(['created_at' => now()->subDays(20), 'ends_at' => now()->subDays(19)]);
    PetCareAction::factory()->create(['ends_at' => now()->subHour(), 'completed_at' => now()]);
    PetCareAction::factory()->create(['ends_at' => now()->addHour()]);
    DogWorkShift::factory()->create(['created_at' => now()->subDays(20), 'ends_at' => now()->subDays(19)]);

    $metrics = app(DashboardMetrics::class)->get(StaffRole::Analyst, 7);

    expect($metrics['period']['care'])->toBe(2);
    expect($metrics['period']['shifts'])->toBe(0);
    expect($metrics['waiting'])->toBe(['ready_care' => 1, 'ready_shifts' => 1]);
});
