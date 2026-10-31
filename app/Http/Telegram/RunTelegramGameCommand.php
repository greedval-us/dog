<?php

namespace App\Http\Telegram;

use App\Jobs\RefreshTelegramCare;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Modules\Pets\Actions\RecordPetOwnerActivity;
use App\Modules\Pets\Exceptions\PendingGameEventRegistration;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Telegram\DTO\TelegramScreen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RunTelegramGameCommand
{
    public function __construct(
        private RecordPetOwnerActivity $activity,
        private GameEventProcessor $events,
        private PetLifecycle $lifecycle,
        private GameMenu $menu,
        private MenuMessenger $messenger,
    ) {}

    /** @param array<string, mixed> $command */
    public function handle(TelegramAccount $account, array $command): void
    {
        $user = $account->user;
        $this->synchronize($user);
        $screen = DB::transaction(function () use ($user, $account, $command): ?TelegramScreen {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $linked = TelegramAccount::query()->whereKey($account->id)->where('user_id', $owner->id)->lockForUpdate()->first();
            if ($linked === null || $owner->status !== PlayerStatus::Active) {
                return null;
            }

            return $this->menu->render($owner, $command, $linked->menu_state['data'] ?? []);
        }, attempts: 3);
        if ($screen === null) {
            return;
        }
        $account->refresh();
        $this->messenger->publish($account, $screen);
        $this->queueCompletion($account, $screen);
    }

    private function synchronize(User $user): void
    {
        $at = CarbonImmutable::now()->startOfSecond();
        $this->activity->handle($user, $at);
        $limit = (int) config('game-events.processing.http_batch_size', 0);
        if ($limit > 0) {
            $this->events->processDue($at, $user, $limit);
        }
        if ($this->events->hasDueRegistrations($user, $at)) {
            throw new PendingGameEventRegistration;
        }
        $this->lifecycle->synchronizeOwner($user, $at);
        $user->refresh();
    }

    private function queueCompletion(TelegramAccount $account, TelegramScreen $screen): void
    {
        $care = $screen->state['care'] ?? null;
        if (! is_array($care) || config('queue.default') === 'sync') {
            return;
        }
        if (isset($care['petId'], $care['token'], $care['endsAt'])) {
            RefreshTelegramCare::dispatch($account->id, (int) $care['petId'], (string) $care['token'], $account->menu_revision)
                ->delay(CarbonImmutable::parse($care['endsAt'])->addSecond())->afterCommit();
        }
    }
}
