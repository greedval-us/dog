<?php

use App\Models\BreedingListing;
use App\Models\BreedingLitter;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\StartBreeding;
use App\Modules\Pets\Enums\PetStat;
use Database\Seeders\BreedingCatalogueSeeder;
use Database\Seeders\DogSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Random\Engine;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

uses(TestCase::class, DatabaseTruncation::class);

test('retrying a mating preserves offspring and ownership while charging and reserving parents once', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The breeding retry requires a PostgreSQL transaction failure.');
    }
    $this->freezeSecond();
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);
    $dog = Dog::query()->where('breed', 'german_shepherd')->firstOrFail();
    $owner = User::factory()->create(['experience' => '1500', 'coins' => 1000]);
    $seller = User::factory()->create(['experience' => '1500', 'coins' => 0]);
    $stats = [];
    foreach (PetStat::cases() as $stat) {
        $stats[$stat->value] = 90;
        $stats[$stat->potentialColumn()] = 100;
    }
    $dam = Pet::factory()->female()->for($owner)->for($dog)->create(['born_at' => now()->subDays(8), ...$stats]);
    $sire = Pet::factory()->for($seller)->for($dog)->create(['born_at' => now()->subDays(8), ...$stats]);
    $listing = BreedingListing::factory()->for($sire, 'pet')->for($seller)->create(['price' => 100]);
    $token = (string) Str::uuid();
    $engine = new class implements Engine
    {
        public int $draws = 0;

        private Engine $engine;

        public function __construct()
        {
            $this->engine = new Mt19937(123);
        }

        public function generate(): string
        {
            $this->draws++;

            return $this->engine->generate();
        }
    };
    $this->app->instance(Randomizer::class, new Randomizer($engine));
    $attempts = [];
    $columns = ['father_id', 'mother_id', 'user_id', 'dog_id', 'name', 'sex', 'coat_color', 'generation', 'status', 'expires_at'];
    foreach (PetStat::cases() as $stat) {
        $columns[] = $stat->potentialColumn();
    }
    Event::listen('eloquent.saving: '.Pet::class, function (Pet $pet) use ($dam, $token, $engine, $columns, &$attempts): void {
        if ($pet->id !== $dam->id || ! $pet->isDirty('breeding_available_at')) {
            return;
        }
        $litter = BreedingLitter::query()->where('operation_token', $token)->sole();
        $attempts[] = [
            'snapshots' => $litter->snapshots,
            'puppies' => $litter->puppies()->orderBy('id')->get($columns)->toArray(),
            'draws' => $engine->draws,
        ];
    });
    DB::unprepared(<<<SQL
        CREATE SEQUENCE breeding_retry_attempt;
        CREATE FUNCTION retry_breeding_reservation() RETURNS trigger LANGUAGE plpgsql AS \$\$
        BEGIN
            IF NEW.id = {$dam->id} AND NEW.breeding_available_at IS NOT NULL
                AND OLD.breeding_available_at IS NULL
                AND nextval('breeding_retry_attempt') = 1 THEN
                RAISE EXCEPTION 'deadlock detected: simulated breeding reservation' USING ERRCODE = '40P01';
            END IF;
            RETURN NEW;
        END;
        \$\$;
        CREATE TRIGGER retry_breeding_reservation BEFORE UPDATE ON pets
            FOR EACH ROW EXECUTE FUNCTION retry_breeding_reservation();
        SQL);

    try {
        $litter = app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, $token);

        expect($attempts)->toHaveCount(2);
        expect($attempts[0]['puppies'])->toHaveCount($litter->puppies()->count());
        expect($attempts[1])->toBe($attempts[0]);
        expect($litter->puppies()->where('user_id', $seller->id)->count())->toBe(1);
        expect($litter->puppies()->where('user_id', $owner->id)->count())->toBe($litter->puppies()->count() - 1);
        expect($owner->fresh()->coins)->toBe(900);
        expect($seller->fresh()->coins)->toBe(100);
        expect($dam->fresh()->breeding_available_at->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString());
        expect($sire->fresh()->breeding_available_at->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString());
        $this->assertDatabaseCount('breeding_litters', 1);
        $this->assertDatabaseCount('puppies', count($attempts[0]['puppies']));
        $this->assertDatabaseCount('currency_transactions', 2);
        $this->assertDatabaseHas('currency_transactions', ['user_id' => $owner->id, 'operation_key' => 'breeding:'.$token, 'amount' => -100]);
        $this->assertDatabaseHas('currency_transactions', ['user_id' => $seller->id, 'operation_key' => 'breeding:'.$token, 'amount' => 100]);
    } finally {
        Event::forget('eloquent.saving: '.Pet::class);
        DB::unprepared('DROP TRIGGER IF EXISTS retry_breeding_reservation ON pets; DROP FUNCTION IF EXISTS retry_breeding_reservation(); DROP SEQUENCE IF EXISTS breeding_retry_attempt');
    }
});
