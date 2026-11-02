<?php

namespace App\Http\Telegram\Menus;

use App\Models\DogWorkShift;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Telegram\DTO\TelegramScreen;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Button from MenuView
 * @phpstan-import-type Command from MenuView
 *
 * @phpstan-type ActivityCounts array{ready: int, pending: int, next: string|null}
 * @phpstan-type Activity array{petId: int, name: string, endsAt: CarbonImmutable, readyLabel: string, readyCommand: Command, pendingCommand: Command}
 */
final class HomeSummary
{
    private const QUICK_ACTION_LIMIT = 3;

    public function __construct(private MenuView $view, private PetDecayCalculator $states) {}

    public function build(User $user, string $locale): TelegramScreen
    {
        $at = CarbonImmutable::now()->startOfSecond();
        $wallet = User::query()->findOrFail($user->id, ['coins', 'gems']);
        $pets = $user->pets()->active()->oldest('id')->get();
        foreach ($pets as $pet) {
            $pet->advanceTo($at, $this->states);
        }
        $pets = $pets->filter(fn (Pet $pet): bool => $pet->isActive());
        $needy = $pets->filter(fn (Pet $pet): bool => collect($pet->statePercentages())->contains(fn (float $value): bool => $value < 50));
        $heading = $this->view->heading($this->view->translate('sections.overview', $locale));
        $lines = [
            $this->view->translate('home_summary.balance', $locale, ['coins' => $wallet->coins, 'gems' => $wallet->gems]),
            $this->view->translate('home_summary.dogs', $locale, ['count' => $pets->count()]),
            $this->view->translate('home_summary.needs_care', $locale, ['count' => $needy->count()]),
        ];
        if ($pets->isEmpty()) {
            return new TelegramScreen($heading."\n".$this->view->escape(implode("\n", [...$lines, '', $this->view->translate('home_summary.no_activities', $locale)])), []);
        }

        $care = PetCareAction::query()->join('pets', function (JoinClause $join): void {
            $join->on('pets.id', '=', 'pet_care_actions.pet_id')->on('pets.activity_token', '=', 'pet_care_actions.activity_token');
        })->where('pet_care_actions.user_id', $user->id)->whereIn('pets.id', $pets->modelKeys())
            ->whereNull('pet_care_actions.completed_at')->whereNull('pet_care_actions.cancelled_at');
        $work = DogWorkShift::query()->join('pets', function (JoinClause $join): void {
            $join->on('pets.id', '=', 'dog_work_shifts.pet_id')->on('pets.activity_token', '=', 'dog_work_shifts.activity_token');
        })->where('dog_work_shifts.user_id', $user->id)->whereIn('pets.id', $pets->modelKeys())
            ->whereNull('dog_work_shifts.completed_at')->whereNull('dog_work_shifts.cancelled_at');
        $careCounts = $this->counts($care, $at);
        $workCounts = $this->counts($work, $at);
        $ready = $careCounts['ready'] + $workCounts['ready'];
        $pending = $careCounts['pending'] + $workCounts['pending'];
        $lines[] = '';
        $lines[] = $this->view->translate($ready + $pending === 0 ? 'home_summary.no_activities' : 'home_summary.activities', $locale, ['ready' => $ready, 'pending' => $pending]);
        $nextFinishes = array_values(array_filter([$careCounts['next'], $workCounts['next']]));
        sort($nextFinishes);
        if ($nextFinishes !== []) {
            $lines[] = $this->view->translate('home_summary.next_finish', $locale, ['time' => $this->view->dateTime($nextFinishes[0], $user)]);
        }

        /** @var list<Activity> $activities */
        $activities = [];
        foreach ($care->orderBy('pet_care_actions.ends_at')->orderBy('pet_care_actions.id')->limit(self::QUICK_ACTION_LIMIT)
            ->get(['pet_care_actions.pet_id', 'pet_care_actions.token', 'pet_care_actions.ends_at']) as $receipt) {
            $activities[] = ['petId' => $receipt->pet_id, 'name' => $pets->findOrFail($receipt->pet_id)->name, 'endsAt' => $receipt->ends_at,
                'readyLabel' => 'home_summary.ready_care',
                'readyCommand' => ['action' => 'care.complete', 'petId' => $receipt->pet_id, 'token' => $receipt->token],
                'pendingCommand' => ['action' => 'pet', 'petId' => $receipt->pet_id]];
        }
        foreach ($work->orderBy('dog_work_shifts.ends_at')->orderBy('dog_work_shifts.id')->limit(self::QUICK_ACTION_LIMIT)
            ->get(['dog_work_shifts.pet_id', 'dog_work_shifts.token', 'dog_work_shifts.ends_at']) as $shift) {
            $activities[] = ['petId' => $shift->pet_id, 'name' => $pets->findOrFail($shift->pet_id)->name, 'endsAt' => $shift->ends_at,
                'readyLabel' => 'home_summary.ready_work',
                'readyCommand' => ['action' => 'work.complete', 'token' => $shift->token],
                'pendingCommand' => ['action' => 'work', 'tab' => 'shifts', 'petId' => $shift->pet_id]];
        }
        usort($activities, fn (array $first, array $second): int => $first['endsAt']->getTimestamp() <=> $second['endsAt']->getTimestamp()
            ?: $first['petId'] <=> $second['petId']);
        $rows = [];
        $shownPets = [];
        foreach ($activities as $activity) {
            if ($activity['endsAt']->greaterThan($at)) {
                continue;
            }
            $rows[] = [$this->activityButton($activity, $locale, true)];
            $shownPets[] = $activity['petId'];
            if (count($rows) === self::QUICK_ACTION_LIMIT) {
                break;
            }
        }
        foreach ($needy as $pet) {
            if (count($rows) >= self::QUICK_ACTION_LIMIT) {
                break;
            }
            if (! in_array($pet->id, $shownPets, true)) {
                $rows[] = [$this->view->commandButton($this->view->label('home_summary.care_dog', $locale, ['name' => Str::limit($pet->name, 24)]), ['action' => 'pet', 'petId' => $pet->id])];
                $shownPets[] = $pet->id;
            }
        }
        foreach ($activities as $activity) {
            if (count($rows) >= self::QUICK_ACTION_LIMIT) {
                break;
            }
            if ($activity['endsAt']->greaterThan($at) && ! in_array($activity['petId'], $shownPets, true)) {
                $rows[] = [$this->activityButton($activity, $locale, false)];
                $shownPets[] = $activity['petId'];
            }
        }

        return new TelegramScreen($heading."\n".$this->view->escape(implode("\n", $lines)), $rows);
    }

    /**
     * @param  Builder<PetCareAction>|Builder<DogWorkShift>  $query
     * @return ActivityCounts
     */
    private function counts(Builder $query, CarbonImmutable $at): array
    {
        /** @var object{total: int|string, ready: int|string, next_finish: string|null} $counts */
        $counts = (clone $query)->toBase()->selectRaw('COUNT(*) AS total, COUNT(CASE WHEN ends_at <= ? THEN 1 END) AS ready, MIN(CASE WHEN ends_at > ? THEN ends_at END) AS next_finish', [$at, $at])->first();

        return ['ready' => (int) $counts->ready, 'pending' => (int) $counts->total - (int) $counts->ready, 'next' => $counts->next_finish];
    }

    /**
     * @param  Activity  $activity
     * @return Button
     */
    private function activityButton(array $activity, string $locale, bool $ready): array
    {
        return $this->view->commandButton($this->view->label($ready ? $activity['readyLabel'] : 'home_summary.pending_activity', $locale, ['name' => Str::limit($activity['name'], 24)]),
            $ready ? $activity['readyCommand'] : $activity['pendingCommand']);
    }
}
