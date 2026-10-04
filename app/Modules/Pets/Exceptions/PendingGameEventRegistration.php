<?php

namespace App\Modules\Pets\Exceptions;

use DomainException;

final class PendingGameEventRegistration extends DomainException
{
    public function __construct()
    {
        parent::__construct('events.errors.processing');
    }
}
