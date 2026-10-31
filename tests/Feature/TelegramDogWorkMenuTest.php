<?php

use App\Http\Telegram\Menus\DogWorkMenu;
use App\Models\DogWorkBoard;
use App\Models\DogWorkOffer;
use App\Models\DogWorkShift;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/** @return list<array<string, mixed>> */
function telegramWorkCommands(TelegramScreen $screen, ?string $action = null): array
{
    return collect($screen->rows)->flatten(1)->pluck('command')->filter()
        ->when($action !== null, fn ($commands) => $commands->where('action', $action))->values()->all();
}

function telegramWorkReadyPet(DogWorkOffer $offer): Pet
{
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 0, 'gems' => 0]))
        ->create(['name' => '<Rex>', 'intelligence' => 100, 'obedience' => 100, 'energy' => 100]);
    $pet->skills()->attach($offer->required_skill_id, ['level' => $offer->required_skill_level]);

    return $pet;
}

test('telegram work confirms terms and completes the accepted reward once using the shift token', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create(['name' => ['en' => '<Search>'], 'description' => ['en' => '<Find>'], 'gems_reward' => 2]);
    $pet = telegramWorkReadyPet($offer);
    $menu = app(DogWorkMenu::class);

    $confirmation = $menu->offer($pet->user, 'en', ['action' => 'work.offer', 'petId' => $pet->id, 'offerId' => $offer->id]);

    expect($confirmation->text)->toContain('&lt;Search&gt;', '&lt;Find&gt;', '150 coins', '2 gems', '15 sec', 'Energy: 10', '20 / 20')
        ->not->toContain('<Search>', '<Find>');
    expect(Str::isUuid($confirmation->state['work']['token']))->toBeTrue();
    expect(telegramWorkCommands($confirmation, 'work.start'))->toHaveCount(1);

    $started = $menu->start($pet->user, 'en', $confirmation->state);
    $menu->start($pet->user, 'en', $confirmation->state);
    $shift = DogWorkShift::query()->sole();

    expect($shift->token)->toBe($confirmation->state['work']['token'])->not->toBe($shift->activity_token);
    expect($started->text)->toContain('In progress', '&lt;Rex&gt;', '150 coins');
    expect(telegramWorkCommands($started, 'work.complete'))->toBe([['action' => 'work.complete', 'token' => $shift->token]]);
    $this->assertDatabaseCount('dog_work_shifts', 1);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 90, 'activity' => 'work']);
    $this->assertDatabaseHas('dog_work_offers', ['id' => $offer->id, 'reserved_count' => 1]);

    $pending = $menu->complete($pet->user, 'en', ['action' => 'work.complete', 'token' => $shift->token]);

    expect($pending->text)->toContain('This job has not finished yet.', 'In progress');
    $this->assertDatabaseCount('currency_transactions', 0);

    $offer->update(['coins_reward' => 999, 'gems_reward' => 5]);
    $this->travelTo($shift->ends_at);
    $completed = $menu->complete($pet->user, 'en', ['action' => 'work.complete', 'token' => $shift->token]);
    $again = $menu->complete($pet->user, 'en', ['action' => 'work.complete', 'token' => $shift->token]);

    expect($completed->text)->toContain('Completed', 'Received: 150 coins', '2 gems')->not->toContain('999');
    expect($again->text)->toBe($completed->text);
    expect(telegramWorkCommands($completed, 'work.complete'))->toBeEmpty();
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 150, 'gems' => 2]);
    $this->assertDatabaseCount('currency_transactions', 2);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity' => null]);
});

test('telegram work explains unavailable offers without offering to start them', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = Pet::factory()->create();

    $screen = app(DogWorkMenu::class)->offer($pet->user, 'en', ['action' => 'work.offer', 'petId' => $pet->id, 'offerId' => $offer->id]);

    expect($screen->text)->toContain('Your dog needs the required skill level.');
    expect(telegramWorkCommands($screen, 'work.start'))->toBeEmpty();
    expect($screen->state)->toBeEmpty();
    $this->assertDatabaseCount('dog_work_shifts', 0);
});

test('telegram work keeps terms and accepted rewards visible when names and descriptions are long', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create(['name' => ['en' => str_repeat('&', 250)],
        'description' => ['en' => str_repeat('<', 2000)], 'required_skill_name' => ['en' => str_repeat('>', 250)]]);
    $pet = telegramWorkReadyPet($offer);
    $menu = app(DogWorkMenu::class);

    $confirmation = $menu->offer($pet->user, 'en', ['action' => 'work.offer', 'petId' => $pet->id, 'offerId' => $offer->id]);
    $started = $menu->start($pet->user, 'en', $confirmation->state);

    expect($confirmation->text)->toContain('Reward: 150 coins', 'Duration: 15 sec', 'Start this job with your dog?');
    expect($started->text)->toContain('In progress', 'Reward: 150 coins');
});

test('telegram work refuses a confirmed offer after the work day changes', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = telegramWorkReadyPet($offer);
    $menu = app(DogWorkMenu::class);
    $confirmation = $menu->offer($pet->user, 'en', ['action' => 'work.offer', 'petId' => $pet->id, 'offerId' => $offer->id]);
    $this->travel(1)->day();

    $screen = $menu->start($pet->user, 'en', $confirmation->state);

    expect($screen->text)->toContain('This job is no longer available. Refresh the board.');
    $this->assertDatabaseCount('dog_work_shifts', 0);
    $this->assertDatabaseHas('dog_work_offers', ['id' => $offer->id, 'reserved_count' => 0]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 0, 'gems' => 0]);
});

