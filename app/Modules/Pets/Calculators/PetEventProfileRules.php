<?php

namespace App\Modules\Pets\Calculators;

/**
 * @phpstan-type BreedProfile array{recognized_rkf:bool,recognized_fci:bool,fci_group:int|null,working_breed:bool,club_system:string,height_ranges:array{male:array{float,float},female:array{float,float}}}
 * @phpstan-type Snapshot array{height_cm:float,recognized_rkf:bool,recognized_fci:bool,fci_group:int|null,working_breed:bool,club_system:string,registered:bool,pedigree_verified:bool,working_certificate_verified:bool,race_category:string}
 */
final readonly class PetEventProfileRules
{
    /**
     * Height ranges generate a game attribute; they are not breed-standard admission limits.
     *
     * @param  array<string,mixed>  $metadata
     * @return BreedProfile
     */
    public function breedProfile(array $metadata, string $size): array
    {
        $fallback = match ($size) {
            'small' => [18.0, 39.0], 'large' => [43.0, 75.0], default => [35.0, 55.0],
        };
        $ranges = is_array($metadata['height_ranges'] ?? null) ? $metadata['height_ranges'] : [];

        return [
            'recognized_rkf' => (bool) ($metadata['recognized_rkf'] ?? false),
            'recognized_fci' => (bool) ($metadata['recognized_fci'] ?? false),
            'fci_group' => isset($metadata['fci_group']) && is_numeric($metadata['fci_group']) && $metadata['fci_group'] >= 1 && $metadata['fci_group'] <= 10 ? (int) $metadata['fci_group'] : null,
            'working_breed' => (bool) ($metadata['working_breed'] ?? false),
            'club_system' => $this->clubSystem($metadata['club_system'] ?? null, (bool) ($metadata['recognized_rkf'] ?? false)),
            'height_ranges' => [
                'male' => $this->heightRange($ranges['male'] ?? null, $fallback),
                'female' => $this->heightRange($ranges['female'] ?? null, $fallback),
            ],
        ];
    }

    /**
     * @param  BreedProfile  $breed
     * @param  array<string,mixed>  $existing
     * @return Snapshot
     */
    public function initialSnapshot(string $identity, string $sex, bool $purebred, array $breed, array $existing = []): array
    {
        $range = $breed['height_ranges'][$sex === 'female' ? 'female' : 'male'];
        $minimum = (int) round($range[0] * 10);
        $maximum = (int) round($range[1] * 10);
        $height = ($minimum + (int) (hexdec(substr(hash('sha256', $identity), 0, 8)) % ($maximum - $minimum + 1))) / 10;
        $recognized = (bool) ($existing['recognized_rkf'] ?? $breed['recognized_rkf']);
        $registered = (bool) ($existing['registered'] ?? ($purebred && $recognized));

        return [
            'height_cm' => isset($existing['height_cm']) && is_numeric($existing['height_cm']) ? (float) $existing['height_cm'] : $height,
            'recognized_rkf' => $recognized,
            'recognized_fci' => (bool) ($existing['recognized_fci'] ?? $breed['recognized_fci']),
            'fci_group' => array_key_exists('fci_group', $existing) ? ($existing['fci_group'] === null ? null : (int) $existing['fci_group']) : $breed['fci_group'],
            'working_breed' => (bool) ($existing['working_breed'] ?? $breed['working_breed']),
            'club_system' => $this->clubSystem($existing['club_system'] ?? $breed['club_system'], $recognized),
            'registered' => $registered,
            'pedigree_verified' => (bool) ($existing['pedigree_verified'] ?? false),
            'working_certificate_verified' => (bool) ($existing['working_certificate_verified'] ?? false),
            'race_category' => in_array($existing['race_category'] ?? null, ['1', '2-RUS', '3', '4'], true) ? $existing['race_category'] : ($purebred && $recognized && $registered ? '3' : '4'),
        ];
    }

    public function heightCategory(float $height): string
    {
        return match (true) {
            $height < 35 => 'S', $height < 43 => 'M', $height < 48 => 'I', default => 'L',
        };
    }

    public function clubSystem(mixed $registry, bool $recognized): string
    {
        return in_array($registry, ['nkp', 'breed', 'rspu', 'none'], true) ? $registry : ($recognized ? 'breed' : 'none');
    }

    /**
     * @param  array{float,float}  $fallback
     * @return array{float,float}
     */
    private function heightRange(mixed $range, array $fallback): array
    {
        if (! is_array($range) || ! isset($range[0], $range[1]) || ! is_numeric($range[0]) || ! is_numeric($range[1]) || $range[0] <= 0 || $range[1] < $range[0]) {
            return $fallback;
        }

        return [(float) $range[0], (float) $range[1]];
    }
}
