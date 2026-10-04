<script setup lang="ts">
import { Head, Link, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Clock,
    Coins,
    PawPrint,
    Trophy,
    Users,
} from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import FormField from '@/components/FormField.vue';
import DogCompetitionCredentials from '@/components/DogCompetitionCredentials.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { index, register, update, cancel } from '@/routes/game-events';
import { index as shop } from '@/routes/shop';
import { show as dogProfile } from '@/routes/pets';
import type {
    EventDecision,
    EventDog,
    EventEquipment,
    GameEventDetail,
    GameEventEntry,
} from '@/types/game-event';

const props = defineProps<{
    event: GameEventDetail;
    dogs: EventDog[];
    equipment: EventEquipment[];
    entry: GameEventEntry | null;
}>();
const { t, number, locale } = useI18n();
const page = usePage();
const ownEntry = computed(() => props.entry ?? props.event.ownEntry ?? null);
const form = useForm({
    pet_id:
        ownEntry.value?.petId ??
        props.dogs.find(
            (dog) =>
                props.event.discipline === 'progeny' ||
                (dog.isActive && !dog.isBusy),
        )?.id ??
        null,
    fee: props.event.fee,
    plan: {
        stages: [
            ...(props.event.discipline === 'progeny'
                ? ['balanced', 'balanced', 'balanced']
                : (ownEntry.value?.plan.stages ?? [
                      'balanced',
                      'balanced',
                      'balanced',
                  ])),
        ] as EventDecision[],
        offspring_ids: [...(ownEntry.value?.plan.offspring_ids ?? [])],
    },
    gear_ids:
        props.event.discipline === 'progeny'
            ? []
            : [...(ownEntry.value?.gearIds ?? [])],
    token: '',
});
const cancelForm = useForm({ token: '' });
const errorPanel = ref<HTMLElement | null>(null);
const now = ref(Date.now());
useIntervalFn(() => {
    now.value = Date.now();
}, 10_000);
const pending = computed(() => form.processing || cancelForm.processing);
const poll = usePoll(
    30_000,
    { only: ['event', 'entry', 'dogs', 'equipment'] },
    { mode: 'rest' },
);
watch(pending, (value) => {
    if (value) poll.stop();
    else poll.start();
});
const selectedDog = computed(() =>
    props.dogs.find((dog) => dog.id === form.pet_id),
);
const registrationOpen = computed(
    () =>
        props.event.status === 'scheduled' &&
        now.value >= Date.parse(props.event.opensAt) &&
        now.value < Date.parse(props.event.closesAt),
);
const withdrawn = computed(() =>
    ['cancelled', 'withdrawn'].includes(ownEntry.value?.status ?? ''),
);
const editable = computed(
    () =>
        !withdrawn.value &&
        registrationOpen.value &&
        (props.event.canRegister || Boolean(ownEntry.value)),
);
const errors = computed(() => [
    ...Object.values(form.errors),
    ...Object.values(cancelForm.errors),
]);
const insufficientFunds = computed(
    () =>
        !ownEntry.value && Number(page.props.auth.user.coins) < props.event.fee,
);
const isShow = computed(() =>
    ['conformation', 'progeny'].includes(props.event.discipline),
);
const validEquipment = computed(() =>
    props.equipment.filter(
        (item) =>
            item.remainingUses > 0 &&
            item.disciplines.includes(props.event.discipline) &&
            (props.event.discipline !== 'agility' ||
                item.phase === 'preparation') &&
            (!item.sizes.length ||
                item.sizes.includes(selectedDog.value?.size ?? '')),
    ),
);
const entriesByDivision = computed(() => {
    const divisions = new Map<string, GameEventEntry[]>();
    for (const entry of props.event.entries)
        divisions.set(entry.division, [
            ...(divisions.get(entry.division) ?? []),
            entry,
        ]);
    return [...divisions.entries()].map(
        ([division, entries]) =>
            [
                division,
                entries.sort(
                    (a, b) => (a.rank ?? Infinity) - (b.rank ?? Infinity),
                ),
            ] as const,
    );
});
const missingKit = computed(
    () =>
        props.event.discipline === 'canicross' &&
        ['body', 'line', 'handler'].some(
            (slot) =>
                !validEquipment.value.some(
                    (gear) =>
                        gear.slot === slot && form.gear_ids.includes(gear.id),
                ),
        ),
);
const missingOffspring = computed(
    () =>
        props.event.discipline === 'progeny' &&
        form.plan.offspring_ids.length < 3,
);
const gearDescription = (gear: EventEquipment) =>
    typeof gear.description === 'string'
        ? gear.description
        : (gear.description?.[locale.value] ?? gear.description?.en);
