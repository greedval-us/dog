<?php

namespace App\Providers;

use App\Modules\Pets\Calculators\EventChampionshipRules;
use App\Modules\Pets\Calculators\GameEventCalendar;
use App\Modules\Pets\Calculators\GameEventTitleOpportunities;
use App\Modules\Pets\Calculators\PetBoardingRules;
use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\PetStateCalculator;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Pets\Calculators\RegionalSportAwards;
use App\Modules\Pets\Calculators\RegionalSportChampionships;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Calculators\RegulatedEventSimulator;
use App\Modules\Pets\Calculators\RegulatedExhibitionAwards;
use App\Modules\Pets\Calculators\RegulatedSportAwards;
use App\Modules\Pets\Calculators\SpecialExhibitionAwards;
use App\Modules\Pets\Calculators\SpecialExhibitionChampionships;
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
 * @phpstan-import-type StatBalance from PetDecayCalculator
 * @phpstan-import-type BoardingBalance from PetBoardingRules
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Randomizer::class, fn (): Randomizer => new Randomizer(new Secure));
        $this->app->bind(RegulatedEventRules::class, fn (): RegulatedEventRules => new RegulatedEventRules(Config::array('event_regulations')));
        $this->app->bind(GameEventCalendar::class, fn (): GameEventCalendar => new GameEventCalendar(Config::array('game-events.special_calendar'), Config::string('game-events.timezone')));
        $this->app->bind(GameEventTitleOpportunities::class, fn (): GameEventTitleOpportunities => new GameEventTitleOpportunities(
            $this->app->make(RegulatedExhibitionAwards::class), $this->app->make(SpecialExhibitionAwards::class),
            $this->app->make(RegulatedSportAwards::class), $this->app->make(RegionalSportAwards::class),
            $this->app->make(RegulatedEventRules::class), Config::array('sport_titles.minimum_participants'),
        ));
        $this->app->bind(RegulatedEventSimulator::class, fn (): RegulatedEventSimulator => new RegulatedEventSimulator(Config::array('event_regulations')));
        $this->app->bind(RegulatedExhibitionAwards::class, fn (): RegulatedExhibitionAwards => new RegulatedExhibitionAwards(Config::array('event_regulations')));
        $this->app->bind(RegulatedSportAwards::class, fn (): RegulatedSportAwards => new RegulatedSportAwards(Config::array('event_regulations')));
        $this->app->bind(EventChampionshipRules::class, fn (): EventChampionshipRules => new EventChampionshipRules(Config::array('event_regulations')));
        $this->app->bind(RegionalSportAwards::class, fn (): RegionalSportAwards => new RegionalSportAwards($this->app->make(RegulatedSportAwards::class), Config::array('sport_titles')));
        $this->app->bind(RegionalSportChampionships::class, fn (): RegionalSportChampionships => new RegionalSportChampionships(Config::array('sport_titles')));
        $this->app->bind(SpecialExhibitionAwards::class, fn (): SpecialExhibitionAwards => new SpecialExhibitionAwards($this->app->make(RegulatedExhibitionAwards::class), [
            ...Config::array('event_regulations'), 'special_exhibitions' => Config::array('special_exhibitions'),
        ]));
        $this->app->bind(SpecialExhibitionChampionships::class, fn (): SpecialExhibitionChampionships => new SpecialExhibitionChampionships($this->app->make(EventChampionshipRules::class), [
            ...Config::array('event_regulations'), 'special_exhibitions' => Config::array('special_exhibitions'),
        ]));
        $this->app->bind(PetBoardingRules::class, function (): PetBoardingRules {
            /** @var BoardingBalance $balance */
            $balance = Config::array('pet_boarding');
            /** @var array<string, int|float> $statDecay */
            $statDecay = Config::array('pet_stats.decay_per_hour');

            return new PetBoardingRules($balance, $statDecay);
        });
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
        $this->app->bind(PetDecayCalculator::class, function (): PetDecayCalculator {
            /** @var StatBalance $balance */
            $balance = Config::array('pet_stats');

            return new PetDecayCalculator($this->app->make(PetStateCalculator::class), $this->app->make(PetStatusRules::class), $balance);
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
