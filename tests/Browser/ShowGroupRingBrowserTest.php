<?php

use App\Models\BreedingLitter;
use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Services\GameEventProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    config(['inertia.ssr.enabled' => false]);
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
});

test('a saved show group follows its members from individual admission into the final ring', function (string $discipline, string $locale, int $width, string $colorScheme) {
    $owner = User::factory()->create(['coins' => 1000, 'locale' => $locale]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $attributes = ['born_at' => now()->subDays(20),
        'exterior' => ['type' => 100, 'structure' => 100, 'movement' => 100],
        'obedience' => 100, 'bond' => 100, 'mood' => 100, 'cleanliness' => 100,
        'health' => 100, 'energy' => 100, 'hydration' => 100, 'satiety' => 100];
    $anchor = Pet::factory()->for($owner)->for($breed)->create([...$attributes, 'name' => 'Atlas']);
    $companion = Pet::factory()->female()->for($owner)->for($breed)->create([...$attributes, 'name' => 'Luna']);
    $members = [$anchor, $companion];
    $foreignOwner = null;
    if ($discipline === 'breeders') {
        $foreignOwner = User::factory()->create(['username' => 'sold-dog-owner']);
        $members[] = Pet::factory()->for($foreignOwner)->for($breed)->create([...$attributes, 'name' => 'Orion']);
        $father = Pet::factory()->for($owner)->for($breed)->create(['born_at' => now()->subDays(60)]);
        $mother = Pet::factory()->female()->for($owner)->for($breed)->create(['born_at' => now()->subDays(60)]);
        $litter = BreedingLitter::factory()->create([
            'own_pet_id' => $father->id, 'initiator_id' => $owner->id,
            'father_id' => $father->id, 'mother_id' => $mother->id,
            'born_at' => $anchor->born_at, 'delivered_at' => $anchor->born_at,
            'snapshots' => ['breeding_origin' => [
                'breeder_key' => 'player:'.$owner->id, 'kennel_key' => 'player:'.$owner->id,
                'kennel_name' => 'Silver Birch',
            ]],
        ]);
        foreach ($members as $member) {
            $member->update(['father_id' => $father->id, 'mother_id' => $mother->id]);
            Puppy::factory()->for($litter, 'litter')->create(['pet_id' => $member->id, 'status' => 'placed']);
        }
    }
    $show = GameEvent::factory()->create(['discipline' => 'conformation', 'seed' => str_repeat('a', 32), 'rules' => [
        'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10, 'field_size' => 1,
        'stages' => ['type', 'structure', 'movement'],
        'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => 'cac_cf', 'format' => 'conformation'],
    ]]);
    $ring = GameEvent::factory()->create([
        'discipline' => $discipline, 'seed' => str_repeat('b', 32), 'closes_at' => $show->closes_at,
        'starts_at' => $show->ends_at->addMinutes(10), 'ends_at' => $show->ends_at->addMinutes(20),
        'rules' => ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 5, 'field_size' => 1,
            'linked_show_id' => $show->id, 'stages' => ['type', 'uniformity', 'movement'],
            'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => $discipline, 'format' => $discipline.'_ring']],
    ]);
    foreach ($members as $member) {
        GameEventEntry::factory()->for($show, 'event')->for($member)->for($member->user)->create([
            'plan' => ['exhibition_class' => 'open', 'stages' => ['balanced', 'balanced', 'balanced']],
        ]);
    }
    $this->actingAs($owner);
    $page = visit(route('game-events.show', $ring, absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ]);

    $page->select('#event-dog', $anchor->id)
        ->assertSee($locale === 'ru' ? 'Допуск после индивидуального ринга' : 'Admission follows the individual ring')
        ->assertSee($locale === 'ru' ? 'Породный отбор → общий финал' : 'Breed selection → final ring')
        ->assertDisabled('.event-form-actions button[type="submit"]')
        ->assertMissing('fieldset.event-stage')
        ->assertMissing('.event-gear-list')
        ->assertScript('document.querySelectorAll(".event-offspring input[type=checkbox]").length', count($members) - 1);
    if ($foreignOwner !== null) {
        $page->assertSee('Silver Birch')->assertSee($foreignOwner->username);
    }
    foreach (array_slice($members, 1) as $member) {
        $page->assertSee($member->name)
            ->click('.event-offspring input[value="'.$member->id.'"]')
            ->assertChecked('.event-offspring input[value="'.$member->id.'"]');
    }
    $count = count($members);
    $selected = $locale === 'ru'
        ? 'Выбрано собак: '.$count.' из '.($discipline === 'couple' ? '2' : '3–5')
        : 'Selected dogs: '.$count.' of '.($discipline === 'couple' ? '2' : '3–5');
    $page->assertSee($selected)
        ->assertEnabled('.event-form-actions button[type="submit"]')
        ->click('.event-form-actions button[type="submit"]')
        ->assertSee($locale === 'ru' ? 'Сохранить группу' : 'Save group')
        ->refresh()
        ->assertSee($selected)
        ->assertNoJavaScriptErrors();
    foreach (array_slice($members, 1) as $member) {
        $page->assertChecked('.event-offspring input[value="'.$member->id.'"]');
    }
    $entry = $ring->entries()->where('user_id', $owner->id)->sole();
    expect($entry->plan['member_ids'])->toBe(array_map(static fn (Pet $member): int => $member->id, array_slice($members, 1)));

    $this->travelTo($show->ends_at);
    app(GameEventProcessor::class)->processDue();
    $this->travelTo($ring->ends_at);
    app(GameEventProcessor::class)->processDue();
    $winner = $discipline === 'couple'
        ? ($locale === 'ru' ? 'Лучшая пара выставки' : 'Best couple')
        : ($locale === 'ru' ? 'Лучший питомник выставки' : 'Best breeders group');
    $page->refresh()
        ->assertSee($winner)
        ->assertSee($locale === 'ru' ? 'Участники группы в ринге' : 'Members in the ring')
        ->assertSee($locale === 'ru' ? 'Отбор в породе' : 'Breed selection')
        ->assertSee($locale === 'ru' ? 'Общий финал групп' : 'Overall group final')
        ->assertSee($locale === 'ru' ? 'Однородность группы' : 'Group uniformity')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
    foreach ($members as $member) {
        $page->assertSeeIn('.event-replay .event-exterior .event-title-list', $member->name);
    }
    $page->script('document.querySelector(".event-replay").scrollIntoView({ block: "start", behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'show-group-'.$discipline.'-'.$locale.'-'.$width);
    expect($entry->fresh()->result['members'])->toHaveCount($count);
})->with([
    'Couple English desktop light' => ['couple', 'en', 1440, 'light'],
    'Couple Russian mobile dark' => ['couple', 'ru', 390, 'dark'],
    'Breeders English desktop light' => ['breeders', 'en', 1440, 'light'],
    'Breeders Russian mobile dark' => ['breeders', 'ru', 390, 'dark'],
]);
