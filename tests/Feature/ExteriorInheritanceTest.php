<?php

use App\Models\Pet;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Actions\KeepPuppy;
use App\Modules\Pets\Calculators\ExteriorGeneticsCalculator;
use Illuminate\Support\Str;

test('inherited exterior combines both parents with bounded independent variation', function () {
    $calculator = new ExteriorGeneticsCalculator;

    expect($calculator->inherit(
        ['type' => 100, 'structure' => 40, 'movement' => 0],
        ['type' => 100, 'structure' => 80, 'movement' => 0],
        ['type' => 5, 'structure' => -3, 'movement' => -5],
    ))->toBe(['type' => 100, 'structure' => 57, 'movement' => 0]);
});

test('keeping a puppy preserves its exterior and repeated placement never rerolls it', function () {
    $this->freezeTime();
    $owner = User::factory()->create();
    $puppy = Puppy::factory()->for($owner, 'user')->create([
        'status' => 'pending', 'exterior' => ['type' => 92, 'structure' => 68, 'movement' => 81],
    ]);
    $token = (string) Str::uuid();

    $placement = app(KeepPuppy::class)->handle($owner, $puppy->id, 'Рей', $token);
    app(KeepPuppy::class)->handle($owner, $puppy->id, 'Рей', $token);

    expect(Pet::query()->findOrFail($placement->pet_id)->exterior)->toEqual(['type' => 92, 'structure' => 68, 'movement' => 81]);
    $this->assertDatabaseCount('puppy_placements', 1);
});
