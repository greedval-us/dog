<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class ActivityMenuRoutes
{
    public function __construct(
        private CareMenu $care,
        private SkillMenu $skills,
        private DogWorkMenu $work,
        private VeterinarianMenu $clinic,
    ) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function render(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        return match ($command['action'] ?? 'home') {
            'care.groups', 'care.variants' => $this->care->options($user, $locale, $command),
            'care.select' => $this->care->select($user, $locale, $command),
            'care.items', 'care.choose', 'care.skip', 'care.confirm' => $this->care->items($user, $locale, $command, $state),
            'care.start' => $this->care->start($user, $locale, $state),
            'care.complete' => $this->care->complete($user, $locale, $command),
            'skills' => $this->skills->index($user, $locale, $command),
            'skills.lesson' => $this->skills->lesson($user, $locale, $command),
            'skills.train' => $this->skills->train($user, $locale, $state),
            'work' => $this->work->index($user, $locale, $command),
            'work.offer' => $this->work->offer($user, $locale, $command),
            'work.start' => $this->work->start($user, $locale, $state),
            'work.complete' => $this->work->complete($user, $locale, $command),
            'clinic' => $this->clinic->index($user, $locale, $command),
            'clinic.diseases' => $this->clinic->diseases($user, $locale, $command),
            'clinic.service' => $this->clinic->service($user, $locale, $command),
            'clinic.perform' => $this->clinic->perform($user, $locale, $state),
            'clinic.history' => $this->clinic->history($user, $locale, $command),
            default => throw new InvalidArgumentException('Unknown Telegram menu action.'),
        };
    }
}