test('telegram work pages daily offers without dropping the selected dog', function () {
    $this->freezeSecond();
    $board = DogWorkBoard::factory()->create();
    $offers = DogWorkOffer::factory()->count(8)->create(['dog_work_board_id' => $board->id]);
    $pet = Pet::factory()->create();
    $menu = app(DogWorkMenu::class);

    $first = $menu->index($pet->user, 'en', ['action' => 'work', 'petId' => $pet->id]);
    $next = collect(telegramWorkCommands($first, 'work'))->firstWhere('offset', 6);
    $second = $menu->index($pet->user, 'en', $next);

    expect(telegramWorkCommands($first, 'work.offer'))->toHaveCount(6);
    expect($next)->toMatchArray(['petId' => $pet->id, 'offset' => 6]);
    expect(array_column(telegramWorkCommands($second, 'work.offer'), 'offerId'))->toBe([$offers[6]->id, $offers[7]->id]);
});

test('telegram accepted work pages owned shift snapshots and keeps their receipt tokens', function () {
    $this->freezeSecond();
    $board = DogWorkBoard::factory()->create();
    $offers = DogWorkOffer::factory()->count(8)->create(['dog_work_board_id' => $board->id]);
    $pet = Pet::factory()->create();
    $tokens = [];
    foreach ($offers as $offer) {
        $shift = DogWorkShift::factory()->create(['user_id' => $pet->user_id, 'pet_id' => $pet->id, 'dog_work_offer_id' => $offer->id,
            'name' => ['en' => 'Accepted snapshot'], 'completed_at' => now()]);
        $tokens[] = $shift->token;
    }
    $foreign = DogWorkShift::factory()->create(['dog_work_offer_id' => $offers[0]->id]);
    $menu = app(DogWorkMenu::class);

    $first = $menu->index($pet->user, 'en', ['action' => 'work', 'petId' => $pet->id, 'tab' => 'shifts']);
    $next = collect(telegramWorkCommands($first, 'work'))->firstWhere('offset', 6);
    $second = $menu->index($pet->user, 'en', $next);

    expect(array_column(telegramWorkCommands($first, 'work.complete'), 'token'))->toBe(array_reverse(array_slice($tokens, 2)))
        ->not->toContain($foreign->token);
    expect($first->rows[0][0]['text'])->toContain('Accepted snapshot', 'Completed');
    expect($next)->toMatchArray(['petId' => $pet->id, 'tab' => 'shifts', 'offset' => 6]);
    expect(array_column(telegramWorkCommands($second, 'work.complete'), 'token'))->toBe([$tokens[1], $tokens[0]]);
});

test('telegram work refuses another players pet and receipt', function () {
    $this->freezeSecond();
    $shift = DogWorkShift::factory()->create();
    $user = User::factory()->create();
    $menu = app(DogWorkMenu::class);

    expect(fn () => $menu->index($user, 'en', ['action' => 'work', 'petId' => $shift->pet_id]))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $menu->index($user, 'en', ['action' => 'work', 'petId' => $shift->pet_id, 'tab' => 'shifts']))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $menu->complete($user, 'en', ['action' => 'work.complete', 'token' => $shift->token]))
        ->toThrow(ModelNotFoundException::class);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram work renders cancelled snapshots without a completion button or payout', function () {
    $this->freezeSecond();
    $shift = DogWorkShift::factory()->create(['cancelled_at' => now()]);
    $user = User::query()->findOrFail($shift->user_id);

    $screen = app(DogWorkMenu::class)->complete($user, 'en', ['action' => 'work.complete', 'token' => $shift->token]);

    expect($screen->text)->toContain('Cancelled: no reward was paid.')->not->toContain('Reward:', 'Received:');
    expect(telegramWorkCommands($screen, 'work.complete'))->toBeEmpty();
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram work refuses a missing or malformed confirmation before starting work', function (array $state) {
    $user = User::factory()->create();

    expect(fn () => app(DogWorkMenu::class)->start($user, 'en', $state))->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseCount('dog_work_shifts', 0);
})->with([
    'missing draft' => [[]],
    'invalid token' => [['work' => ['petId' => 1, 'offerId' => 1, 'token' => 'bad-token']]],
    'non-scalar draft' => [['work' => ['petId' => [], 'offerId' => 1, 'token' => '00000000-0000-4000-8000-000000000000']]],
]);

test('telegram work keeps navigation available when the board has no offers or dogs', function () {
    $this->freezeSecond();
    $user = User::factory()->create();

    $screen = app(DogWorkMenu::class)->index($user, 'en', ['action' => 'work']);

    expect($screen->text)->toContain('No jobs today.', 'Choose a dog');
    expect(telegramWorkCommands($screen, 'work.offer'))->toBeEmpty();
    expect(telegramWorkCommands($screen, 'pets'))->toHaveCount(1);
    expect(telegramWorkCommands($screen, 'home'))->toHaveCount(1);
});
