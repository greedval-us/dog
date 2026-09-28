<?php

namespace App\Providers;

use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Pets\Calculators\PetStateCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Random\Engine\Secure;
use Random\Randomizer;

/**
 * @phpstan-import-type CareBalance from PetCareRules
 * @phpstan-import-type StateBalance from PetStateCalculator
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Randomizer::class, fn (): Randomizer => new Randomizer(new Secure));
        $this->app->bind(PetCareRules::class, function (): PetCareRules {
            /** @var CareBalance $balance */
            $balance = Config::array('pet_care');

            return new PetCareRules($balance);
        });
        $this->app->bind(PetStateCalculator::class, function (): PetStateCalculator {
            /** @var StateBalance $balance */
            $balance = Config::array('pet_states');

            return new PetStateCalculator($balance);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
