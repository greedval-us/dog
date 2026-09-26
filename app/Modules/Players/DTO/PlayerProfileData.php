<?php

namespace App\Modules\Players\DTO;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, int|string|null> */
final readonly class PlayerProfileData implements Arrayable
{
    public function __construct(
        public string $name,
        public string $username,
        public ?string $avatarVersion,
        public ?string $bio,
        public int $level,
        public int $experience,
        public int $dogsCount,
        public int $exhibitionWins,
        public int $competitionWins,
        public int $walksCount,
        public int $trainingsCount,
        public ?string $joinedAt,
    ) {}

    public static function fromModel(User $user, int $dogsCount): self
    {
        return new self(
            name: $user->name,
            username: $user->username,
            avatarVersion: $user->avatarVersion(),
            bio: $user->bio,
            level: $user->level,
            experience: $user->experience,
            dogsCount: $dogsCount,
            exhibitionWins: $user->exhibition_wins,
            competitionWins: $user->competition_wins,
            walksCount: $user->walks_count,
            trainingsCount: $user->trainings_count,
            joinedAt: $user->created_at?->toDateString(),
        );
    }

    /** @return array{name: string, username: string, avatarVersion: string|null, bio: string|null, level: int, experience: int, dogsCount: int, exhibitionWins: int, competitionWins: int, walksCount: int, trainingsCount: int, joinedAt: string|null} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'avatarVersion' => $this->avatarVersion,
            'bio' => $this->bio,
            'level' => $this->level,
            'experience' => $this->experience,
            'dogsCount' => $this->dogsCount,
            'exhibitionWins' => $this->exhibitionWins,
            'competitionWins' => $this->competitionWins,
            'walksCount' => $this->walksCount,
            'trainingsCount' => $this->trainingsCount,
            'joinedAt' => $this->joinedAt,
        ];
    }
}
