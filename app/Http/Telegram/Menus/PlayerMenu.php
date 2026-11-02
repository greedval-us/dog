<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Players\Queries\GetPlayerProfile;
use App\Modules\Players\Queries\GetUnreadSystemNotificationCount;
use App\Modules\Telegram\DTO\TelegramScreen;

final class PlayerMenu
{
    public function __construct(private MenuView $view, private GetPlayerProfile $profiles, private GetUnreadSystemNotificationCount $unread, private HomeSummary $summary) {}

    public function home(User $user, string $locale): TelegramScreen
    {
        $unread = $this->unread->handle($user);
        $notifications = $this->view->label('notifications.title', $locale).($unread > 0 ? ' ('.$unread.')' : '');
        $summary = $this->summary->build($user, $locale);

        return new TelegramScreen($this->view->heading($this->view->translate('welcome_player', $locale, ['name' => $user->name]))."\n\n".$summary->text, [
            ...$summary->rows,
            [$this->view->button('pets', 'pets', $locale), $this->view->button('profile', 'profile', $locale)],
            [$this->view->button('kennel.title', 'kennel', $locale), $this->view->button('slots.title', 'slots', $locale)],
            [$this->view->button('market.title', 'market', $locale), $this->view->button('market.own', 'market', $locale, ['tab' => 'own'])],
            [$this->view->button('inventory', 'inventory', $locale), $this->view->button('shop', 'shop', $locale)],
            [$this->view->button('daily_work.title', 'daily_work', $locale), $this->view->button('work.title', 'work', $locale)],
            [$this->view->button('event.competitions', 'events', $locale, ['kind' => 'competition']), $this->view->button('event.exhibitions', 'events', $locale, ['kind' => 'exhibition'])],
            [$this->view->commandButton($notifications, ['action' => 'notifications'])],
            [$this->view->button('help', 'help', $locale), $this->view->website($locale)],
        ]);
    }

    public function profile(User $user, string $locale): TelegramScreen
    {
        $profile = $this->profiles->handle($user);
        $wallet = $user->fresh();
        $lines = [$this->view->label('profile', $locale), $profile->name, '@'.$profile->username,
            '', $this->view->translate('sections.progress', $locale),
            $this->view->translate('level', $locale).': '.$profile->level,
            $this->view->translate('experience', $locale).': '.$profile->experience,
            $this->view->translate('progress', $locale).': '.$this->view->bar($profile->progress['percent']).' '.round($profile->progress['percent']).'% ('.$profile->progress['levelExperience'].'/'.$profile->progress['requiredExperience'].')',
            '',
            $this->view->translate('coins', $locale).': '.$wallet?->coins.' · '.$this->view->translate('gems', $locale).': '.$wallet?->gems,
            '', $this->view->translate('sections.activity', $locale),
            $this->view->translate('pets', $locale).': '.$profile->dogsCount,
            $this->view->translate('walks', $locale).': '.$profile->walksCount.' · '.$this->view->translate('trainings', $locale).': '.$profile->trainingsCount,
            $this->view->translate('actions_count', $locale).': '.$profile->statistics['actionsCount'],
            $this->view->translate('active_days', $locale).': '.$profile->statistics['activeDays'],
            '', $this->view->translate('sections.achievements', $locale),
            $this->view->translate('competition_wins', $locale).': '.$profile->competitionWins,
            $this->view->translate('exhibition_wins', $locale).': '.$profile->exhibitionWins,
        ];

        return $this->view->screen(implode("\n", $lines), [], $locale);
    }

    public function help(string $locale): TelegramScreen
    {
        return $this->view->screen($this->view->translate('help_text', $locale), [], $locale);
    }
}
