<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { EventRegulation } from '@/types/game-event';

const props = defineProps<{
    regulation?: EventRegulation | null;
    compact?: boolean;
}>();
const { t, locale } = useI18n();
const country = computed(() => {
    const code = props.regulation?.country;
    if (!code) return '';
    try {
        return (
            new Intl.DisplayNames([locale.value], { type: 'region' }).of(
                code,
            ) ?? code
        );
    } catch {
        return code;
    }
});
const sources = computed(() => {
    const references = props.regulation?.sources?.length
        ? props.regulation.sources
        : props.regulation?.sourceUrl
          ? [{ url: props.regulation.sourceUrl }]
          : [];
    return references.filter((source) => /^https?:\/\//.test(source.url));
});
const specialShow = computed(() =>
    [
        props.regulation?.specialShowLabel,
        props.regulation?.showLegLabel,
        props.regulation?.showYear
            ? t('Game year: {year}', { year: props.regulation.showYear })
            : null,
        props.regulation?.showEdition
            ? t('Game edition: {edition}', {
                  edition: props.regulation.showEdition,
              })
            : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
const location = computed(() =>
    [country.value, props.regulation?.regionLabel ?? props.regulation?.region]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <section
        v-if="regulation"
        class="event-regulation"
        :aria-label="t('Event regulation')"
    >
        <p class="event-regulation-heading">
            <span class="event-badge">{{
                regulation.system.toUpperCase()
            }}</span>
            <strong>{{ regulation.rankLabel }}</strong>
            <span v-if="regulation.formatLabel" class="event-note">{{
                regulation.formatLabel
            }}</span>
        </p>
        <p v-if="specialShow" class="event-note">{{ specialShow }}</p>
        <template v-if="!compact">
            <p v-if="location" class="event-note">{{ location }}</p>
            <p v-if="regulation.sportScopeLabel" class="event-note">
                {{ regulation.sportScopeLabel }}
            </p>
            <p v-if="regulation.judgeName" class="event-note">
                {{ t('Judge: {name}', { name: regulation.judgeName }) }}
            </p>
            <p v-if="regulation.policyNote" class="event-note">
                {{ regulation.policyNote }}
            </p>
            <a
                v-for="source in sources"
                :key="source.url"
                :href="source.url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-link"
                >{{ source.label ?? t('Read the event rules') }}</a
            >
        </template>
    </section>
</template>
