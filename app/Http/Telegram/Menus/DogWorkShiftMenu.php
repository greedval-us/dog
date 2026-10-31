<?php

namespace App\Http\Telegram\Menus;

use App\Models\DogWorkShift;
use App\Models\User;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Support\Str;

/** @phpstan-import-type Command from MenuView */
final class DogWorkShiftMenu
{
    public function __construct(private MenuView $view, private CompleteDogWork $completeWork) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command, ?string $notice = null): TelegramScreen
    {
        $petId = isset($command['petId']) ? (int) $command['petId'] : null;
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $shifts = DogWorkShift::query()->where('user_id', $user->id)
            ->when($petId !== null, fn ($query) => $query->where('pet_id', $petId))
            ->orderByDesc('id')->skip($offset)->take(MenuView::PAGE_SIZE + 1)->get();
        $text = $this->view->translate('work.shifts', $locale);
        $rows = [];
        foreach ($shifts->take(MenuView::PAGE_SIZE) as $shift) {
            $rows[] = [$this->view->commandButton($this->shiftName($shift, $locale).' · '.$this->view->translate('work.'.$this->status($shift), $locale), [
                'action' => 'work.complete', 'token' => $shift->token,
            ])];
        }
        if ($shifts->isEmpty()) {
            $text .= "\n\n".$this->view->translate('work.shifts_empty', $locale);
        }
        $navigation = ['action' => 'work', 'tab' => 'shifts', ...($petId === null ? [] : ['petId' => $petId])];
        $rows = [...$rows, ...$this->view->offsetNavigation($offset + $shifts->count(), $offset, $navigation, $locale)];
        $rows[] = [$this->view->button('refresh', 'work', $locale, $navigation)];

        return $this->view->screen(($notice === null ? '' : $notice."\n\n").$text, $rows, $locale,
            ['action' => 'work', ...($petId === null ? [] : ['petId' => $petId])]);
    }

    public function show(User $user, string $locale, DogWorkShift $shift, ?string $notice = null): TelegramScreen
    {
        $status = $this->status($shift);
        $text = $this->shiftName($shift, $locale)."\n".Str::limit($shift->pet_name, 120)."\n".$this->view->translate('work.'.$status, $locale);
        $text .= "\n".$this->view->translate('work.ends', $locale, ['time' => $this->view->dateTime($shift->ends_at->toIso8601String(), $user)]);
        if ($status !== 'cancelled') {
            $text .= "\n".$this->view->translate($status === 'completed' ? 'work.reward_received' : 'work.reward', $locale, [
                'coins' => $shift->coins_reward, 'gems' => $shift->gems_reward,
            ]);
        }
        $rows = [];
        if ($shift->completed_at === null && $shift->cancelled_at === null) {
            $rows[] = [$this->view->button($status === 'ready' ? 'work.complete' : 'refresh', 'work.complete', $locale, ['token' => $shift->token])];
        }

        return $this->view->screen(($notice === null ? '' : $notice."\n\n").$text, $rows, $locale, ['action' => 'work', 'tab' => 'shifts']);
    }

    /** @param Command $command */
    public function complete(User $user, string $locale, array $command): TelegramScreen
    {
        $shift = DogWorkShift::query()->where('user_id', $user->id)->where('token', strtolower((string) $command['token']))->firstOrFail();
        $notice = null;
        try {
            $shift = $this->completeWork->handle($user, $shift->token);
        } catch (PetUnavailable $exception) {
            $notice = $this->view->reason($exception->getMessage(), $locale);
            $shift->refresh();
        }

        return $this->show($user, $locale, $shift, $notice);
    }

    private function status(DogWorkShift $shift): string
    {
        return match (true) {
            $shift->cancelled_at !== null => 'cancelled',
            $shift->completed_at !== null => 'completed',
            $shift->ends_at->isPast() || $shift->ends_at->equalTo(now()) => 'ready',
            default => 'pending',
        };
    }

    private function shiftName(DogWorkShift $shift, string $locale): string
    {
        return Str::limit($shift->name[$locale] ?? $shift->name['en'] ?? '', 120);
    }
}
