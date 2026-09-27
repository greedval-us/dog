<?php

namespace App\Providers;

use App\Modules\Pets\Calculators\PetCareRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Random\Engine\Secure;
use Random\Randomizer;

/** @phpstan-import-type CareBalance from PetCareRules */
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
