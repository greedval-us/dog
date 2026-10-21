<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import type { EventDog } from '@/types/game-event';

defineProps<{
    dog: EventDog;
    system: 'rkf' | 'fci';
    disabled: boolean;
}>();
const selectedIds = defineModel<number[]>({ default: () => [] });
const { t, number } = useI18n();
</script>

<template>
    <fieldset class="event-offspring" :disabled="disabled">
        <legend>{{ t('Parent + 3–5 direct offspring') }}</legend>
        <p class="event-note">
            {{
                t(
                    'All members must be entered individually in the linked show. Admission follows the individual ring; old titles do not replace today’s assessment.',
                )
            }}
        </p>
        <p class="event-note">
            {{
                t(
                    system === 'rkf'
                        ? 'RKF: Very good or higher for every member. Baby and Puppy cannot enter.'
                        : 'FCI: Compulsory individual classes in this show are required for every member.',
                )
            }}
        </p>
        <p
            v-if="dog.progenyEligibility?.reason"
            class="event-note"
            role="status"
        >
            {{ dog.progenyEligibility.reason }}
        </p>
        <p
            v-else-if="
                dog.progenyEligibility?.gradeLabel ||
                dog.progenyEligibility?.grade
            "
            class="event-note"
        >
            {{ dog.name }} ·
            {{
                dog.progenyEligibility.gradeLabel ??
                dog.progenyEligibility.grade
            }}
        </p>
        <p v-else class="event-note">
            {{ t('Admission follows the individual ring') }}
        </p>
        <label
            v-for="offspring in dog.offspring"
            :key="offspring.id"
            class="event-offspring-choice"
        >
            <input
                v-model="selectedIds"
                type="checkbox"
                :value="offspring.id"
                :disabled="
                    !selectedIds.includes(offspring.id) &&
                    (offspring.eligible === false || selectedIds.length >= 5)
                "
            />
            <span
                >{{ offspring.name
                }}<small>{{
                    offspring.reason ??
                    offspring.gradeLabel ??
                    offspring.grade ??
                    t('Admission follows the individual ring')
                }}</small></span
            >
        </label>
        <p class="event-note">
            {{
                t('Selected offspring: {count} of 3–5', {
                    count: number(selectedIds.length),
                })
            }}
        </p>
        <p class="event-note">
            {{
                t(
                    'Individual show equipment is reused. No second item use or separate group tactic is needed.',
                )
            }}
        </p>
        <p class="event-note">
            {{
                t(
                    'Every member must be healthy and available for the group ring. Care and calm presentation still affect the result.',
                )
            }}
        </p>
    </fieldset>
</template>
