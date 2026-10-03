<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Clock3,
    History,
    MessageCircle,
    RotateCcw,
    Stethoscope,
} from '@lucide/vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { computed, onMounted, onUnmounted, ref, useId, watch } from 'vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { stateLabels, statLabels } from '@/lib/petLabels';
import { index } from '@/routes/pets/history';
import { store } from '@/routes/pets/thoughts';
import type { PlayerPet } from '@/types/pet';
import type {
    PetHistoryChange,
    PetHistoryEntry,
    PetHistoryKind,
    PetHistoryPage,
    PetHistoryPeriod,
    PetHistoryUnit,
} from '@/types/pet-history';

const props = defineProps<{ pet: PlayerPet }>();
const { t, number, locale } = useI18n();
const id = useId();
const kind = ref<PetHistoryKind>('action');
const period = ref<PetHistoryPeriod>(7);
const event = ref('');
const entries = ref<PetHistoryEntry[]>([]);
const events = ref<PetHistoryPage['events']>([]);
const nextCursor = ref<string | null>(null);
const previousCursor = ref<string | null>(null);
const currentCursor = ref<string | null>(null);
const initializing = ref(true);
const loading = ref(false);
const failed = ref(false);
const thoughtsFailed = ref(false);
const request = useHttp<Record<string, never>, PetHistoryPage>({});
const thoughts = useHttp<Record<string, never>, void>({});
const busy = computed(
    () => initializing.value || loading.value || thoughts.processing,
);
const metricLabels: Record<string, string> = {
    ...stateLabels,
    ...statLabels,
    coins: 'Coins',
    gems: 'Gems',
    level: 'Skill level',
    skill_level: 'Skill level',
};
let alive = false;
let loadGeneration = 0;
let refreshGeneration = 0;

async function loadHistory(cursor: string | null = null): Promise<void> {
    const generation = ++loadGeneration;
    request.cancel();
    currentCursor.value = cursor;
    loading.value = true;
    failed.value = false;
    try {
        const response = await request.get(
            index.url(props.pet.id, {
                query: {
                    kind: kind.value,
                    period: period.value,
                    event:
                        kind.value === 'action'
                            ? event.value || undefined
                            : undefined,
                    cursor: cursor ?? undefined,
                },
            }),
        );
        if (!alive || generation !== loadGeneration) return;
        entries.value = response.data;
        events.value = response.events;
        nextCursor.value = response.nextCursor;
        previousCursor.value = response.previousCursor;
    } catch {
        if (alive && generation === loadGeneration) failed.value = true;
    } finally {
        if (generation === loadGeneration) loading.value = false;
    }
}

async function refresh(cursor: string | null = null): Promise<void> {
    const generation = ++refreshGeneration;
    ++loadGeneration;
    request.cancel();
    loading.value = false;
    thoughts.cancel();
    thoughtsFailed.value = false;
    try {
        await thoughts.post(store.url(props.pet.id));
    } catch {
        if (alive && generation === refreshGeneration)
            thoughtsFailed.value = true;
    }
    if (alive && generation === refreshGeneration) await loadHistory(cursor);
}

function metricLabel(change: PetHistoryChange): string {
    return change.label ?? t(metricLabels[change.metric] ?? 'Change');
}

function metricValue(value: number, unit: PetHistoryUnit): string {
    const amount = new Intl.NumberFormat(locale.value, {
        maximumFractionDigits: 2,
    }).format(value);
    if (unit === 'percent') return `${amount}%`;
    if (unit === 'coins') return t('{amount} coins', { amount });
    if (unit === 'gems') return t('{amount} gems', { amount });
    return t('{amount} points', { amount });
}

function delta(change: PetHistoryChange): string {
    return `${change.delta > 0 ? '+' : ''}${metricValue(change.delta, change.unit)}`;
}

function signedAmount(amount: number): string {
    return `${amount > 0 ? '+' : ''}${number(amount)}`;
}

