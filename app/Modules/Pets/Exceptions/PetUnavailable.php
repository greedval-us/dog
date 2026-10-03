<?php

namespace App\Modules\Pets\Exceptions;

use App\Modules\Pets\Enums\CareRefusal;
use DomainException;

final class PetUnavailable extends DomainException
{
    public function __construct(string $message = '', public readonly ?CareRefusal $reason = null)
    {
        parent::__construct($message);
    }

    public static function forCare(CareRefusal $reason): self
    {
        return new self($reason->message(), $reason);
    }
}
