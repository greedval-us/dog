<?php

namespace App\Modules\Pets\DTO;

use App\Models\PetTitle;
use App\Modules\Pets\Calculators\PetAgeRules;
use Carbon\CarbonImmutable;

final readonly class PetTitleProgressData
{
    /**
     * @param  list<array<string,mixed>>  $goals
     * @param  array<int,PetTitle>  $titles
     * @return array{generatedAt:string,goals:list<array<string,mixed>>}
     */
    public static function fromGoals(array $goals, array $titles, string $locale, int $at): array
    {
        return [
            'generatedAt' => CarbonImmutable::createFromTimestampUTC($at)->toIso8601String(),
            'goals' => array_map(fn (array $goal): array => self::goal($goal, $titles, $locale), $goals),
        ];
    }

    /**
     * @param  array<string,mixed>  $goal
     * @param  array<int,PetTitle>  $titles
     * @return array<string,mixed>
     */
    private static function goal(array $goal, array $titles, string $locale): array
    {
        $paths = array_values($goal['paths'] ?? []);
        $best = self::recommendedPath($paths);
        $status = self::status($goal, $paths);
        $scope = self::scopeLabel($goal, $locale);
        $code = $goal['code'];
        $discipline = $goal['discipline'];

        return [
            'key' => $goal['key'] ?? $discipline.':'.$code.':'.($goal['scope_key'] ?? ''),
            'code' => $code, 'name' => self::awardName($code, $locale), 'discipline' => $discipline,
            'scopeLabel' => $scope, 'status' => $status, 'repeatable' => (bool) ($goal['repeatable'] ?? false),
            'earnedCount' => (int) ($goal['earnedCount'] ?? 0),
            'summary' => $goal['summary'] ?? __('title_progress.summaries.'.$status, [], $locale),
            'paths' => array_map(fn (array $path, int $index): array => self::path($path, $titles, $locale, $index === $best), $paths, array_keys($paths)),
        ];
    }

    /**
     * @param  array<string,mixed>  $path
     * @param  array<int,PetTitle>  $titles
     * @return array<string,mixed>
     */
    private static function path(array $path, array $titles, string $locale, bool $recommended): array
    {
        $seen = [];
        $requirements = [];
        foreach ($path['requirements'] ?? [] as $requirement) {
            $key = $requirement['key'];
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $requirements[] = self::requirement($requirement, $locale, $seen[$key] === 1 ? $key : $key.':'.$seen[$key]);
        }
        [$credentials, $missing] = self::credentials($path, $titles, $locale);
        $notes = array_values($path['notes'] ?? []);
        if ($missing) {
            $notes[] = __('title_progress.notes.missing_documents', [], $locale);
        }
        $label = self::label('paths.'.$path['key'], $locale, __('title_progress.paths.other', [], $locale));

        return [
            'key' => $path['key'], 'label' => $label, 'ready' => (bool) ($path['ready'] ?? false),
            'recommended' => $recommended, 'requirements' => $requirements, 'credentials' => $credentials,
            'events' => array_values($path['events'] ?? []), 'notes' => array_values(array_unique($notes)),
        ];
    }

    /**
     * @param  array{key:string,kind?:string,current?:int|float,required?:int|float,met?:bool,codes?:list<string>,showLeg?:string,ready_at?:int}  $requirement
     * @return array{key:string,label:string,current:int|float,target:int|float,met:bool,note:string|null}
     */
    private static function requirement(array $requirement, string $locale, string $key): array
    {
        $rawKey = $requirement['key'];
        $kind = $requirement['kind'] ?? 'count';
        $current = $requirement['current'] ?? 0;
        $required = $requirement['required'] ?? 1;
        $codes = $requirement['codes'] ?? [];
        $codeLabels = implode(' / ', array_map(fn (string $code): string => self::awardName($code, $locale), $codes));
        $labelKey = str_starts_with($rawKey, 'count:') ? 'certificates' : $rawKey;
        $label = self::label('requirements.'.$labelKey, $locale, __('title_progress.requirements.other', [], $locale));
        if ($codeLabels !== '' && in_array($labelKey, ['certificates', 'specialty_certificates', 'titles'], true)) {
            $label .= ': '.$codeLabels;
        }
        if (is_string($requirement['showLeg'] ?? null) && $requirement['showLeg'] !== '') {
            $label .= ' · '.__('events.show_legs.'.$requirement['showLeg'], [], $locale);
        }
        $note = null;
        if ($kind === 'interval') {
            $current = round($current / PetAgeRules::SECONDS_PER_MONTH, 4);
            $required = round($required / PetAgeRules::SECONDS_PER_MONTH, 4);
            if (is_int($requirement['ready_at'] ?? null) && ! ($requirement['met'] ?? false)) {
                $date = CarbonImmutable::createFromTimestampUTC($requirement['ready_at'])->setTimezone('Europe/Moscow')->format('d.m.Y H:i');
                $note = self::message('notes.interval', ['date' => $date], $locale);
            }
        } elseif ($kind === 'compatible') {
            $note = self::message('notes.compatible_evidence', [], $locale);
        }

        return ['key' => $key, 'label' => $label, 'current' => $current, 'target' => $required, 'met' => (bool) ($requirement['met'] ?? false), 'note' => $note];
    }

    /**
     * @param  array<string,mixed>  $path
     * @param  array<int,PetTitle>  $titles
     * @return array{list<array<string,mixed>>,bool}
     */
    private static function credentials(array $path, array $titles, string $locale): array
    {
        $proof = $path['proof_title_ids'] ?? [];
        $available = $path['available_title_ids'] ?? [];
        $excluded = array_column($path['excluded'] ?? [], 'reason', 'title_id');
        $ids = array_values(array_unique([...$proof, ...$available, ...array_keys($excluded)]));
        $credentials = [];
        $missing = false;
        foreach ($ids as $id) {
            $title = $titles[$id] ?? null;
            if ($title === null) {
                $missing = true;

                continue;
            }
            $status = in_array($id, $proof, true) ? 'counted' : (in_array($id, $available, true) ? 'available' : match ($excluded[$id]) {
                'used', 'consumed' => 'used', 'expired' => 'expired', default => 'ineligible',
            });
            $reason = in_array($status, ['counted', 'available'], true) ? null
                : self::label('document_reasons.'.($excluded[$id] ?? $status), $locale, __('title_progress.document_reasons.ineligible', [], $locale));
            $data = PetTitleData::fromTitle($title, $locale);
            $entry = $title->relationLoaded('entry') ? $title->entry : null;
            $credentials[] = [
                'id' => (int) $id, 'name' => $data['name'], 'code' => $title->code,
                'awardedAt' => $data['awardedAt'], 'eventId' => $entry->game_event_id ?? $title->game_event_id,
                'status' => $status, 'reason' => $reason,
            ];
        }

        return [$credentials, $missing];
    }

    /**
     * @param  array<string,mixed>  $goal
     * @param  list<array<string,mixed>>  $paths
     */
    private static function status(array $goal, array $paths): string
    {
        if (in_array($goal['status'] ?? null, ['achieved', 'ready', 'in_progress', 'blocked', 'unavailable'], true)) {
            return $goal['status'];
        }
        if (! ($goal['repeatable'] ?? false) && (($goal['achieved'] ?? false) || ($goal['earnedCount'] ?? 0) > 0)) {
            return 'achieved';
        }
        if (in_array(true, array_column($paths, 'ready'), true)) {
            return 'ready';
        }
        $blocked = array_filter($paths, fn (array $path): bool => array_filter($path['requirements'] ?? [], fn (array $requirement): bool => ($requirement['kind'] ?? '') === 'document' && ! ($requirement['met'] ?? false)) !== []);

        return $paths !== [] && count($blocked) === count($paths) ? 'blocked' : 'in_progress';
    }

    /** @param list<array<string,mixed>> $paths */
    private static function recommendedPath(array $paths): ?int
    {
        $bestIndex = null;
        $bestScore = null;
        foreach ($paths as $index => $path) {
            $missing = array_values(array_filter($path['requirements'] ?? [], fn (array $requirement): bool => ! ($requirement['met'] ?? false)));
            $distance = array_sum(array_map(fn (array $requirement): float => max(0, ($requirement['required'] ?? 1) - ($requirement['current'] ?? 0)) / max(1, $requirement['required'] ?? 1), $missing));
            $score = [(int) ! ($path['ready'] ?? false), count($missing), $distance];
            if ($bestScore === null || $score < $bestScore) {
                $bestIndex = $index;
                $bestScore = $score;
            }
        }

        return $bestIndex;
    }

    /** @param array<string,mixed> $goal */
    private static function scopeLabel(array $goal, string $locale): ?string
    {
        $scope = $goal['scope_key'] ?? '';
        if ($scope === 'region:any') {
            return __('title_progress.scopes.any_region', [], $locale);
        }
        if (str_starts_with($scope, 'region:')) {
            $region = explode(':', $scope)[1];

            return self::eventLabel('regions.'.$region, $locale, $region);
        }
        $edition = str_starts_with($scope, 'edition:') ? substr($scope, 8) : ($goal['details']['showEdition'] ?? null);
        if (is_string($edition) && $edition !== '') {
            return __('events.game_edition', ['edition' => $edition], $locale);
        }
        if (is_int($goal['details']['showYear'] ?? null)) {
            return __('events.game_year', ['year' => $goal['details']['showYear']], $locale);
        }

        return null;
    }

    private static function awardName(string $code, string $locale): string
    {
        return self::eventLabel('regulated_awards.'.$code, $locale, $code);
    }

    private static function eventLabel(string $key, string $locale, string $fallback): string
    {
        $translation = __('events.'.$key, [], $locale);

        return $translation === 'events.'.$key ? $fallback : $translation;
    }

    private static function label(string $key, string $locale, string $fallback): string
    {
        $translation = __('title_progress.'.$key, [], $locale);

        return $translation === 'title_progress.'.$key ? $fallback : $translation;
    }

    /** @param array<string,string|int|float> $replacements */
    private static function message(string $key, array $replacements, string $locale): string
    {
        $translation = __('title_progress.'.$key, $replacements, $locale);

        return is_string($translation) ? $translation : '';
    }
}
