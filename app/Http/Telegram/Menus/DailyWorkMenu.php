<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Actions\CompleteDailyWork;
use App\Modules\Players\DTO\DailyWorkQuote;
use App\Modules\Players\Queries\GetDailyWork;
use App\Modules\Telegram\DTO\TelegramScreen;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class DailyWorkMenu
{
    public function __construct(private MenuView $view, private GetDailyWork $dailyWork, private CompleteDailyWork $completeWork) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command = []): TelegramScreen
    {
        $dailyWork = $this->dailyWork->handle($user->refresh(), $locale);
        $lines = [
            $this->view->translate('daily_work.title', $locale),
            $this->view->translate('daily_work.description', $locale),
            $this->view->translate('daily_work.streak', $locale, ['progress' => $dailyWork['progress'], 'total' => $dailyWork['streakLength']]),
            $this->view->translate('daily_work.resets', $locale, [
                'time' => CarbonImmutable::parse($dailyWork['resetsAt'])->setTimezone($dailyWork['timezone'])->format('d.m H:i T'),
                'timezone' => $dailyWork['timezone'],
            ]),
        ];
        $rows = [];
        if ($dailyWork['completedToday']) {
            $lines[] = $this->view->translate('daily_work.completed', $locale, ['coins' => $dailyWork['earnedCoins'], 'gems' => $dailyWork['earnedGems']]);
        } elseif (! $dailyWork['canWork']) {
            $lines[] = $this->view->translate('daily_work.unavailable', $locale);
        } elseif ($dailyWork['jobs'] === []) {
            $lines[] = $this->view->translate('daily_work.empty', $locale);
        } else {
            $lines[] = $this->view->translate('daily_work.choose', $locale);
            $offset = max(0, (int) ($command['offset'] ?? 0));
            foreach (array_slice($dailyWork['jobs'], $offset, MenuView::PAGE_SIZE) as $job) {
                $label = Str::limit($job['name'], 100).' · '.$job['coins'].' '.$this->view->translate('coins', $locale);
                $rows[] = [$this->view->commandButton($label, ['action' => 'daily_work.confirm', 'workTypeId' => $job['id']])];
            }
            $rows = [...$rows, ...$this->view->offsetNavigation(count($dailyWork['jobs']), $offset, ['action' => 'daily_work'], $locale)];
        }
        $rows[] = [$this->view->button('refresh', 'daily_work', $locale)];

        return $this->view->screen(implode("\n\n", $lines), $rows, $locale);
    }

    /** @param Command $command */
    public function confirm(User $user, string $locale, array $command): TelegramScreen
    {
        $dailyWork = $this->dailyWork->handle($user->refresh(), $locale);
        if (! $dailyWork['canWork']) {
            return $this->index($user, $locale);
        }
        $job = collect($dailyWork['jobs'])->firstWhere('id', (int) ($command['workTypeId'] ?? 0));
        if ($job === null) {
            throw new ModelNotFoundException;
        }
        $gems = $dailyWork['streakLength'] === $dailyWork['progress'] + 1 ? $job['gems'] : 0;
        $lines = [
            $this->view->translate('daily_work.title', $locale).' · '.Str::limit($job['name'], 120),
            Str::limit($job['description'], 500),
            $this->view->translate('daily_work.reward', $locale, ['coins' => $job['coins'], 'gems' => $gems]),
            $this->view->translate('daily_work.bonus', $locale, ['days' => $dailyWork['streakLength'], 'gems' => $job['gems']]),
            $this->view->translate('daily_work.confirmation', $locale),
        ];

        return $this->view->screen(implode("\n\n", $lines), [[$this->view->button('daily_work.complete', 'daily_work.complete', $locale)]], $locale,
            ['action' => 'daily_work'], ['dailyWork' => [
                'userId' => $user->id, 'workTypeId' => $job['id'],
                'workedOn' => CarbonImmutable::parse($dailyWork['resetsAt'])->setTimezone($dailyWork['timezone'])->subDay()->toDateString(),
                'expectedCoins' => $job['coins'], 'expectedGems' => $gems,
            ]]);
    }

    /** @param State $state */
    public function complete(User $user, string $locale, array $state): TelegramScreen
    {
        $quote = new SavedMenuState($state, 'dailyWork');
        $workTypeId = $quote->integer('workTypeId');
        $workedOn = $quote->string('workedOn');
        $expectedCoins = $quote->integer('expectedCoins');
        $expectedGems = $quote->integer('expectedGems', minimum: 0);
        if ($quote->integer('userId') !== $user->id) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $this->completeWork->handle($user, $workTypeId, new DailyWorkQuote($workedOn, $expectedCoins, $expectedGems));

        return $this->index($user, $locale);
    }
}
