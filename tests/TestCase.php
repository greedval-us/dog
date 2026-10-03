<?php

namespace Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /** Build a historical schema in isolation; RefreshDatabase rolls it back with the test. */
    protected function prepareLegacySchema(string $migrationName): Migration
    {
        $migrations = app(Migrator::class)->getMigrationFiles([database_path('migrations')]);
        $this->assertArrayHasKey($migrationName, $migrations);
        $this->assertGreaterThan(0, DB::connection()->transactionLevel());

        $schema = 'legacy_'.str_replace('-', '', (string) Str::uuid());
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        DB::statement('SET LOCAL search_path TO "'.$schema.'"');

        foreach ($migrations as $name => $path) {
            if ($name >= $migrationName) {
                break;
            }

            $migration = require $path;
            $migration->up();
        }

        return require $migrations[$migrationName];
    }

    protected function rejectCareWrites(string $trigger, string $event = 'UPDATE'): void
    {
        DB::unprepared("CREATE FUNCTION {$trigger}() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''Simulated failure''; END'; CREATE TRIGGER {$trigger} BEFORE {$event} ON pet_care_actions FOR EACH ROW EXECUTE FUNCTION {$trigger}()");
    }

    protected function allowCareWrites(string $trigger): void
    {
        DB::statement("DROP TRIGGER {$trigger} ON pet_care_actions");
        DB::statement("DROP FUNCTION {$trigger}()");
    }
}