const divisionLabel = (division: string, entries: GameEventEntry[]) => {
    if (entries[0]?.divisionLabel) return entries[0].divisionLabel;
    const [tier, group] = division.split(':');
    return [
        t(`events.division.${tier}`),
        group && !group.startsWith('breed-')
            ? t(`events.division.${group}`)
            : null,
    ]
        .filter(Boolean)
        .join(' · ');
};
const replayId = ref<number | null>(
    ownEntry.value?.result ? ownEntry.value.id : null,
);
const replay = computed(
    () =>
        props.event.entries.find((entry) => entry.id === replayId.value) ??
        (ownEntry.value?.id === replayId.value ? ownEntry.value : null),
);
const replayWithdrawn = computed(() =>
    ['cancelled', 'withdrawn'].includes(replay.value?.status ?? ''),
);
const date = (value: string) =>
    new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Europe/Moscow',
    }).format(new Date(value));
const decimal = (value: number) =>
    new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(
        value,
    );
const stageLabel = (key: string) => t(`events.stage.${key}`);
const optionLabel = (key: string, choice: string) =>
    t(`events.option.${props.event.discipline}.${key}.${choice}`);
const phaseLabel = (phase: string) =>
    t(phase === 'preparation' ? 'Preparation only' : 'During the performance');

watch(
    () => ownEntry.value?.id,
    () => {
        const entry = ownEntry.value;
        if (!entry) return;
        form.pet_id = entry.petId;
        form.plan.stages =
            props.event.discipline === 'progeny'
                ? ['balanced', 'balanced', 'balanced']
                : [...entry.plan.stages];
        form.plan.offspring_ids = [...(entry.plan.offspring_ids ?? [])];
        form.gear_ids =
            props.event.discipline === 'progeny' ? [] : [...entry.gearIds];
    },
);
watch(
    () => ownEntry.value?.result,
    (result) => {
        if (result && ownEntry.value) replayId.value = ownEntry.value.id;
    },
);
watch(
    () => form.pet_id,
    () => {
        if (!ownEntry.value) {
            form.gear_ids = [];
            form.plan.offspring_ids = [];
        }
    },
);

function toggleGear(item: EventEquipment) {
    if (!editable.value || pending.value) return;
    if (form.gear_ids.includes(item.id)) {
        form.gear_ids = form.gear_ids.filter((id) => id !== item.id);
        return;
    }
    const sameSlot = new Set(
        props.equipment
            .filter((gear) => gear.slot === item.slot)
            .map((gear) => gear.id),
    );
    form.gear_ids = [
        ...form.gear_ids.filter((id) => !sameSlot.has(id)),
        item.id,
    ];
}
function operationToken(): string {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (value) =>
        value.toString(16).padStart(2, '0'),
    ).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
function submit() {
    if (
        !editable.value ||
        pending.value ||
        !form.pet_id ||
        insufficientFunds.value ||
        missingKit.value ||
        missingOffspring.value
    )
        return;
    if (!form.token) form.token = operationToken();
    form.fee = props.event.fee;
    cancelForm.clearErrors();
    form.submit(
        ownEntry.value ? update(props.event.id) : register(props.event.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                form.token = '';
            },
            onError: () => nextTick(() => errorPanel.value?.focus()),
        },
    );
}
function withdraw() {
    if (!editable.value || pending.value || !ownEntry.value) return;
    if (!cancelForm.token) cancelForm.token = operationToken();
    form.clearErrors();
    cancelForm.submit(cancel(props.event.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelForm.token = '';
        },
        onError: () => nextTick(() => errorPanel.value?.focus()),
    });
}
</script>

