<?php

namespace App\Modules\Pets\DTO;

final readonly class TrainPetSkillData
{
    public function __construct(public int $skillId, public int $level, public int $expectedPrice, public string $token) {}
}
