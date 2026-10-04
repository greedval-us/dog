<?php

namespace App\Modules\Pets\Enums;

enum GameEventDiscipline: string
{
    case Agility = 'agility';
    case Nosework = 'nosework';
    case Canicross = 'canicross';
    case Conformation = 'conformation';
    case Progeny = 'progeny';

    public function isExhibition(): bool
    {
        return $this === self::Conformation || $this === self::Progeny;
    }

    public function isDocumentary(): bool
    {
        return $this === self::Progeny;
    }

    public function ranksByPenalties(): bool
    {
        return $this === self::Agility || $this === self::Nosework;
    }

    public function divisionGroup(string $size, int $breedId): string
    {
        return match ($this) {
            self::Agility => $size,
            self::Conformation, self::Progeny => 'breed-'.$breedId,
            default => 'all',
        };
    }

    /** @return list<string> */
    public static function exhibitions(): array
    {
        return array_map(fn (self $discipline): string => $discipline->value, array_values(array_filter(self::cases(), fn (self $discipline): bool => $discipline->isExhibition())));
    }
}