function date(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function duration(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;
    return [
        minutes > 0 ? t('{minutes} min', { minutes: number(minutes) }) : '',
        remainder > 0 || minutes === 0
            ? t('{seconds} sec', { seconds: number(remainder) })
            : '',
    ]
        .filter(Boolean)
        .join(' ');
}

watch([kind, period, event, locale], () => {
    if (!alive || initializing.value) return;
    ++refreshGeneration;
    thoughts.cancel();
    void loadHistory();
});
watch(
    () => props.pet,
    () => {
        if (alive && !initializing.value) void refresh(currentCursor.value);
    },
);
onMounted(async () => {
    alive = true;
    await refresh();
    if (alive) initializing.value = false;
});
onUnmounted(() => {
    alive = false;
    ++loadGeneration;
    ++refreshGeneration;
    request.cancel();
    thoughts.cancel();
});
defineExpose({ refresh });
</script>

<template>
    <SurfaceCard
        :title="t('History')"
        :description="
            t('Your shared days: care, progress and your dog’s thoughts.')
        "
        class="pet-history"
    >
        <TabsRoot v-model="kind" class="pet-history-content">
            <TabsList
                class="pet-tabs pet-history-tabs"
                :aria-label="t('History sections')"
            >
                <TabsTrigger value="action" class="pet-tab" :disabled="busy">
                    <History :size="17" aria-hidden="true" />{{ t('Actions') }}
                </TabsTrigger>
                <TabsTrigger value="thought" class="pet-tab" :disabled="busy">
                    <MessageCircle :size="17" aria-hidden="true" />{{
                        t('Dog’s thoughts')
                    }}
                </TabsTrigger>
            </TabsList>
            <div class="pet-history-toolbar">
                <div class="pet-history-field">
                    <label :for="id + '-period'">{{ t('Period') }}</label>
                    <select
                        :id="id + '-period'"
                        v-model="period"
                        class="pet-history-select"
                        :disabled="busy"
                    >
                        <option :value="7">{{ t('Last 7 days') }}</option>
                        <option :value="30">{{ t('Last 30 days') }}</option>
                    </select>
                </div>
                <div v-if="kind === 'action'" class="pet-history-field">
                    <label :for="id + '-event'">{{ t('Action type') }}</label>
                    <select
                        :id="id + '-event'"
                        v-model="event"
                        class="pet-history-select"
                        :disabled="busy"
                    >
                        <option value="">{{ t('All actions') }}</option>
                        <option
                            v-for="option in events"
                            :key="option.code"
                            :value="option.code"
                        >
                            {{ option.name }}
                        </option>
                    </select>
                </div>
                <Button
                    variant="outline"
                    :disabled="busy"
                    :aria-busy="busy"
                    @click="refresh()"
                >
                    <RotateCcw :size="16" aria-hidden="true" />{{
                        t('Refresh history')
                    }}
                </Button>
            </div>
            <p class="pet-history-retention">
                {{ t('History is available for the last 30 days.') }}
            </p>
            <TabsContent
                :key="kind"
                :value="kind"
                class="pet-history-panel"
                :aria-busy="busy"
            >
                <p
                    v-if="thoughtsFailed && kind === 'thought'"
                    class="pet-history-error"
                    role="alert"
                >
                    {{
                        t(
                            'Could not update your dog’s thoughts. Try refreshing the history.',
                        )
                    }}
                </p>
                <div v-if="busy" class="pet-history-loading" role="status">
                    <span class="sr-only">{{ t('Loading history...') }}</span>
                    <span
                        v-for="index in 3"
                        :key="index"
                        class="pet-history-skeleton"
                        aria-hidden="true"
                    ></span>
                </div>
                <div v-else-if="failed" class="pet-history-empty" role="alert">
                    <p>{{ t('Could not load history. Please retry.') }}</p>
                    <Button
                        variant="outline"
                        @click="loadHistory(currentCursor)"
                        >{{ t('Retry') }}</Button
                    >
                </div>
                <div v-else-if="!entries.length" class="pet-history-empty">
                    <component
                        :is="kind === 'thought' ? MessageCircle : History"
                        :size="26"
                        aria-hidden="true"
                    />
                    <h3>
                        {{
                            kind === 'thought'
                                ? t('No thoughts during this period')
                                : t('No actions during this period')
                        }}
                    </h3>
                    <p>
                        {{
                            kind === 'thought'
                                ? t(
                                      'Your dog’s thoughts will appear here as you spend time together.',
                                  )
                                : t(
                                      'Feed, walk or train your dog to begin your shared history.',
                                  )
                        }}
                    </p>
                </div>
                <ol v-else class="pet-history-list">
                    <li
                        v-for="entry in entries"
                        :key="entry.id"
                        class="pet-history-entry"
                        :class="{ 'is-thought': entry.kind === 'thought' }"
                    >
                        <span class="pet-history-icon"
                            ><component
                                :is="
                                    entry.kind === 'thought'
                                        ? MessageCircle
                                        : History
                                "
                                :size="18"
                                aria-hidden="true"
                        /></span>
                        <div class="pet-history-copy">
                            <div class="pet-history-heading">
                                <h3>{{ entry.title }}</h3>
                                <time :datetime="entry.occurredAt">{{
                                    date(entry.occurredAt)
                                }}</time>
                            </div>
                            <p v-if="entry.message" class="pet-history-message">
                                {{ entry.message }}
                            </p>
                            <div
                                v-if="
                                    entry.details.stage ||
                                    entry.details.name ||
                                    entry.details.diseaseName ||
                                    entry.details.durationSeconds !== undefined
                                "
                                class="pet-history-meta"
                            >
                                <span
                                    v-if="entry.details.stage"
                                    class="pet-history-stage"
                                    :class="{
                                        'is-completed':
                                            entry.details.stage === 'completed',
                                    }"
                                    >{{
                                        entry.details.stage === 'completed'
                                            ? t('Completed')
                                            : t('Started')
                                    }}</span
                                >
                                <span
                                    v-if="
                                        entry.details.name &&
                                        entry.details.name !== entry.title
                                    "
                                    >{{ entry.details.name }}</span
                                >
                                <span v-if="entry.details.diseaseName"
                                    ><Stethoscope
                                        :size="14"
                                        aria-hidden="true"
                                    />{{ entry.details.diseaseName }}</span
                                >
                                <span
                                    v-if="
                                        entry.details.durationSeconds !==
                                        undefined
                                    "
                                    ><Clock3 :size="14" aria-hidden="true" />{{
                                        t('Duration: {time}', {
                                            time: duration(
                                                entry.details.durationSeconds,
                                            ),
                                        })
                                    }}</span
                                >
                            </div>
                            <dl
                                v-if="entry.details.coins || entry.details.gems"
                                class="pet-history-changes"
                            >
                                <div v-if="entry.details.coins">
                                    <dt>{{ t('Coins') }}</dt>
                                    <dd>
                                        <strong
                                            :class="{
                                                'is-gain':
                                                    entry.details.coins > 0,
                                                'is-loss':
                                                    entry.details.coins < 0,
                                            }"
                                            >{{
                                                signedAmount(
                                                    entry.details.coins,
                                                )
                                            }}</strong
                                        >
                                    </dd>
                                </div>
                                <div v-if="entry.details.gems">
                                    <dt>{{ t('Gems') }}</dt>
                                    <dd>
                                        <strong
                                            :class="{
                                                'is-gain':
                                                    entry.details.gems > 0,
                                                'is-loss':
                                                    entry.details.gems < 0,
                                            }"
                                            >{{
                                                signedAmount(entry.details.gems)
                                            }}</strong
                                        >
                                    </dd>
                                </div>
                            </dl>
                            <dl
                                v-if="entry.details.changes?.length"
                                class="pet-history-changes"
                            >
                                <div
                                    v-for="(change, index) in entry.details
                                        .changes"
                                    :key="index"
                                >
                                    <dt>{{ metricLabel(change) }}</dt>
                                    <dd>
                                        <strong
                                            :class="{
                                                'is-gain': change.delta > 0,
                                                'is-loss': change.delta < 0,
                                            }"
                                            >{{ delta(change) }}</strong
                                        >
                                        <span
                                            >{{
                                                metricValue(
                                                    change.before,
                                                    change.unit,
                                                )
                                            }}
                                            →
                                            {{
                                                metricValue(
                                                    change.after,
                                                    change.unit,
                                                )
                                            }}</span
                                        >
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </li>
                </ol>
                <nav
                    v-if="!failed && (previousCursor || nextCursor)"
                    class="shop-pagination pet-history-pagination"
                    :aria-label="t('History pages')"
                >
                    <Button
                        v-if="previousCursor"
                        variant="outline"
                        :disabled="busy"
                        @click="loadHistory(previousCursor)"
                        ><ArrowLeft :size="17" aria-hidden="true" />{{
                            t('Newer entries')
                        }}</Button
                    >
                    <Button
                        v-if="nextCursor"
                        variant="outline"
                        :disabled="busy"
                        @click="loadHistory(nextCursor)"
                        >{{ t('Older entries')
                        }}<ArrowRight :size="17" aria-hidden="true"
                    /></Button>
                </nav>
            </TabsContent>
        </TabsRoot>
    </SurfaceCard>
</template>
