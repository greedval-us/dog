<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;

class SendAccountNotifications
{
    public function __construct(private readonly Request $request) {}

    public function handleLogin(Login $event): void
    {
        if ($event->guard === config('fortify.guard') && $event->user instanceof User) {
            $event->user->notify(SystemNotification::login($this->request->ip()));
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $event->user->notify(SystemNotification::passwordReset());
        }
    }
}
