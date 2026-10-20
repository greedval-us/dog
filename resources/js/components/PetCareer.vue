<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Award,
    Medal,
    RotateCcw,
    Trophy,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PetCareerAwards from '@/components/PetCareerAwards.vue';
import PetTitleProgress from '@/components/PetTitleProgress.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { usePetCareer } from '@/composables/usePetCareer';
import { index, show } from '@/routes/game-events';
import type { PetCareer } from '@/types/pet-career';
import { groupEventAwards } from '@/lib/eventAwards';

const props = defineProps<{ petId: number; career?: PetCareer | null }>();
const { t, number, locale } = useI18n();
const { data, loading, failed, load, retry } = usePetCareer(
    () => props.petId,
    () => props.career,
);
const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'Europe/Moscow',
        }),
);
const date = (value: string) => dateFormatter.value.format(new Date(value));
const awardGroups = computed(() => groupEventAwards(data.value?.titles ?? []));
const summaries = computed(() =>
    data.value
        ? [
              {
                  label: 'Competition starts',
                  value: data.value.summary.competitionStarts,
              },
              {
                  label: 'Competition wins',
                  value: data.value.summary.competitionWins,
              },
              {
                  label: 'Exhibition starts',
                  value: data.value.summary.exhibitionStarts,
              },
              {
                  label: 'Exhibition wins',
                  value: data.value.summary.exhibitionWins,
              },
              { label: 'Podium finishes', value: data.value.summary.podiums },
              { label: 'Weekly event cups', value: data.value.summary.cups },
          ]
        : [],
);
</script>

<template>
    <SurfaceCard
        class="pet-career"
        :aria-busy="loading"
        :title="t('Titles')"
        :description="
            t(
                'Earned titles, cups and results from this dog’s completed events.',
            )
        "
    >
        <div v-if="failed" class="pet-career-notice" role="alert">
            <p>{{ t('Could not load data. Please retry.') }}</p>
            <Button :disabled="loading" @click="retry()">{{
                t('Retry')
            }}</Button>
        </div>
        <div v-if="loading && !data" class="dashboard-loading" role="status">
            {{ t('Loading...') }}
        </div>
        <template v-if="data">
            <div class="pet-career-summary">
                <dl>
                    <div v-for="stat in summaries" :key="stat.label">
                        <dt>{{ t(stat.label) }}</dt>
                        <dd>{{ number(stat.value) }}</dd>
                    </div>
                </dl>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="loading"
                    @click="load()"
                    ><RotateCcw :size="15" aria-hidden="true" />{{
                        t('Refresh')
                    }}</Button
                >
            </div>
            <PetTitleProgress
                v-if="data.titleProgress"
                :progress="data.titleProgress"
            />
            <PetCareerAwards
                v-for="group in awardGroups"
                :key="group.kind"
                :label="group.label"
                :titles="group.entries"
            />
            <div
                v-if="!data.titles.length && data.results.entries.length"
                class="pet-career-notice"
            >
                <Award :size="21" aria-hidden="true" />
                <p>
                    {{
                        t(
                            'No awards yet. Certificates and titles require the result and qualifications specified by the event regulation.',
                        )
                    }}
                </p>
            </div>
            <section
                v-if="data.results.entries.length"
                class="pet-career-results"
                :aria-label="t('Event results')"
            >
                <h3>
                    <Medal :size="21" aria-hidden="true" />{{
                        t('Event results')
                    }}
                </h3>
                <p class="pet-career-hint">
                    {{
                        t(
                            'Places follow each event’s class and category rules. Assessments, certificates and titles are separate achievements; a first-place finish alone does not guarantee a title.',
                        )
                    }}
                </p>
                <ol class="pet-career-result-list">
                    <li
                        v-for="result in data.results.entries"
                        :key="result.id"
                        :class="{
                            'is-winner':
                                !result.eliminated && result.rank === 1,
                        }"
                    >
                        <span class="pet-career-place"
                            ><Trophy
                                v-if="!result.eliminated && result.rank === 1"
                                :size="19"
                                aria-hidden="true"
                            />{{
                                result.eliminated
                                    ? t('Eliminated')
                                    : result.rank == null
                                      ? t('Not placed')
                                      : t('Place {place}', {
                                            place: number(result.rank),
                                        })
                            }}</span
                        >
                        <div class="pet-career-result-copy">
                            <h4>
                                {{
                                    t(`events.discipline.${result.discipline}`)
                                }}
                            </h4>
                            <p>
                                {{ result.petName }} ·
                                {{ result.divisionLabel }}
                            </p>
                            <p>
                                {{ t(`events.frequency.${result.frequency}`) }}
                                ·
                                <time :datetime="result.completedAt">{{
                                    date(result.completedAt)
                                }}</time>
                            </p>
                        </div>
                        <span class="pet-career-prize">{{
                            t('{amount} coins', {
                                amount: number(result.prize),
                            })
                        }}</span>
                        <Button as-child variant="secondary" size="sm"
                            ><Link :href="show(result.eventId)"
                                >{{ t('View result')
                                }}<ArrowRight
                                    :size="15"
                                    aria-hidden="true" /></Link
                        ></Button>
                    </li>
                </ol>
                <nav
                    v-if="
                        data.results.previousCursor || data.results.nextCursor
                    "
                    class="shop-pagination"
                    :aria-label="t('Event result pages')"
                >
                    <Button
                        v-if="data.results.previousCursor"
                        variant="outline"
                        :disabled="loading"
                        @click="load(data.results.previousCursor)"
                        ><ArrowLeft :size="17" aria-hidden="true" />{{
                            t('Newer entries')
                        }}</Button
                    >
                    <Button
                        v-if="data.results.nextCursor"
                        variant="outline"
                        :disabled="loading"
                        @click="load(data.results.nextCursor)"
                        >{{ t('Older entries')
                        }}<ArrowRight :size="17" aria-hidden="true"
                    /></Button>
                </nav>
            </section>
            <EmptyState
                v-else-if="!data.titles.length"
                :kicker="null"
                :title="t('Your dog’s career starts here')"
                :description="
                    t(
                        'Enter a competition or exhibition. Completed results and earned titles will appear here.',
                    )
                "
            >
                <template #icon><Trophy aria-hidden="true" /></template>
                <Button as-child
                    ><Link :href="index()"
                        >{{ t('Find an event')
                        }}<ArrowRight :size="17" aria-hidden="true" /></Link
                ></Button>
            </EmptyState>
        </template>
    </SurfaceCard>
</template>
