<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\DTO\GameEventProtocol;
use InvalidArgumentException;

/** @phpstan-import-type Result from GameEventProtocol */
final class GameEventSimulator
{
    public const VERSION = GameEventSimulatorV1::VERSION;

    public function __construct(private GameEventSimulatorV1 $versionOne = new GameEventSimulatorV1) {}

    /**
     * @param  array{eliminated:bool, penalties:int, score:float, time:float}  $first
     * @param  array{eliminated:bool, penalties:int, score:float, time:float}  $second
     */
    public function compareResults(string $discipline, array $first, array $second, int $version = self::VERSION): int
    {
        return $this->implementation($version)->compareResults($discipline, $first, $second);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $rules
     * @param  list<float>  $draws
     * @return Result
     */
    public function simulate(string $discipline, array $snapshot, array $plan, array $rules, array $draws): array
    {
        $protocol = GameEventProtocol::fromArrays($discipline, $snapshot, $rules);
        $implementation = $this->implementation($protocol->calculationVersion);
        if ($protocol->snapshotVersion !== $implementation::SNAPSHOT_VERSION) {
            throw new InvalidArgumentException('Unsupported event snapshot version.');
        }

        return $implementation->simulate($discipline, $snapshot, $plan, $rules, $draws);
    }

    private function implementation(int $version): GameEventSimulatorV1
    {
        return match ($version) {
            GameEventSimulatorV1::VERSION => $this->versionOne,
            default => throw new InvalidArgumentException('Unsupported event calculation version.'),
        };
    }
}
