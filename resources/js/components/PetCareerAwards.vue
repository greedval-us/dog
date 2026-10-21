<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Award, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { formatAgeMonths } from '@/lib/petAge';
import { show } from '@/routes/game-events';
import type { PetCareerTitle } from '@/types/pet-career';

defineProps<{ label: string; titles: PetCareerTitle[] }>();
const { t, number, locale } = useI18n();
const formatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'Europe/Moscow',
        }),
);
</script>

<template>
    <section class="pet-career-awards" :aria-label="t(label)">
        <h3><Award :size="21" aria-hidden="true" />{{ t(label) }}</h3>
        <ul class="pet-career-title-list">
            <li
                v-for="(title, index) in titles"
                :key="`${title.discipline}:${title.code ?? title.name}:${title.scopeKey ?? ''}:${title.classKey}:${index}`"
            >
                <Trophy :size="22" aria-hidden="true" />
                <div>
                    <h4>
                        {{ title.name
                        }}<span>{{
                            t('Awarded {count} times', {
                                count: number(title.count),
                            })
                        }}</span>
                    </h4>
                    <p>
                        {{ t(`events.discipline.${title.discipline}`)
                        }}<template
                            v-if="!title.kind || title.kind === 'legacy'"
                        >
                            ·
                            {{
                                t(`events.frequency.${title.frequency}`)
                            }}</template
                        >
                    </p>
                    <p v-if="title.sourceCount">
                        {{
                            t('Supporting documents: {count}', {
                                count: number(title.sourceCount),
                            })
                        }}
                    </p>
                    <p v-if="title.ageMonths != null">
                        {{
                            t('Age at award: {age}', {
                                age: formatAgeMonths(title.ageMonths, locale),
                            })
                        }}
                    </p>
                    <p>
                        {{
                            t('Last awarded: {date}', {
                                date: formatter.format(
                                    new Date(title.awardedAt),
                                ),
                            })
                        }}
                    </p>
                </div>
                <Button
                    v-if="title.eventId !== null"
                    as-child
                    size="sm"
                    variant="outline"
                    ><Link :href="show(title.eventId)"
                        >{{ t('View result')
                        }}<ArrowRight :size="15" aria-hidden="true" /></Link
                ></Button>
            </li>
        </ul>
    </section>
</template>
