<?php

namespace App\Modules\Players\DTO;

final readonly class PlayerAvatarData
{
    public function __construct(public string $temporaryPath) {}
}
