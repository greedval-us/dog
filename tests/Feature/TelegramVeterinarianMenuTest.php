<?php

use App\Http\Telegram\Menus\VeterinarianMenu;
use App\Models\Disease;
use App\Models\Pet;
use App\Models\PetDisease;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

test('telegram veterinary checkup requires confirmation and replays its original receipt', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500, 'locale' => 'en']))->create(['health' => 50, 'health_max' => 100]);
    $menu = app(VeterinarianMenu::class);

    $confirmation = $menu->service($pet->user, 'en', ['petId' => $pet->id, 'service' => 'checkup']);

    expect($confirmation->state['veterinary']['expectedPrice'])->toBe(60);
    expect($pet->user->fresh()->coins)->toBe(500);
    expect($pet->fresh()->health)->toBe(50.0);
    $menu->perform($pet->user, 'en', $confirmation->state);
    $menu->perform($pet->user, 'en', $confirmation->state);

    expect($pet->user->fresh()->coins)->toBe(440);
    expect($pet->fresh()->health)->toBe(60.0);
    $this->assertDatabaseCount('veterinary_visits', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('telegram veterinary confirmation keeps its quoted price when the catalogue changes', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $menu = app(VeterinarianMenu::class);
    $confirmation = $menu->service($pet->user, 'en', ['petId' => $pet->id, 'service' => 'vaccination']);
    config(['veterinarian.prices.vaccination' => 110]);

    expect(fn () => $menu->perform($pet->user, 'en', $confirmation->state))->toThrow(PetUnavailable::class);
    expect($pet->user->fresh()->coins)->toBe(500);
    $this->assertDatabaseCount('veterinary_visits', 0);
});

test('telegram veterinary treatment selects a disease and cures only the confirmed episode', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $disease = Disease::factory()->create(['name' => ['en' => '<illness>']]);
    $episode = PetDisease::factory()->for($pet)->for($disease)->create(['effect_snapshot' => $disease->snapshot()]);
    $other = PetDisease::factory()->for($pet)->create();
    $menu = app(VeterinarianMenu::class);

    $list = $menu->diseases($pet->user, 'en', ['petId' => $pet->id]);
    expect(collect($list->rows)->flatten(1)->pluck('command')->filter()->values()->all())->toContain([
        'action' => 'clinic.service', 'petId' => $pet->id, 'service' => 'treatment', 'episodeId' => $episode->id,
    ]);
    $confirmation = $menu->service($pet->user, 'en', ['petId' => $pet->id, 'service' => 'treatment', 'episodeId' => $episode->id]);
    expect($confirmation->text)->toContain('&lt;illness&gt;')->not->toContain('<illness>');
    $menu->perform($pet->user, 'en', $confirmation->state);

    expect($episode->fresh()->ended_at)->not->toBeNull();
    expect($other->fresh()->ended_at)->toBeNull();
    expect($pet->user->fresh()->coins)->toBe(380);
});

test('telegram veterinary pages and purchases exclude another owners dog', function () {
    $user = User::factory()->create(['coins' => 500]);
    $foreign = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $menu = app(VeterinarianMenu::class);

    expect(fn () => $menu->index($user, 'en', ['petId' => $foreign->id]))->toThrow(ModelNotFoundException::class);
    $quote = $menu->service($foreign->user, 'en', ['petId' => $foreign->id, 'service' => 'checkup']);
    expect(fn () => $menu->perform($user, 'en', $quote->state))->toThrow(ModelNotFoundException::class);
    expect($user->fresh()->coins)->toBe(500);
    $this->assertDatabaseCount('veterinary_visits', 0);
});

test('telegram veterinary cooldown remains visible and prevents another confirmation', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $menu = app(VeterinarianMenu::class);
    $quote = $menu->service($pet->user, 'en', ['petId' => $pet->id, 'service' => 'vaccination']);
    $menu->perform($pet->user, 'en', $quote->state);

    $screen = $menu->service($pet->user->fresh(), 'en', ['petId' => $pet->id, 'service' => 'vaccination']);

    expect(collect($screen->rows)->flatten(1)->pluck('command.action')->filter()->all())->not->toContain('clinic.perform');
    expect($screen->state)->toBeEmpty();
    expect($screen->text)->toContain('vaccination is still active', now()->addDays(30)->format('d.m'));
});
