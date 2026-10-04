<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, Trophy } from '@lucide/vue';
import DogCompetitionCredentials from '@/components/DogCompetitionCredentials.vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useGameEventEntry } from '@/composables/useGameEventEntry';
import { useGameEventPresentation } from '@/composables/useGameEventPresentation';
import { useI18n } from '@/composables/useI18n';
import { index as shop } from '@/routes/shop';
import type { GameEventEntryProps } from '@/types/game-event';

const props = defineProps<GameEventEntryProps>();
const { t, number } = useI18n();
const { stageLabel, optionLabel, phaseLabel, gearDescription, modifier } =
    useGameEventPresentation();
const {
    ownEntry,
    form,
    cancelForm,
    errorPanel,
    now,
    pending,
    selectedDog,
    withdrawn,
    editable,
    errors,
    insufficientFunds,
    shortfall,
    validEquipment,
    missingKit,
    missingOffspring,
    toggleGear,
    submit,
    withdraw,
} = useGameEventEntry(props);
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
                        :disabled="!editable || pending || Boolean(ownEntry)"
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
                    v-if="selectedDog && event.discipline === 'conformation'"
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
                                    t(`events.progenyCriterion.${stage.key}`)
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
                                class="event-note"
                                >{{ gearDescription(gear) }}</span
                            >
                            <span class="event-gear-modifiers"
                                ><span
                                    v-for="(value, key) in gear.modifiers"
                                    :key="key"
                                    >{{ t(`events.modifier.${key}`) }}
                                    {{ modifier(value) }}</span
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
            <SurfaceCard :title="t('Participation rules')">
                <p>
                    {{
                        t(
                            'A player may enter up to {limit} events per Moscow day across all dogs and disciplines.',
                            {
                                limit: number(
                                    event.participationRules.playerDailyLimit,
                                ),
                            },
                        )
                    }}
                </p>
                <p v-if="event.discipline === 'progeny'">
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
                                        event.participationRules.petDailyLimit,
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
                                        event.participationRules.petRestHours,
                                    ),
                                },
                            )
                        }}
                    </p>
                </template>
            </SurfaceCard>
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
                    selectedDog?.titles.length && event.discipline !== 'progeny'
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
                                    t(`events.discipline.${title.discipline}`)
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
</template>
