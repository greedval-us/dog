<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import type { PetCare } from '@/types/pet-care';

defineProps<{ incidents: PetCare['recentIncidents'] }>();
const { t, number, locale } = useI18n();
const localized = (value: Record<string, string>) =>
    value[locale.value] ?? value.en ?? '';
const eventDate = (value: string) =>
    new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <section
        v-if="incidents.length"
        class="pet-care-incidents"
        aria-live="polite"
        :aria-label="t('Recent events')"
    >
        <h3>{{ t('Recent events') }}</h3>
        <article
            v-for="event in incidents"
            :key="event.id"
            class="pet-status pet-status-negative"
        >
            <time :datetime="event.occurredAt">{{
                eventDate(event.occurredAt)
            }}</time>
            <div
                v-for="incident in event.incidents"
                :key="incident.effect.code"
            >
                <strong>{{ localized(incident.effect.name) }}</strong>
                <p>
                    {{
                        t(
                            incident.quality === 0
                                ? 'During {item}.'
                                : 'After using {item} (quality {quality}/10).',
                            {
                                item: localized(incident.item_name),
                                quality: number(incident.quality),
                            },
                        )
                    }}
                </p>
                <p>{{ localized(incident.effect.description) }}</p>
            </div>
        </article>
    </section>
</template>
