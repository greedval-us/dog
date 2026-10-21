<script setup lang="ts">
import { Trophy } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import type { EventExterior, EventTitle } from '@/types/game-event';
import { formatAgeMonths } from '@/lib/petAge';
import { eventAwardLabels } from '@/lib/eventAwards';

withDefaults(
    defineProps<{
        exterior?: EventExterior;
        titles?: EventTitle[];
        titleLabel?: string;
        compact?: boolean;
    }>(),
    { compact: false },
);
const { t, number, locale } = useI18n();
</script>

<template>
    <div
        v-if="exterior || titles?.length"
        class="dog-competition-credentials"
        :class="{ 'is-compact': compact }"
    >
        <div
            v-if="exterior"
            class="dog-conformation-chips"
            :aria-label="t('Breed conformation')"
        >
            <span v-for="(value, key) in exterior" :key="key"
                >{{ t(`events.exterior.${key}`) }}
                <strong>{{ number(value) }}</strong></span
            >
        </div>
        <template v-if="titles?.length">
            <span
                v-if="compact"
                class="dog-title-chip"
                :title="titles.map((title) => title.name).join(', ')"
                ><Trophy :size="13" aria-hidden="true" />{{
                    titleLabel
                        ? t(titleLabel)
                        : t('Achievements: {count}', {
                              count: number(titles.length),
                          })
                }}{{ titleLabel ? `: ${number(titles.length)}` : '' }}</span
            >
            <details v-else class="dog-titles-details">
                <summary>
                    <Trophy :size="14" aria-hidden="true" />{{
                        titleLabel ? t(titleLabel) : t('Dog achievements')
                    }}
                    · {{ number(titles.length) }}
                </summary>
                <ul class="event-title-list">
                    <li v-for="(title, titleIndex) in titles" :key="titleIndex">
                        <span
                            >{{ title.name
                            }}<small
                                >{{
                                    t(`events.discipline.${title.discipline}`)
                                }}
                                ·
                                {{ t(eventAwardLabels[title.kind ?? 'legacy'])
                                }}<template
                                    v-if="
                                        !title.kind || title.kind === 'legacy'
                                    "
                                >
                                    ·
                                    {{
                                        t(`events.frequency.${title.frequency}`)
                                    }}</template
                                ><template v-if="title.ageMonths != null">
                                    ·
                                    {{
                                        t('Age at award: {age}', {
                                            age: formatAgeMonths(
                                                title.ageMonths,
                                                locale,
                                            ),
                                        })
                                    }}</template
                                ></small
                            ></span
                        >
                    </li>
                </ul>
            </details>
        </template>
    </div>
</template>