<template>
    <div class="events-page">
        <Head :title="t(`events.discipline.${event.discipline}`)" />
        <div class="events-page-heading">
            <Heading
                :title="t(`events.discipline.${event.discipline}`)"
                :description="t(`events.frequency.${event.frequency}`)"
            />
            <Button as-child variant="secondary"
                ><Link :href="index()"
                    ><ArrowLeft :size="17" aria-hidden="true" />{{
                        t('Event calendar')
                    }}</Link
                ></Button
            >
        </div>
        <SurfaceCard class="event-overview">
            <div class="event-card-heading">
                <span class="event-badge">{{
                    t(`events.status.${event.status}`)
                }}</span
                ><span>{{
                    t('All event times are shown in Moscow time.')
                }}</span>
            </div>
            <dl class="event-overview-facts">
                <div>
                    <dt>{{ t('Registration opens') }}</dt>
                    <dd>
                        <time :datetime="event.opensAt">{{
                            date(event.opensAt)
                        }}</time>
                    </dd>
                </div>
                <div>
                    <dt>
                        <Clock :size="17" aria-hidden="true" />{{ t('Start') }}
                    </dt>
                    <dd>
                        <time :datetime="event.startsAt">{{
                            date(event.startsAt)
                        }}</time>
                    </dd>
                </div>
                <div>
                    <dt>{{ t('Registration closes') }}</dt>
                    <dd>
                        <time :datetime="event.closesAt">{{
                            date(event.closesAt)
                        }}</time>
                    </dd>
                </div>
                <div>
                    <dt>
                        <Coins :size="17" aria-hidden="true" />{{
                            t('Entry fee')
                        }}
                    </dt>
                    <dd>
                        {{ t('{amount} coins', { amount: number(event.fee) }) }}
                    </dd>
                </div>
                <div>
                    <dt>
                        <Users :size="17" aria-hidden="true" />{{
                            t('Entries')
                        }}
                    </dt>
                    <dd>{{ number(event.entryCount) }}</dd>
                </div>
            </dl>
            <div class="event-prizes">
                <span v-for="(prize, position) in event.prizes" :key="position"
                    ><Trophy :size="16" aria-hidden="true" />{{
                        t('Place {rank}: {amount} coins', {
                            rank: number(position + 1),
                            amount: number(prize),
                        })
                    }}</span
                >
            </div>
            <p class="event-note">
                {{
                    t(
                        event.discipline === 'progeny'
                            ? 'Choose the offspring group before registration closes. It will be evaluated at the scheduled time, even when you are offline.'
                            : 'Register before the deadline and save your plan. Your dog performs at the scheduled time, even when you are offline.',
                    )
                }}
            </p>
        </SurfaceCard>

        <div class="event-layout">
            <SurfaceCard
                :title="t(ownEntry ? 'Your entry' : 'Prepare your entry')"
                class="event-preparation"
            >
                <p
                    v-if="
                        ownEntry && event.status === 'completed' && !withdrawn
                    "
                    class="event-success"
                    role="status"
                >
                    <Check :size="18" aria-hidden="true" />{{
                        t(
                            event.discipline === 'progeny'
                                ? 'Evaluation completed. See the scores for the selected offspring below.'
                                : 'Performance completed. Open the replay below to see every stage.',
                        )
                    }}
                </p>
                <p v-else-if="!editable" class="event-note" role="status">
                    {{
                        t(
                            withdrawn
                                ? 'Your entry was withdrawn and the fee refunded. You cannot enter this event again.'
                                : event.status === 'cancelled'
                                  ? 'This event has been cancelled.'
                                  : now < Date.parse(event.opensAt)
                                    ? 'Registration has not opened yet.'
                                    : 'Registration is closed. Saved plans are ready for the start.',
                        )
                    }}
                </p>
                <p
                    v-if="withdrawn && ownEntry?.result?.reason"
                    class="event-note"
                >
                    {{ ownEntry.result.reason }}
                </p>
                <form class="event-entry-form" @submit.prevent="submit">
                    <FormField
                        id="event-dog"
                        :label="t('Dog')"
                        :error="form.errors.pet_id"
                        v-slot="{ field }"
                    >
                        <select
                            v-bind="field"
                            v-model="form.pet_id"
                            class="events-select"
                            :disabled="
                                !editable || pending || Boolean(ownEntry)
                            "
                        >
                            <option :value="null" disabled>
                                {{ t('Choose a dog') }}
                            </option>
                            <option
                                v-for="dog in dogs"
                                :key="dog.id"
                                :value="dog.id"
                                :disabled="
                                    event.discipline !== 'progeny' &&
                                    (dog.isBusy || !dog.isActive) &&
                                    dog.id !== ownEntry?.petId
                                "
                            >
                                {{ dog.name }} · {{ dog.breed
                                }}{{
                                    dog.isBusy
                                        ? ` · ${t('Busy')}`
                                        : !dog.isActive
                                          ? ` · ${t('Archived dog')}`
                                          : ''
                                }}
                            </option>
                        </select>
                    </FormField>
                    <p v-if="!dogs.length" class="event-note">
                        {{ t('You need a dog to enter this event.') }}
                    </p>
                    <div
                        v-if="
                            selectedDog && event.discipline === 'conformation'
                        "
                        class="event-exterior"
                    >
                        <h3>{{ t('Breed conformation') }}</h3>
                        <dl class="event-exterior-values">
                            <div
                                v-for="(value, key) in selectedDog.exterior"
                                :key="key"
                            >
                                <dt>{{ t(`events.exterior.${key}`) }}</dt>
                                <dd>{{ number(value) }} / 100</dd>
                            </div>
                        </dl>
                        <p>
                            {{
                                t(
                                    'Handling helps show your dog’s strengths. It does not change inherited conformation.',
                                )
                            }}
                        </p>
                    </div>
                    <fieldset
                        v-if="event.discipline === 'progeny' && selectedDog"
                        class="event-offspring"
                        :disabled="!editable || pending"
                    >
                        <legend>{{ t('Choose 3–5 direct offspring') }}</legend>
                        <p class="event-note">
                            {{
                                t(
                                    'Titles of offspring matter in the progeny competition, alongside breed type and consistency.',
                                )
                            }}
                        </p>
                        <label
                            v-for="offspring in selectedDog.offspring"
                            :key="offspring.id"
                            class="event-offspring-choice"
                            ><input
                                v-model="form.plan.offspring_ids"
                                type="checkbox"
                                :value="offspring.id"
                                :disabled="
                                    !form.plan.offspring_ids.includes(
                                        offspring.id,
                                    ) && form.plan.offspring_ids.length >= 5
                                " /><span
                                >{{ offspring.name
                                }}<small>{{
                                    t('Titles: {count}', {
                                        count: number(offspring.titlesCount),
                                    })
                                }}</small
                                ><DogCompetitionCredentials
                                    :exterior="offspring.exterior" /></span
                        ></label>
                        <p
                            v-if="selectedDog.offspring.length < 3"
                            class="event-note"
                        >
                            {{
                                t(
                                    'This dog needs at least three direct offspring for this class.',
                                )
                            }}
                        </p>
                    </fieldset>

                    <template v-if="event.discipline !== 'progeny'">
                        <div class="event-plan-heading">
                            <h3>{{ t('Your performance plan') }}</h3>
                            <p class="event-note">
                                {{
                                    t(
                                        'Each stage affects the next. A bold start can cost focus or stamina later.',
                                    )
                                }}
                            </p>
                        </div>
                        <fieldset
                            v-for="(stage, stageIndex) in event.stages"
                            :key="stage.key"
                            class="event-stage"
                            :disabled="!editable || pending"
                        >
                            <legend>
                                <span>{{ number(stageIndex + 1) }}</span
                                >{{ stageLabel(stage.key) }}
                            </legend>
                            <div class="event-tactics">
                                <label
                                    v-for="choice in [
                                        'careful',
                                        'balanced',
                                        'bold',
                                    ]"
                                    :key="choice"
                                    class="event-tactic"
                                    :class="{
                                        'is-selected':
                                            form.plan.stages[stageIndex] ===
                                            choice,
                                    }"
                                >
                                    <input
                                        v-model="form.plan.stages[stageIndex]"
                                        type="radio"
                                        :name="`event-stage-${stageIndex}`"
                                        :value="choice"
                                    />
                                    <strong>{{
                                        optionLabel(stage.key, choice)
                                    }}</strong>
                                    <span>{{
                                        t(
                                            `events.tactic.${event.discipline}.${choice}`,
                                        )
                                    }}</span>
                                </label>
                            </div>
                        </fieldset>
                    </template>
                    <section v-else class="event-exterior">
                        <h3>{{ t('Judging criteria') }}</h3>
                        <p class="event-note">
                            {{
                                t(
                                    'This is a documentary evaluation of the offspring group you select. Breed type, consistency and their earned titles determine the result; the parent does not run a course.',
                                )
                            }}
                        </p>
                        <ol class="event-title-list">
                            <li v-for="stage in event.stages" :key="stage.key">
                                <span
                                    ><strong>{{ stageLabel(stage.key) }}</strong
                                    ><small>{{
                                        t(
                                            `events.progenyCriterion.${stage.key}`,
                                        )
                                    }}</small></span
                                >
                            </li>
                        </ol>
                    </section>

                    <template v-if="event.discipline !== 'progeny'">
                        <div class="event-gear-heading">
                            <h3>{{ t('Competition equipment') }}</h3>
                            <Button as-child variant="outline" size="sm"
                                ><Link :href="shop()">{{
                                    t('Visit the shop')
                                }}</Link></Button
                            >
                        </div>
                        <p class="event-note">
                            {{
                                t(
                                    'Choose one item per slot. The best fit depends on your dog and your plan.',
                                )
                            }}
                        </p>
                        <p class="event-note">
                            {{ t(`events.equipmentRule.${event.discipline}`) }}
                        </p>
                        <div
                            v-if="validEquipment.length && selectedDog"
                            class="event-gear-list"
                        >
                            <button
                                v-for="gear in validEquipment"
                                :key="gear.id"
                                type="button"
                                class="event-gear"
                                :class="{
                                    'is-selected': form.gear_ids.includes(
                                        gear.id,
                                    ),
                                }"
                                :aria-pressed="form.gear_ids.includes(gear.id)"
                                :disabled="!editable || pending"
                                @click="toggleGear(gear)"
                            >
                                <span class="event-gear-title"
                                    ><strong>{{ gear.name }}</strong
                                    ><Check
                                        v-if="form.gear_ids.includes(gear.id)"
                                        :size="17"
                                        aria-hidden="true"
                                /></span>
                                <span class="event-gear-meta"
                                    >{{ t(`events.slot.${gear.slot}`) }} ·
                                    {{ phaseLabel(gear.phase) }} ·
                                    {{
                                        t('Uses: {count}', {
                                            count: number(gear.remainingUses),
                                        })
                                    }}</span
                                >
                                <span
                                    v-if="gearDescription(gear)"
                                    class="event-note"
                                    >{{ gearDescription(gear) }}</span
                                >
                                <span class="event-gear-modifiers"
                                    ><span
                                        v-for="(value, key) in gear.modifiers"
                                        :key="key"
                                        >{{ t(`events.modifier.${key}`) }}
                                        {{ value > 0 ? '+' : ''
                                        }}{{ number(value * 100) }}%</span
                                    ></span
                                >
                            </button>
                        </div>
                        <p v-else class="event-note">
                            {{
                                t(
                                    'No compatible equipment in your inventory. Check the ammunition category in the shop.',
                                )
                            }}
                        </p>
                    </template>
                    <p v-if="missingKit" class="event-note">
                        {{
                            t(
                                'Canicross requires a pulling harness, tugline and handler belt. Select all three before entering.',
                            )
                        }}
                    </p>
                    <p v-if="missingOffspring" class="event-note">
                        {{
                            t(
                                'Select at least three direct offspring before entering.',
                            )
                        }}
                    </p>
                    <p v-if="insufficientFunds" class="event-note">
                        {{
                            t('You need {amount} more coins.', {
                                amount: number(
                                    event.fee -
                                        Number(page.props.auth.user.coins),
                                ),
                            })
                        }}
                    </p>
                    <div
                        v-if="errors.length"
                        ref="errorPanel"
                        tabindex="-1"
                        class="event-errors"
                        role="alert"
                    >
                        <InputError
                            v-for="error in errors"
                            :key="error"
                            :message="error"
                        />
                    </div>
                    <p
                        v-if="form.recentlySuccessful"
                        class="event-success"
                        role="status"
                    >
                        {{
                            t(
                                event.discipline === 'progeny'
                                    ? 'Your offspring group has been saved.'
                                    : 'Your performance plan has been saved.',
                            )
                        }}
                    </p>
                    <div v-if="editable" class="event-form-actions">
                        <Button
                            type="submit"
                            :disabled="
                                pending ||
                                !form.pet_id ||
                                insufficientFunds ||
                                missingKit ||
                                missingOffspring
                            "
                            :aria-busy="form.processing"
                            >{{
                                form.processing
                                    ? t('Saving…')
                                    : ownEntry
                                      ? t(
                                            event.discipline === 'progeny'
                                                ? 'Save offspring group'
                                                : 'Save performance plan',
                                        )
                                      : t('Enter for {amount} coins', {
                                            amount: number(event.fee),
                                        })
                            }}</Button
                        >
                        <Button
                            v-if="ownEntry"
                            type="button"
                            variant="outline"
                            :disabled="pending"
                            :aria-busy="cancelForm.processing"
                            @click="withdraw"
                            >{{
                                cancelForm.processing
                                    ? t('Cancelling…')
                                    : t('Withdraw and refund entry fee')
                            }}</Button
                        >
                    </div>
                    <p v-if="editable" class="event-note">
                        {{
                            t(
                                'You can change the plan or withdraw with a full refund until registration closes.',
                            )
                        }}
                    </p>
                </form>
            </SurfaceCard>
            <aside class="event-rules">
                <SurfaceCard :title="t('How judging works')"
                    ><p>{{ t(`events.judging.${event.discipline}`) }}</p>
                    <p>
                        {{
                            t(
                                'Compete within your division. Club dogs fill empty places and follow the same rules.',
                            )
                        }}
                    </p>
                    <p v-if="event.discipline !== 'progeny'">
                        {{
                            t(
                                'A title records an achievement. Experience helps in its own discipline, with a limited effect.',
                            )
                        }}
                    </p></SurfaceCard
                >
                <SurfaceCard
                    v-if="
                        selectedDog?.titles.length &&
                        event.discipline !== 'progeny'
                    "
                    :title="t('Dog titles')"
                    ><ul class="event-title-list">
                        <li
                            v-for="(title, titleIndex) in selectedDog.titles"
                            :key="titleIndex"
                        >
                            <Trophy :size="17" aria-hidden="true" /><span
                                >{{ title.name
                                }}<small
                                    >{{
                                        t(
                                            `events.discipline.${title.discipline}`,
                                        )
                                    }}
                                    ·
                                    {{
                                        t(`events.frequency.${title.frequency}`)
                                    }}</small
                                ></span
                            >
                        </li>
                    </ul></SurfaceCard
                >
            </aside>
        </div>

        <SurfaceCard
            :title="
                t(event.status === 'completed' ? 'Results' : 'Participants')
            "
            class="event-results"
        >
            <p v-if="!event.entries.length" class="event-note">
                {{
                    t(
                        'Be the first to enter. Club dogs will join before the start if places remain.',
                    )
                }}
            </p>
            <section
                v-for="[division, entries] in entriesByDivision"
                :key="division"
                class="event-division"
            >
                <h3>
                    {{
                        t('Division: {name}', {
                            name: divisionLabel(division, entries),
                        })
                    }}
                </h3>
                <div class="event-table-scroll">
                    <table class="event-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ t('Place') }}</th>
                                <th scope="col">{{ t('Dog') }}</th>
                                <th scope="col">{{ t('Result') }}</th>
                                <th scope="col">{{ t('Reward') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="participant in entries"
                                :key="participant.id"
                                :class="{
                                    'is-own': participant.id === ownEntry?.id,
                                }"
                            >
                                <td>
                                    {{
                                        participant.rank === null
                                            ? '—'
                                            : number(participant.rank)
                                    }}
                                </td>
                                <td>
                                    <Link
                                        v-if="
                                            participant.petId &&
                                            !participant.isNpc
                                        "
                                        :href="dogProfile(participant.petId)"
                                        class="text-link"
                                        >{{ participant.name }}</Link
                                    ><strong v-else>{{
                                        participant.name
                                    }}</strong
                                    ><small
                                        >{{
                                            participant.isNpc
                                                ? t('Club dog (NPC)')
                                                : participant.ownerName
                                        }}{{
                                            participant.id === ownEntry?.id
                                                ? ` · ${t('Your dog')}`
                                                : ''
                                        }}</small
                                    >
                                </td>
                                <td>
                                    <template v-if="participant.result"
                                        ><span
                                            v-if="
                                                [
                                                    'cancelled',
                                                    'withdrawn',
                                                ].includes(participant.status)
                                            "
                                            >{{ t('Withdrawn') }}</span
                                        ><span
                                            v-else-if="
                                                participant.result.eliminated
                                            "
                                            >{{ t('Eliminated') }}</span
                                        ><template v-else
                                            ><span v-if="isShow">{{
                                                t('Score: {score}', {
                                                    score: decimal(
                                                        participant.result
                                                            .score,
                                                    ),
                                                })
                                            }}</span
                                            ><span v-else>{{
                                                t(
                                                    '{time} s · {count} penalties',
                                                    {
                                                        time: decimal(
                                                            participant.result
                                                                .time,
                                                        ),
                                                        count: number(
                                                            participant.result
                                                                .penalties,
                                                        ),
                                                    },
                                                )
                                            }}</span></template
                                        ><Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="replayId = participant.id"
                                            >{{
                                                t(
                                                    [
                                                        'cancelled',
                                                        'withdrawn',
                                                    ].includes(
                                                        participant.status,
                                                    )
                                                        ? 'View withdrawal reason'
                                                        : event.discipline ===
                                                            'progeny'
                                                          ? 'View evaluation'
                                                          : 'View replay',
                                                )
                                            }}</Button
                                        ></template
                                    ><span v-else>{{
                                        t('Awaiting start')
                                    }}</span>
                                </td>
                                <td>
                                    {{
                                        participant.prize > 0
                                            ? t('{amount} coins', {
                                                  amount: number(
                                                      participant.prize,
                                                  ),
                                              })
                                            : '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </SurfaceCard>
        <SurfaceCard
            v-if="replay?.result"
            :title="
                t(
                    replayWithdrawn
                        ? 'Entry withdrawn — {name}'
                        : event.discipline === 'progeny'
                          ? 'Progeny evaluation — {name}'
                          : 'Performance replay — {name}',
                    { name: replay.name },
                )
            "
            class="event-replay"
        >
            <p
                v-if="replayWithdrawn && replay.result.reason"
                class="event-note"
            >
                {{ replay.result.reason }}
            </p>
            <p v-else-if="!replayWithdrawn" class="event-note">
                {{
                    t(
                        event.discipline === 'progeny'
                            ? 'The evaluation shows how the selected offspring scored against each judging criterion.'
                            : 'The replay shows your saved decisions, mistakes and condition after every stage.',
                    )
                }}
            </p>
            <ol
                v-if="!replayWithdrawn && replay.result.stages.length"
                class="event-replay-stages"
            >
                <li
                    v-for="(stage, stageIndex) in replay.result.stages"
                    :key="stage.key"
                >
                    <span class="event-replay-number">{{
                        number(stageIndex + 1)
                    }}</span>
                    <div class="event-replay-body">
                        <h3>{{ stageLabel(stage.key) }}</h3>
                        <strong v-if="event.discipline !== 'progeny'">{{
                            optionLabel(stage.key, stage.decision)
                        }}</strong>
                        <p>{{ t(stage.reason) }}</p>
                        <dl class="event-replay-metrics">
                            <div v-if="event.discipline !== 'progeny'">
                                <dt>{{ t('Time') }}</dt>
                                <dd>
                                    {{
                                        t('{time} seconds', {
                                            time: decimal(stage.time),
                                        })
                                    }}
                                </dd>
                            </div>
                            <div v-if="event.discipline !== 'progeny'">
                                <dt>{{ t('Penalties') }}</dt>
                                <dd>{{ number(stage.penalties) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('Score') }}</dt>
                                <dd>{{ decimal(stage.score) }}</dd>
                            </div>
                            <div v-if="event.discipline !== 'progeny'">
                                <dt>{{ t('Fatigue') }}</dt>
                                <dd>{{ decimal(stage.fatigue) }}</dd>
                            </div>
                            <div v-if="event.discipline !== 'progeny'">
                                <dt>{{ t('Focus') }}</dt>
                                <dd>{{ decimal(stage.focus) }}</dd>
                            </div>
                        </dl>
                    </div>
                </li>
            </ol>
            <div class="event-replay-total">
                <PawPrint :size="18" aria-hidden="true" />{{
                    replayWithdrawn
                        ? t('Withdrawn')
                        : replay.result.eliminated
                          ? t('Eliminated')
                          : isShow
                            ? t('Final score: {score}', {
                                  score: decimal(replay.result.score),
                              })
                            : t('Final result: {time} s · {count} penalties', {
                                  time: decimal(replay.result.time),
                                  count: number(replay.result.penalties),
                              })
                }}
            </div>
        </SurfaceCard>
    </div>
</template>
