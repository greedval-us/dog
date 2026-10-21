<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, ChevronDown, Trophy } from '@lucide/vue';
import { computed, ref } from 'vue';
import DogCompetitionCredentials from '@/components/DogCompetitionCredentials.vue';
import EventEntryClassification from '@/components/EventEntryClassification.vue';
import GameEventPreparation from '@/components/GameEventPreparation.vue';
import GameEventProgenyGroup from '@/components/GameEventProgenyGroup.vue';
import GameEventShowGroup from '@/components/GameEventShowGroup.vue';
import HelpHint from '@/components/HelpHint.vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useGameEventEntry } from '@/composables/useGameEventEntry';
import { useGameEventPresentation } from '@/composables/useGameEventPresentation';
import { useI18n } from '@/composables/useI18n';
import { index as shop } from '@/routes/shop';
import type { GameEventEntryProps } from '@/types/game-event';
import { formatAgeMonths } from '@/lib/petAge';

const props = defineProps<GameEventEntryProps>();
const { t, number, locale } = useI18n();
const { stageLabel, optionLabel, phaseLabel, gearDescription, modifierEffect } =
    useGameEventPresentation();
const {
    ownEntry,
    multipleOwnedShow,
    groupRing,
    selectedMemberGroup,
    form,
    cancelForm,
    errorPanel,
    now,
    pending,
    selectedDog,
    selectedExhibitionClass,
    regulated,
    entryOptions,
    selectedEntryOption,
    selectedEntryOptionKey,
    missingEntryOption,
    registrationOpen,
    withdrawn,
    editable,
    errors,
    insufficientFunds,
    shortfall,
    validEquipment,
    missingKit,
    missingOffspring,
    missingGroupMembers,
    missingExhibitionClass,
    blockedDog,
    applyStagePreset: preset,
    toggleGear,
    submit,
    withdraw,
} = useGameEventEntry(props);
const refreshingPreparation = ref(false);
const preparationUpdatedAt = ref(props.serverNow);
const warmupEquipment = computed(
    () => regulated.value && props.event.discipline === 'agility',
);
const showPresentationEffects = computed(
    () =>
        regulated.value &&
        props.event.discipline === 'conformation' &&
        (ownEntry.value?.result
            ? ownEntry.value.result.stages.some(
                  (stage) =>
                      stage.factors?.presentationMultiplier !== undefined,
              )
            : props.event.calculationVersion >= 3),
);
const oneStageOdorTest = computed(
    () =>
        props.event.regulation?.format === 'odor_test' &&
        props.event.stages.length === 1,
);
const progenyRing = computed(
    () => props.event.regulation?.format === 'progeny_ring',
);
function refreshPreparation() {
    if (refreshingPreparation.value || pending.value) return;
    router.reload({
        only: ['dogs', 'equipment', 'serverNow'],
        onStart: () => {
            refreshingPreparation.value = true;
        },
        onSuccess: () => {
            preparationUpdatedAt.value = props.serverNow;
        },
        onFinish: () => {
            refreshingPreparation.value = false;
        },
    });
}
const selectedModifiers = computed(() => {
    const totals: Record<string, number> = {};
    for (const gear of validEquipment.value.filter((item) =>
        form.gear_ids.includes(item.id),
    )) {
        for (const [key, value] of Object.entries(gear.modifiers))
            totals[key] = (totals[key] ?? 0) + value;
    }
    return Object.entries(totals).map(
        ([key, value]) => [key, Math.max(-0.2, Math.min(0.2, value))] as const,
    );
});
const statLabels: Record<string, string> = {
    speed: 'Speed',
    agility: 'Agility',
    endurance: 'Endurance',
    strength: 'Strength',
    obedience: 'Obedience',
    intelligence: 'Intelligence',
};
function stageWeights(key: string): string {
    const stage = selectedDog.value?.preparation?.stages.find(
        (stage) => stage.key === key,
    );
    return stage
        ? Object.entries(stage.weights)
              .map(
                  ([stat, weight]) =>
                      `${t(statLabels[stat] ?? stat)} ${number(Math.round(weight * 100))}%`,
              )
              .join(' · ')
        : '';
}
</script>

