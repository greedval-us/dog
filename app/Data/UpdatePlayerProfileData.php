<?php

namespace App\Data;

final readonly class UpdatePlayerProfileData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $bio,
        public bool $bioProvided,
    ) {}
}
