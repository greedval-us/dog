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
        $screen = $this->render($user, $account, $command);
        if ($screen === null) {
            return;
        }
        $account->refresh();
        $this->messenger->publish($account, $screen);
        $this->queueCompletion($account, $screen);
    }

    /** @param array<string,mixed> $command */
    private function render(User $user, TelegramAccount $account, array $command): ?TelegramScreen
    {
        $action = $command['action'] ?? 'home';
        if (is_string($action) && in_array(explode('.', $action, 2)[0], ['events', 'market'], true)) {
            $context = DB::transaction(fn (): ?array => $this->context($user, $account), attempts: 3);

            return $context === null ? null : $this->menu->render($context['owner'], $command, $context['state']);
        }

        return DB::transaction(function () use ($user, $account, $command): ?TelegramScreen {
            $context = $this->context($user, $account);

            return $context === null ? null : $this->menu->render($context['owner'], $command, $context['state']);
        }, attempts: 3);
    }

    /** @return array{owner:User,state:array<string,mixed>}|null */
    private function context(User $user, TelegramAccount $account): ?array
    {
        $owner = User::query()->lockForUpdate()->findOrFail($user->id);
        $linked = TelegramAccount::query()->whereKey($account->id)->where('user_id', $owner->id)->lockForUpdate()->first();
        if ($linked === null || $owner->status !== PlayerStatus::Active) {
            return null;
        }
        $state = $linked->menu_state['data'] ?? [];

        return ['owner' => $owner, 'state' => is_array($state) ? $state : []];
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
