<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { useGameEventPresentation } from '@/composables/useGameEventPresentation';
import type { EventAssessment, EventStanding } from '@/types/game-event';

defineProps<{ assessment?: EventAssessment; standings?: EventStanding[] }>();
const { t, number } = useI18n();
const { decimal } = useGameEventPresentation();
</script>

<template>
    <div v-if="assessment || standings?.length" class="event-assessment">
        <p
            v-if="assessment"
            class="event-assessment-grade"
            :class="{ 'is-qualified': assessment.qualified }"
        >
            {{ assessment.label }}
        </p>
        <div v-if="standings?.length" class="event-table-scroll">
            <table class="event-table event-standing-table">
                <caption>
                    {{
                        t('Category standings')
                    }}
                </caption>
                <thead>
                    <tr>
                        <th scope="col">{{ t('Category') }}</th>
                        <th scope="col">{{ t('Place') }}</th>
                        <th scope="col">{{ t('Result') }}</th>
                        <th scope="col">{{ t('Qualification') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="standing in standings" :key="standing.key">
                        <th scope="row">{{ standing.label }}</th>
                        <td>
                            {{
                                standing.rank == null
                                    ? '—'
                                    : number(standing.rank)
                            }}
                        </td>
                        <td>
                            {{
                                t(
                                    '{score} points · {time} s · {count} penalties',
                                    {
                                        score: decimal(standing.score),
                                        time: decimal(standing.time),
                                        count: decimal(standing.penalties),
                                    },
                                )
                            }}
                        </td>
                        <td>
                            {{
                                t(
                                    standing.qualified
                                        ? 'Qualified'
                                        : 'Not qualified',
                                )
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
