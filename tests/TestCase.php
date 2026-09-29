<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function rejectCareWrites(string $trigger, string $event = 'UPDATE'): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE FUNCTION {$trigger}() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''Simulated failure''; END'; CREATE TRIGGER {$trigger} BEFORE {$event} ON pet_care_actions FOR EACH ROW EXECUTE FUNCTION {$trigger}()");
        } else {
            DB::statement("CREATE TRIGGER {$trigger} BEFORE {$event} ON pet_care_actions BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END");
        }
    }

    protected function allowCareWrites(string $trigger): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP TRIGGER {$trigger} ON pet_care_actions");
            DB::statement("DROP FUNCTION {$trigger}()");
        } else {
            DB::statement("DROP TRIGGER {$trigger}");
        }
    }
}
