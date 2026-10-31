<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Players\Queries\GetPlayerProfile;
use App\Modules\Telegram\DTO\TelegramScreen;

final class PlayerMenu
{
    public function __construct(private MenuView $view, private GetPlayerProfile $profiles) {}

    public function home(User $user, string $locale): TelegramScreen
    {
        return new TelegramScreen($this->view->escape($this->view->translate('welcome_player', $locale, ['name' => $user->name])), [
            [$this->view->button('pets', 'pets', $locale), $this->view->button('profile', 'profile', $locale)],
            [$this->view->button('inventory', 'inventory', $locale), $this->view->button('shop', 'shop', $locale)],
            [$this->view->button('help', 'help', $locale), $this->view->website($locale)],
        ]);
    }

    public function profile(User $user, string $locale): TelegramScreen
    {
        $profile = $this->profiles->handle($user);
        $wallet = $user->fresh();
        $lines = [$profile->name, '@'.$profile->username,
            $this->view->translate('level', $locale).': '.$profile->level,
            $this->view->translate('experience', $locale).': '.$profile->experience,
            $this->view->translate('progress', $locale).': '.round($profile->progress['percent']).'% ('.$profile->progress['levelExperience'].'/'.$profile->progress['requiredExperience'].')',
            $this->view->translate('coins', $locale).': '.$wallet?->coins.' · '.$this->view->translate('gems', $locale).': '.$wallet?->gems,
            $this->view->translate('pets', $locale).': '.$profile->dogsCount,
            $this->view->translate('walks', $locale).': '.$profile->walksCount.' · '.$this->view->translate('trainings', $locale).': '.$profile->trainingsCount,
            $this->view->translate('competition_wins', $locale).': '.$profile->competitionWins,
            $this->view->translate('exhibition_wins', $locale).': '.$profile->exhibitionWins,
            $this->view->translate('actions_count', $locale).': '.$profile->statistics['actionsCount'],
            $this->view->translate('active_days', $locale).': '.$profile->statistics['activeDays'],
        ];

        return $this->view->screen(implode("\n", $lines), [], $locale);
    }

    public function help(string $locale): TelegramScreen
    {
        return $this->view->screen($this->view->translate('help_text', $locale), [], $locale);
    }
}
