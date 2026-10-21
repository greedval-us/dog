<?php

namespace App\Modules\Pets\Calculators;

use InvalidArgumentException;

final class ShowGroupRules
{
    public function __construct(private ProgenyRingRules $progeny = new ProgenyRingRules) {}

    /** @param list<array{id:int,breed_id:int,sex:string,owner_id:?int,breeder_key:?string,kennel_key:?string}> $members */
    public function compositionReason(string $discipline, array $members): ?string
    {
        $count = count($members);
        if (($discipline === 'couple' && $count !== 2)
            || ($discipline === 'breeders' && ($count < 3 || $count > 5))
            || ! in_array($discipline, ['couple', 'breeders'], true)
            || count(array_unique(array_column($members, 'id'))) !== $count
            || count(array_unique(array_column($members, 'breed_id'))) !== 1) {
            return 'events.errors.group_members';
        }
        foreach (array_slice($members, 1) as $member) {
            $reason = $this->candidateReason($discipline, $members[0], $member);
            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * @param  array{id:int,breed_id:int,sex:string,owner_id:?int,breeder_key:?string,kennel_key:?string}  $anchor
     * @param  array{id:int,breed_id:int,sex:string,owner_id:?int,breeder_key:?string,kennel_key:?string}  $candidate
     */
    public function candidateReason(string $discipline, array $anchor, array $candidate): ?string
    {
        if (! in_array($discipline, ['couple', 'breeders'], true) || $anchor['id'] === $candidate['id'] || $anchor['breed_id'] !== $candidate['breed_id']) {
            return 'events.errors.group_members';
        }
        if ($discipline === 'couple') {
            $sexes = [$anchor['sex'], $candidate['sex']];
            sort($sexes);

            return $sexes === ['female', 'male'] && $anchor['owner_id'] !== null
                && $anchor['owner_id'] === $candidate['owner_id'] ? null : 'events.errors.couple';
        }
        foreach (['breeder_key', 'kennel_key'] as $key) {
            if ($anchor[$key] === null || trim($anchor[$key]) === '' || $anchor[$key] !== $candidate[$key]) {
                return 'events.errors.kennel_origin';
            }
        }

        return null;
    }

    /** @param array{status:?string,class:?string,grade:?string} $member */
    public function memberReason(array $member, string $system, bool $final): ?string
    {
        $reason = $this->progeny->memberReason($member, $system, $final);

        return $reason === null ? null : str_replace('events.errors.progeny_', 'events.errors.group_', $reason);
    }

    /**
     * @param  list<array<string,mixed>>  $members
     * @return array{type:float,uniformity:float,movement:float}
     */
    public function criteria(array $members, string $format): array
    {
        $this->awardNames($format);

        return $this->progeny->criteria($members);
    }

    /**
     * @param  list<array<string,mixed>>  $entries
     * @return array{rankings:array<int,array{breed:int,final:?int}>,awards:list<array<string,mixed>>}
     */
    public function placements(array $entries, string $format): array
    {
        [$prefix, $winner] = $this->awardNames($format);
        $placements = $this->progeny->placements($entries);
        if ($format === 'progeny_ring') {
            return $placements;
        }
        foreach ($placements['awards'] as &$award) {
            $award['code'] = $award['code'] === 'BEST_PROGENY_GROUP' ? $winner : str_replace('PROGENY_', $prefix.'_', $award['code']);
        }
        unset($award);

        return $placements;
    }

    /** @return array{string,string} */
    private function awardNames(string $format): array
    {
        return match ($format) {
            'progeny_ring' => ['PROGENY', 'BEST_PROGENY_GROUP'],
            'couple_ring' => ['COUPLE', 'BEST_COUPLE'],
            'breeders_ring' => ['BREEDERS', 'BEST_BREEDERS_GROUP'],
            default => throw new InvalidArgumentException('Unknown collective show format.'),
        };
    }
}
