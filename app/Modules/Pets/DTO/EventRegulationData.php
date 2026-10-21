<?php

namespace App\Modules\Pets\DTO;

use App\Models\Dog;
use App\Models\GameEvent;
use App\Modules\Pets\Enums\GameEventDiscipline;

final readonly class EventRegulationData
{
    public static function isRegulated(GameEvent $event): bool
    {
        return ($event->rules['version'] ?? 0) === 3;
    }

    public static function isProgenyRing(GameEvent $event): bool
    {
        return self::isRegulated($event) && $event->discipline === 'progeny' && (self::rules($event)['format'] ?? '') === 'progeny_ring';
    }

    public static function isGroupRing(GameEvent $event): bool
    {
        return self::isProgenyRing($event) || (self::isRegulated($event)
            && in_array($event->discipline, ['couple', 'breeders'], true)
            && (self::rules($event)['format'] ?? '') === $event->discipline.'_ring');
    }

    /** @param array<string,mixed> $plan
     * @return list<int>
     */
    public static function memberIds(GameEvent $event, array $plan, ?int $anchor): array
    {
        $ids = $anchor === null ? [] : [$anchor];
        if (self::isGroupRing($event)) {
            $ids = [...$ids, ...($plan[self::isProgenyRing($event) ? 'offspring_ids' : 'member_ids'] ?? [])];
        }

        return array_values(array_unique($ids));
    }

    public static function isDocumentary(GameEvent $event): bool
    {
        return GameEventDiscipline::from($event->discipline)->isDocumentary() && ! self::isProgenyRing($event);
    }

    /** @return array<string, mixed> */
    public static function rules(GameEvent $event): array
    {
        return is_array($event->rules['regulation'] ?? null) ? $event->rules['regulation'] : [];
    }

    /** @return array<string, mixed>|null */
    public static function fromEvent(GameEvent $event, string $locale): ?array
    {
        if (! self::isRegulated($event)) {
            return null;
        }
        $rules = self::rules($event);
        $rank = (string) ($rules['rank'] ?? 'qualifying');
        $sources = self::sources($event, $rules, $locale);

        return [
            ...$rules,
            'rankLabel' => __('events.regulated_ranks.'.$rank, [], $locale),
            'formatLabel' => __('events.formats.'.($rules['format'] ?? $event->discipline), [], $locale),
            'regionLabel' => self::contextLabel('regions', $rules['region'] ?? null, $locale),
            'specialShowLabel' => self::contextLabel('special_shows', $rules['specialShow'] ?? null, $locale),
            'showLegLabel' => self::contextLabel('show_legs', $rules['showLeg'] ?? null, $locale),
            'sportScopeLabel' => self::contextLabel('sport_scopes', $rules['sportScope'] ?? null, $locale),
            'policyNote' => self::contextLabel('certificate_policies', $rules['certificatePolicy'] ?? null, $locale),
            'sources' => $sources,
            'sourceUrl' => $sources[0]['url'] ?? '',
        ];
    }

    /**
     * @param  array<string,mixed>  $rules
     * @return list<array<string,mixed>>
     */
    private static function sources(GameEvent $event, array $rules, string $locale): array
    {
        $sources = [];
        $references = is_array($rules['sources'] ?? null) ? $rules['sources'] : [];
        if (is_string($rules['sourceUrl'] ?? null) && $rules['sourceUrl'] !== '') {
            $references[] = $rules['sourceUrl'];
        }
        if ($references === []) {
            $international = ($rules['system'] ?? 'rkf') === 'fci';
            $references[] = match ($event->discipline) {
                'conformation', 'progeny', 'couple', 'breeders' => $international ? 'fci_shows_2026' : 'rkf_shows_2026',
                'agility' => $international ? 'fci_agility' : 'rkf_agility_2026',
                'nosework' => 'rkf_nosework_2026_09', 'canicross' => 'rkf_racing_2026_06',
                default => '',
            };
        }
        foreach ($references as $reference) {
            $source = is_array($reference) ? $reference : [];
            $key = $source['key'] ?? (is_string($reference) && ! preg_match('/^https?:\/\//', $reference) ? $reference : '');
            $url = $source['url'] ?? (is_string($reference) && preg_match('/^https?:\/\//', $reference) ? $reference : config('event_regulations.sources.'.$key, ''));
            if (! is_string($url) || preg_match('/^https?:\/\//', $url) !== 1) {
                continue;
            }
            if (isset($sources[$url])) {
                continue;
            }
            $sources[$url] = [...$source, 'url' => $url, 'label' => is_string($source['label'] ?? null)
                ? $source['label'] : (self::contextLabel('regulation_sources', $key, $locale) ?? __('events.rule_source', [], $locale))];
        }

        return array_values($sources);
    }

    private static function contextLabel(string $group, mixed $value, string $locale): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $key = 'events.'.$group.'.'.$value;
        $label = __($key, [], $locale);

        return $label === $key ? $value : $label;
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    public static function option(array $option, string $locale, ?Dog $breed = null): array
    {
        $plan = is_array($option['plan'] ?? null) ? $option['plan'] : [];
        $label = __((string) ($option['labelKey'] ?? $option['key']), [], $locale);
        foreach (['program' => 'programs', 'test_type' => 'odors'] as $key => $group) {
            if (isset($plan[$key])) {
                $label .= ' · '.__('events.'.$group.'.'.$plan[$key], [], $locale);
            }
        }

        return [
            ...$option, 'label' => $label,
            'categoryLabel' => isset($option['categoryKey']) ? GameEventDivisionData::label((string) $option['categoryKey'], $locale, $breed) : null,
            'reason' => isset($option['reason']) ? __((string) $option['reason'], [], $locale) : null,
        ];
    }
}
