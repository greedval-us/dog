<?php

namespace App\Modules\Pets\DTO;

use App\Modules\Pets\Enums\GameEventDiscipline;
use InvalidArgumentException;

/**
 * @phpstan-type Decision 'careful'|'balanced'|'bold'
 * @phpstan-type Plan array{stages:list<Decision>, offspring_ids?:list<int>}
 * @phpstan-type Pedigree array{generation:int, knownParents:int<0, 2>}
 * @phpstan-type BaseSnapshot array{version:int, name:string, breed:string, breed_id:int, size:string, stats:array<string, int>, potentials:array<string, int>, states:array<string, int|float>, skills:array<string, int>, exterior:array<string, int|float>, career_experience:int, pedigree?:Pedigree}
 * @phpstan-type PetSnapshot BaseSnapshot&array{pedigree:Pedigree}
 * @phpstan-type GearModifiers array{precision?:int|float, stamina?:int|float, pace?:int|float, focus?:int|float}
 * @phpstan-type Gear array{id:int, name:array<string, string>, slot:'body'|'line'|'handler'|'preparation', phase:'preparation'|'performance', modifiers:GearModifiers}
 * @phpstan-type OffspringTitle array{code:string, discipline?:string, frequency?:string}
 * @phpstan-type Offspring array{id?:int, name?:string, exterior:array<string, int|float>, titles:list<OffspringTitle>}
 * @phpstan-type Snapshot array{version:int, name:string, breed:string, breed_id:int, size:string, stats:array<string, int>, potentials:array<string, int>, states:array<string, int|float>, skills:array<string, int>, exterior:array<string, int|float>, career_experience:int, pedigree?:Pedigree, gear:list<Gear>, modifiers:GearModifiers, offspring:list<Offspring>}
 * @phpstan-type Rules array{version:int, fee:int, prizes:list<int>, stages:list<string>, energy_cost:int, field_size:int, time_limit:int}
 * @phpstan-type Modifiers array{precision:float, stamina:float, pace:float, focus:float}
 * @phpstan-type Preparation array{careMultiplier:float, initialFatigue:float, initialFocus:float, states:array<string, float>, normalizedStats:array<string, float>, modifiers:Modifiers}
 * @phpstan-type StageFactors array{quality:float, careMultiplier:float, mistakeChance:float, startFatigue:float, startFocus:float, exterior:float|null, exteriorContribution:float, presentationContribution:float}
 * @phpstan-type Stage array{key:string, decision:Decision, time:float, penalties:int, score:float, fatigue:float, focus:float, note:string, factors?:StageFactors}
 * @phpstan-type Result array{version:int, score:float, time:float, penalties:int, eliminated:bool, stages:list<Stage>, preparation?:Preparation}
 */
final readonly class GameEventProtocol
{
    public const SNAPSHOT_VERSION = 1;

    public function __construct(public GameEventDiscipline $discipline, public int $calculationVersion, public int $snapshotVersion) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $rules
     */
    public static function fromArrays(string $discipline, array $snapshot, array $rules): self
    {
        $kind = GameEventDiscipline::tryFrom($discipline);
        $calculationVersion = $rules['version'] ?? null;
        $snapshotVersion = $snapshot['version'] ?? null;
        if ($kind === null || ! is_int($calculationVersion) || ! is_int($snapshotVersion)) {
            throw new InvalidArgumentException('Invalid event protocol.');
        }

        return new self($kind, $calculationVersion, $snapshotVersion);
    }
}
