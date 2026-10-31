<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\Actions\GenerateDogWorkBoard;
use App\Modules\Pets\Actions\StartDogWork;
use App\Modules\Pets\DTO\StartDogWorkData;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetDogWorkBoard;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 *
 * @phpstan-type WorkDraft array{petId: int, offerId: int, token: string}
 */
final class DogWorkMenu
{
    public function __construct(
        private MenuView $view,
        private GenerateDogWorkBoard $generateBoard,
        private GetDogWorkBoard $workBoard,
        private StartDogWork $startWork,
        private DogWorkShiftMenu $shifts,
    ) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command, ?string $notice = null): TelegramScreen
    {
        $petId = isset($command['petId']) ? (int) $command['petId'] : null;
        if ($petId !== null) {
            $user->pets()->active()->findOrFail($petId);
        }
        if (($command['tab'] ?? null) === 'shifts') {
            return $this->shifts->index($user, $locale, $command, $notice);
        }
        $board = $this->workBoard->handle($user, $this->generateBoard->handle(), $petId, $locale);
        $petId = $board['selectedPetId'];
        $text = $this->view->translate('work.title', $locale)."\n".$this->view->translate('work.resets', $locale, [
            'time' => $this->view->dateTime($board['resetsAt'], $user),
        ]);
        $dog = collect($board['dogs'])->firstWhere('id', $petId);
        $text .= "\n".($dog === null ? $this->view->translate('work.choose_dog', $locale) : Str::limit($dog['name'], 120));
        $rows = [];
        $offset = max(0, (int) ($command['offset'] ?? 0));
        foreach (array_slice($board['offers'], $offset, MenuView::PAGE_SIZE) as $offer) {
            $rows[] = [$this->view->commandButton($offer['name'], ['action' => 'work.offer', 'petId' => $petId, 'offerId' => $offer['id']])];
        }
        if ($board['offers'] === []) {
            $text .= "\n\n".$this->view->translate('work.empty', $locale);
        }
        $navigation = ['action' => 'work', ...($petId === null ? [] : ['petId' => $petId])];
        $rows = [...$rows, ...$this->view->offsetNavigation(count($board['offers']), $offset, $navigation, $locale)];
        $rows[] = [$this->view->button('work.shifts', 'work', $locale, [...$navigation, 'tab' => 'shifts'])];
        $rows[] = [$this->view->button('refresh', 'work', $locale, $navigation)];

        return $this->view->screen(($notice === null ? '' : $notice."\n\n").$text, $rows, $locale,
            $petId === null ? ['action' => 'pets'] : ['action' => 'pet', 'petId' => $petId]);
    }

    /** @param Command $command */
    public function offer(User $user, string $locale, array $command): TelegramScreen
    {
        $petId = isset($command['petId']) ? (int) $command['petId'] : null;
        $board = $this->workBoard->handle($user, $this->generateBoard->handle(), $petId, $locale);
        $offer = collect($board['offers'])->firstWhere('id', (int) $command['offerId']);
        if ($offer === null) {
            throw new ModelNotFoundException;
        }
        $text = Str::limit($offer['name'], 120)."\n".Str::limit($offer['description'], 350)."\n\n".$this->view->translate('work.requirement', $locale, [
            'skill' => Str::limit($offer['skill'], 100), 'level' => $offer['skillLevel'], 'learned' => $offer['dogSkillLevel'],
        ])."\n".$this->view->translate('work.terms', $locale, [
            'seconds' => $offer['duration'], 'energy' => $offer['energy'],
        ])."\n".$this->view->translate('work.reward', $locale, [
            'coins' => $offer['coins'], 'gems' => $offer['gems'],
        ])."\n".$this->view->translate('work.places', $locale, ['places' => $offer['places'], 'limit' => $offer['limit']]);
        $back = ['action' => 'work', ...($board['selectedPetId'] === null ? [] : ['petId' => $board['selectedPetId']])];
        if ($offer['reason'] !== null) {
            return $this->view->screen($text."\n\n".$this->view->reason($offer['reason'], $locale), [], $locale, $back);
        }
        $draft = ['petId' => $board['selectedPetId'], 'offerId' => $offer['id'], 'token' => (string) Str::uuid()];

        return $this->view->screen($text."\n\n".$this->view->translate('work.confirm', $locale), [
            [$this->view->button('work.start', 'work.start', $locale)],
        ], $locale, $back, ['work' => $draft]);
    }

    /** @param State $state */
    public function start(User $user, string $locale, array $state): TelegramScreen
    {
        $draft = $this->draft($state);
        try {
            $shift = $this->startWork->handle($user, $draft['petId'], new StartDogWorkData($draft['offerId'], $draft['token']));
        } catch (PetUnavailable $exception) {
            return $this->index($user, $locale, ['action' => 'work', 'petId' => $draft['petId']], $this->view->reason($exception->getMessage(), $locale));
        }

        return $this->shifts->show($user, $locale, $shift, $this->view->translate('work.started', $locale));
    }

    /** @param Command $command */
    public function complete(User $user, string $locale, array $command): TelegramScreen
    {
        return $this->shifts->complete($user, $locale, $command);
    }

    /**
     * @param  State  $state
     * @return WorkDraft
     */
    private function draft(array $state): array
    {
        $draft = new SavedMenuState($state, 'work');

        return ['petId' => $draft->integer('petId'), 'offerId' => $draft->integer('offerId'), 'token' => $draft->uuid()];
    }
}
