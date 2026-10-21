<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\Pet;
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

test('one owner can enter the whole family then follow its individual assessments into the producer group final', function (string $locale, int $width) {
    $owner = User::factory()->create(['coins' => 1000, 'locale' => $locale]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $attributes = ['exterior' => ['type' => 100, 'structure' => 100, 'movement' => 100],
        'obedience' => 100, 'bond' => 100, 'mood' => 100, 'cleanliness' => 100,
        'health' => 100, 'energy' => 100, 'hydration' => 100, 'satiety' => 100];
    $parent = Pet::factory()->for($owner)->for($breed)->create([...$attributes, 'name' => 'Runa', 'born_at' => now()->subDays(40)]);
    $children = Pet::factory()->count(3)->for($owner)->for($breed)->create([...$attributes, 'father_id' => $parent->id, 'born_at' => now()->subDays(18)]);
    $show = GameEvent::factory()->create(['discipline' => 'conformation', 'rules' => [
        'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10, 'field_size' => 1,
        'stages' => ['type', 'structure', 'movement'], 'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => 'cac_cf', 'format' => 'conformation'],
    ]]);
    $ring = GameEvent::factory()->create(['discipline' => 'progeny', 'closes_at' => $show->closes_at,
        'starts_at' => $show->ends_at->addMinutes(10), 'ends_at' => $show->ends_at->addMinutes(20),
        'rules' => ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 5, 'field_size' => 1,
            'linked_show_id' => $show->id, 'stages' => ['type', 'uniformity', 'movement'],
            'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => 'progeny', 'format' => 'progeny_ring']],
    ]);
    $this->actingAs($owner);
    $page = visit(route('game-events.show', $show, absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $locale === 'ru' ? 'dark' : 'light',
    ]);

    foreach ([$parent, ...$children] as $member) {
        $page->select('#event-dog', $member->id)
            ->select('#event-entry-option', 'open')
            ->click('.event-form-actions button[type="submit"]')
            ->assertSee($locale === 'ru' ? 'Сохранить план' : 'Save performance plan')
            ->assertSelected('#event-dog', $member->id);
    }
    expect($show->entries()->where('user_id', $owner->id)->count())->toBe(4);
    $page->select('#event-dog', $parent->id)
        ->click('input[name="event-stage-0"][value="careful"]')
        ->click('.event-form-actions button[type="submit"]')
        ->assertSee($locale === 'ru' ? 'План выступления сохранён.' : 'Your performance plan has been saved.')
        ->refresh()
        ->assertChecked('input[name="event-stage-0"][value="careful"]')
        ->select('#event-dog', $children[0]->id)
        ->assertChecked('input[name="event-stage-0"][value="balanced"]')
        ->navigate(route('game-events.show', $ring, absolute: false))
        ->select('#event-dog', $parent->id)
        ->assertSee($locale === 'ru' ? 'Допуск после индивидуального ринга' : 'Admission follows the individual ring')
        ->assertMissing('fieldset.event-stage')
        ->assertMissing('.event-gear-list');
    foreach ($children as $child) {
        $page->click('.event-offspring input[value="'.$child->id.'"]');
    }
    $page->click('.event-form-actions button[type="submit"]')
        ->assertSee($locale === 'ru' ? 'Сохранить группу потомков' : 'Save offspring group')
        ->assertNoJavaScriptErrors();
    $entry = $ring->entries()->where('user_id', $owner->id)->sole();
    expect($entry->plan['offspring_ids'])->toBe($children->modelKeys());
    expect($owner->fresh()->coins)->toBe(875);

    $this->travelTo($show->ends_at);
    app(GameEventProcessor::class)->processDue();
    expect($entry->fresh()->status)->toBe('frozen');
    $this->travelTo($ring->ends_at);
    app(GameEventProcessor::class)->processDue();
    $page->refresh()
        ->assertSee($locale === 'ru' ? 'Лучший производитель выставки' : 'Best progeny group')
        ->assertSee($locale === 'ru' ? 'Места в финале групп' : 'Group final placements')
        ->assertSee($locale === 'ru' ? 'Награды групп производителей' : 'Progeny group awards')
        ->assertSee($locale === 'ru' ? 'Отбор в породе' : 'Breed selection')
        ->assertSee($locale === 'ru' ? 'Общий финал групп' : 'Overall group final')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
    $page->script('document.querySelector(".event-own-result").scrollIntoView({ block: "center", behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'progeny-ring-final-'.$locale.'-'.$width);
    expect($entry->fresh()->result['members'])->toHaveCount(4);
})->with(['English desktop' => ['en', 1440], 'Russian mobile' => ['ru', 390]]);