<template>
    <div class="event-layout">
        <SurfaceCard
            :title="t(ownEntry ? 'Your entry' : 'Prepare your entry')"
            class="event-preparation"
        >
            <p
                v-if="ownEntry && event.status === 'completed' && !withdrawn"
                class="event-success"
                role="status"
            >
                <Check :size="18" aria-hidden="true" />{{
                    t(
                        groupRing
                            ? 'The group ring is complete. See breed selection and final results below.'
                            : event.discipline === 'progeny'
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
            <p v-if="withdrawn && ownEntry?.result?.reason" class="event-note">
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
                            (!editable &&
                                !(multipleOwnedShow && registrationOpen)) ||
                            pending ||
                            (Boolean(ownEntry) && !multipleOwnedShow)
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
                                !dog.isActive && dog.id !== ownEntry?.petId
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
                <EventEntryClassification
                    v-if="regulated && selectedDog"
                    v-model="selectedEntryOptionKey"
                    :dog="selectedDog"
                    :options="entryOptions"
                    :disabled="!editable || pending"
                    :error="
                        form.errors['plan.exhibition_class'] ??
                        form.errors['plan.sport_class'] ??
                        form.errors['plan.program'] ??
                        form.errors['plan.test_type'] ??
                        form.errors['plan.handler_category']
                    "
                />
                <p
                    v-if="
                        event.discipline === 'nosework' &&
                        selectedEntryOption?.timeLimitSeconds !== undefined
                    "
                    class="event-note"
                >
                    {{
                        t(
                            oneStageOdorTest
                                ? 'Search time limit: {seconds} seconds.'
                                : 'Search time limit: {seconds} seconds per category.',
                            {
                                seconds: number(
                                    selectedEntryOption.timeLimitSeconds,
                                ),
                            },
                        )
                    }}
                </p>
                <template
                    v-if="
                        !regulated &&
                        selectedDog &&
                        event.discipline === 'conformation'
                    "
                >
                    <p class="event-note">
                        {{
                            t('Age at the start: {age}', {
                                age: formatAgeMonths(
                                    selectedDog.ageMonths,
                                    locale,
                                ),
                            })
                        }}
                    </p>
                    <FormField
                        id="event-exhibition-class"
                        :label="t('Exhibition class')"
                        :error="form.errors['plan.exhibition_class']"
                        v-slot="{ field }"
                    >
                        <select
                            v-bind="field"
                            v-model="form.plan.exhibition_class"
                            class="events-select"
                            :disabled="!editable || pending"
                        >
                            <option
                                v-for="item in selectedDog.availableClasses"
                                :key="item.key"
                                :value="item.key"
                            >
                                {{ item.label }}
                            </option>
                        </select>
                    </FormField>
                    <p class="event-note">
                        {{
                            t(
                                'One class per exhibition. Overlapping age classes let you choose which award to pursue.',
                            )
                        }}
                    </p>
                </template>
                <p v-if="!dogs.length" class="event-note">
                    {{ t('You need a dog to enter this event.') }}
                </p>
                <p
                    v-if="editable && missingKit"
                    class="event-note"
                    role="status"
                >
                    {{
                        t(
                            'Canicross requires a pulling harness, tugline and handler belt. Select all three before entering.',
                        )
                    }}
                </p>
                <GameEventPreparation
                    v-if="selectedDog && editable && !groupRing"
                    :dog="selectedDog"
                    :exhibition-class-label="selectedExhibitionClass?.label"
                    :classification-label="selectedEntryOption?.label"
                    :discipline="event.discipline"
                    :closes-at="event.closesAt"
                    :updated-at="preparationUpdatedAt"
                    :refreshing="refreshingPreparation"
                    :disabled="pending"
                    @refresh="refreshPreparation"
                />
                <div
                    v-if="selectedDog && event.discipline === 'conformation'"
                    class="event-exterior"
                >
                    <h3>{{ t('Breed conformation') }}</h3>
                    <p class="event-note">
                        {{
                            t(
                                'Exterior is inherited and cannot be trained: breed type, structure and movement. Care and handling help the dog show its qualities; even 100/100 does not guarantee a win.',
                            )
                        }}
                    </p>
                    <dl class="event-exterior-values">
                        <div
                            v-for="(value, key) in selectedDog.exterior"
                            :key="key"
                        >
                            <dt>{{ t(`events.exterior.${key}`) }}</dt>
                            <dd>{{ number(value) }} / 100</dd>
                        </div>
                    </dl>
                    <p class="event-note">
                        {{
                            t(
                                showPresentationEffects
                                    ? 'Game judging keeps presentation to at most 20 points out of 100. Calm handling lowers risk; expressive handling strengthens presentation with more risk. Movement equipment affects only the movement stage’s presentation, never inherited exterior.'
                                    : 'Exterior contributes 80%; presentation contributes 20%. Cleanliness, health and focus support presentation.',
                            )
                        }}
                    </p>
                </div>
                <GameEventShowGroup
                    v-if="
                        selectedMemberGroup &&
                        selectedDog &&
                        (event.discipline === 'couple' ||
                            event.discipline === 'breeders')
                    "
                    v-model="form.plan.member_ids"
                    :dog="selectedDog"
                    :discipline="event.discipline"
                    :system="event.regulation!.system"
                    :disabled="!editable || pending"
                />
                <GameEventProgenyGroup
                    v-if="progenyRing && selectedDog"
                    v-model="form.plan.offspring_ids"
                    :dog="selectedDog"
                    :system="event.regulation!.system"
                    :disabled="!editable || pending"
                />
                <fieldset
                    v-else-if="event.discipline === 'progeny' && selectedDog"
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
                                !form.plan.offspring_ids?.includes(
                                    offspring.id,
                                ) && (form.plan.offspring_ids?.length ?? 0) >= 5
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

                <template v-if="event.discipline !== 'progeny' && !groupRing">
                    <div class="event-plan-heading surface-heading-help">
                        <h3>{{ t('Your performance plan') }}</h3>
                        <HelpHint
                            :text="
                                t(
                                    'Each stage affects the next. A bold start can cost focus or stamina later.',
                                )
                            "
                        />
                    </div>
                    <div
                        v-if="editable"
                        class="event-plan-presets"
                        :aria-label="t('Plan presets')"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="pending"
                            @click="preset(['careful', 'careful', 'careful'])"
                            >{{ t('Steady throughout') }}</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="pending"
                            @click="
                                preset(['balanced', 'balanced', 'balanced'])
                            "
                            >{{ t('Balanced throughout') }}</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="pending"
                            @click="preset(['careful', 'balanced', 'bold'])"
                            >{{ t('Save for the finish') }}</Button
                        >
                    </div>
                    <fieldset
                        v-for="(stage, stageIndex) in event.stages"
                        :key="stage.key"
                        class="event-stage"
                        :disabled="!editable || pending"
                    >
                        <legend>
                            <span>{{ number(stageIndex + 1) }}</span
                            >{{
                                event.regulation
                                    ? stage.label
                                    : stageLabel(stage.key)
                            }}
                        </legend>
                        <p
                            v-if="stageWeights(stage.key)"
                            class="event-stage-weights"
                        >
                            {{
                                t(
                                    event.discipline === 'conformation'
                                        ? 'Error control: {weights}'
                                        : 'Quality inputs: {weights}',
                                    { weights: stageWeights(stage.key) },
                                )
                            }}
                        </p>
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
                                        form.plan.stages[stageIndex] === choice,
                                }"
                            >
                                <input
                                    v-model="form.plan.stages[stageIndex]"
                                    type="radio"
                                    :name="`event-stage-${stageIndex}`"
                                    :value="choice"
                                />
                                <strong>{{
                                    optionLabel(
                                        event.discipline,
                                        stage.key,
                                        choice,
                                    )
                                }}</strong>
                            </label>
                        </div>
                        <p class="event-tactic-effect">
                            {{
                                t(
                                    showPresentationEffects
                                        ? `events.showTactic.${form.plan.stages[stageIndex]}`
                                        : `events.tactic.${event.discipline}.${form.plan.stages[stageIndex]}`,
                                )
                            }}
                        </p>
                    </fieldset>
                </template>
                <section v-else-if="groupRing" class="event-exterior">
                    <h3>{{ t('Judging criteria') }}</h3>
                    <p class="event-note">
                        {{
                            t(
                                'The judge compares breed type, uniformity and movement of the group in the ring. Past titles add no judging bonus.',
                            )
                        }}
                    </p>
                    <p class="event-note">
                        {{
                            t(
                                'The best group of each breed reaches the final. Places 1–3 and coin prizes are awarded in the final only; this contest does not award CAC or CACIB.',
                            )
                        }}
                    </p>
                </section>
                <section v-else class="event-exterior">
                    <h3>{{ t('Judging criteria') }}</h3>
                    <p class="event-note">
                        {{
                            t(
                                'This documentary evaluation is a game adaptation of progeny groups. Real RKF/FCI progeny competitions take place in the ring.',
                            )
                        }}
                    </p>
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
                                ><strong>{{
                                    event.regulation
                                        ? stage.label
                                        : stageLabel(stage.key)
                                }}</strong
                                ><small>{{
                                    t(`events.progenyCriterion.${stage.key}`)
                                }}</small></span
                            >
                        </li>
                    </ol>
                </section>

                <template v-if="event.discipline !== 'progeny' && !groupRing">
                    <div class="event-gear-heading">
                        <div class="surface-heading-help">
                            <h3>{{ t('Competition equipment') }}</h3>
                            <HelpHint
                                :text="
                                    [
                                        t(
                                            'Choose one item per slot. The best fit depends on your dog and your plan.',
                                        ),
                                        t(
                                            'One use is spent when registration closes. Each combined modifier is capped at 20%.',
                                        ),
                                    ].join(' ')
                                "
                            />
                        </div>
                        <Button as-child variant="outline" size="sm"
                            ><Link :href="shop()">{{
                                t('Visit the shop')
                            }}</Link></Button
                        >
                    </div>
                    <p class="event-note">
                        {{
                            t(
                                warmupEquipment
                                    ? 'Equipment is removed before the agility course. The warm-up effect supports precision, pace, fatigue control and starting focus.'
                                    : `events.equipmentRule.${event.discipline}`,
                            )
                        }}
                    </p>
                    <div
                        v-if="selectedModifiers.length"
                        class="event-gear-modifiers"
                        :aria-label="
                            t(
                                warmupEquipment
                                    ? 'Warm-up effect'
                                    : 'Combined equipment effect',
                            )
                        "
                    >
                        <span
                            v-for="[key, value] in selectedModifiers"
                            :key="key"
                            >{{
                                modifierEffect(
                                    key,
                                    value,
                                    showPresentationEffects,
                                )
                            }}</span
                        >
                        <HelpHint
                            :text="
                                t(
                                    showPresentationEffects
                                        ? 'Precision lowers base error risk in percentage points; focus adds starting focus points. Stamina reduces fatigue gained at each stage. Movement equipment changes only movement presentation within the 20-point presentation limit.'
                                        : 'Precision lowers the base error risk in percentage points. Focus adds starting focus points. Pace affects time; stamina reduces fatigue gained at each stage.',
                                )
                            "
                        />
                    </div>
                    <p
                        v-if="
                            event.discipline === 'conformation' &&
                            !showPresentationEffects
                        "
                        class="event-note"
                    >
                        {{
                            t(
                                'For conformation, focus and precision help judging; stamina limits fatigue. Pace equipment does not increase the final score.',
                            )
                        }}
                    </p>
                    <p v-if="oneStageOdorTest" class="event-note">
                        {{
                            t(
                                'Stamina does not affect this one-stage odor test: there is no later stage for reduced fatigue to help.',
                            )
                        }}
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
                                'is-selected': form.gear_ids.includes(gear.id),
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
                                :class="
                                    form.gear_ids.includes(gear.id)
                                        ? 'event-note'
                                        : 'sr-only'
                                "
                                >{{ gearDescription(gear) }}</span
                            >
                            <span class="event-gear-modifiers"
                                ><span
                                    v-for="(value, key) in gear.modifiers"
                                    :key="key"
                                    >{{
                                        modifierEffect(
                                            key,
                                            value,
                                            showPresentationEffects,
                                        )
                                    }}</span
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
                <p v-if="editable && missingOffspring" class="event-note">
                    {{
                        t(
                            progenyRing &&
                                (form.plan.offspring_ids?.length ?? 0) >= 3
                                ? 'Remove ineligible members and select 3–5 admitted offspring.'
                                : 'Select at least three direct offspring before entering.',
                        )
                    }}
                </p>
                <p v-if="editable && missingGroupMembers" class="event-note">
                    {{
                        t(
                            event.discipline === 'couple'
                                ? 'Remove ineligible members and select one admitted partner of the opposite sex.'
                                : 'Remove ineligible members and select 2–4 admitted companions from the same breeder or kennel.',
                        )
                    }}
                </p>
                <p v-if="editable && insufficientFunds" class="event-note">
                    {{
                        t('You need {amount} more coins.', {
                            amount: number(shortfall),
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
                            selectedMemberGroup
                                ? 'Your group has been saved.'
                                : event.discipline === 'progeny'
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
                            missingOffspring ||
                            missingGroupMembers ||
                            missingExhibitionClass ||
                            missingEntryOption ||
                            blockedDog
                        "
                        :aria-busy="form.processing"
                        >{{
                            form.processing
                                ? t('Saving…')
                                : ownEntry
                                  ? t(
                                        selectedMemberGroup
                                            ? 'Save group'
                                            : event.discipline === 'progeny'
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
            <details class="event-explanation event-rules-disclosure">
                <summary>
                    {{ t('Rules and judging')
                    }}<ChevronDown :size="16" aria-hidden="true" />
                </summary>
                <SurfaceCard :title="t('Participation rules')">
                    <p>
                        {{
                            t(
                                'A player may enter up to {limit} events per Moscow day across all dogs and disciplines.',
                                {
                                    limit: number(
                                        event.participationRules
                                            .playerDailyLimit,
                                    ),
                                },
                            )
                        }}
                    </p>
                    <p v-if="groupRing">
                        {{
                            t(
                                selectedMemberGroup
                                    ? 'The individual ring and group contests count as one exhibition visit for daily limits and rest.'
                                    : 'The individual ring and producer group count as one exhibition visit for daily limits and rest.',
                            )
                        }}
                    </p>
                    <p v-else-if="event.discipline === 'progeny'">
                        {{
                            t(
                                'Progeny judging does not count toward the dog’s physical event limit and does not require event rest. The player’s daily limit still applies.',
                            )
                        }}
                    </p>
                    <template v-else>
                        <p>
                            {{
                                t(
                                    'A dog may enter up to {limit} physical competitions and exhibitions combined per Moscow day.',
                                    {
                                        limit: number(
                                            event.participationRules
                                                .petDailyLimit,
                                        ),
                                    },
                                )
                            }}
                        </p>
                        <p>
                            {{
                                t(
                                    'Allow at least {hours} h from the previous event’s end until the next event’s registration closes, when preparation begins.',
                                    {
                                        hours: number(
                                            event.participationRules
                                                .petRestHours,
                                        ),
                                    },
                                )
                            }}
                        </p>
                    </template>
                </SurfaceCard>
                <SurfaceCard :title="t('How judging works')"
                    ><p>
                        {{
                            groupRing
                                ? t(
                                      'The judge compares breed type, uniformity and movement of the group in the ring. Past titles add no judging bonus.',
                                  )
                                : t(`events.judging.${event.discipline}`)
                        }}
                    </p>
                    <template v-if="regulated">
                        <p>
                            {{
                                t(
                                    'Age, qualification and the event regulation determine the available classes. Placements, assessments, certificates and championship titles are recorded separately.',
                                )
                            }}
                        </p>
                        <p v-if="event.discipline === 'conformation'">
                            {{
                                t(
                                    'Dogs are assessed by breed, sex and class. Up to four qualified dogs are placed in each class; certificates and best-dog comparisons have additional requirements.',
                                )
                            }}
                        </p>
                        <p v-else-if="event.regulation?.format === 'odor_test'">
                            {{
                                t(
                                    'Each dog is assessed individually on the selected odor. Passing the test records a qualification.',
                                )
                            }}
                        </p>
                        <p v-else-if="!groupRing">
                            {{
                                t(
                                    'Each class and category has one shared standing. A podium finish alone does not guarantee a certificate or a championship title.',
                                )
                            }}
                        </p>
                    </template>
                    <template v-else-if="event.discipline === 'conformation'">
                        <p>
                            {{
                                t(
                                    'Dogs are judged separately by breed, age class and sex. Each category awards first, second and third place.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                t(
                                    'Intermediate, Open and Veteran winners also compete for Best male, Best female and Best of breed. These awards do not grant extra coins.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                t(
                                    'Age is checked at the event start. Titles stay with the dog after it changes class.',
                                )
                            }}
                        </p>
                    </template>
                    <p
                        v-if="
                            event.regulation?.format !== 'odor_test' &&
                            !groupRing
                        "
                    >
                        {{
                            t(
                                'Compete within your division. Club dogs fill empty places and follow the same rules.',
                            )
                        }}
                    </p>
                    <p v-if="event.discipline !== 'progeny' && !groupRing">
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
                        event.discipline !== 'progeny' &&
                        !groupRing
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
            </details>
        </aside>
    </div>
</template>
