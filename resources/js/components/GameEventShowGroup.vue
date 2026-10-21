<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { EventDog, EventGroupEligibility } from '@/types/game-event';

const props = defineProps<{
    dog: EventDog;
    discipline: 'couple' | 'breeders';
    system: 'rkf' | 'fci';
    disabled: boolean;
}>();
const selectedIds = defineModel<number[]>({ default: () => [] });
const { t, number } = useI18n();
const couple = computed(() => props.discipline === 'couple');
const maxCompanions = computed(() => (couple.value ? 1 : 4));
function admission(eligibility?: EventGroupEligibility): string {
    return (
        eligibility?.reason ??
        eligibility?.gradeLabel ??
        eligibility?.grade ??
        t('Admission follows the individual ring')
    );
}
</script>

<template>
    <fieldset class="event-offspring" :disabled="disabled">
        <legend>
            {{
                t(
                    couple
                        ? 'One male + one female of the same breed and owner'
                        : '3–5 dogs of one breed from the same breeder or kennel',
                )
            }}
        </legend>
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
        <p v-if="!couple" class="event-note">
            {{
                t(
                    'Current owners may differ. Every dog must come from the same recorded breeder or kennel.',
                )
            }}
        </p>
        <ul class="event-title-list">
            <li>
                <span
                    ><strong>{{ dog.name }}</strong
                    ><small v-if="dog.kennelName && !couple">{{
                        dog.kennelName
                    }}</small
                    ><small>{{ admission(dog.groupEligibility) }}</small></span
                >
            </li>
        </ul>
        <label
            v-for="member in dog.groupMembers ?? []"
            :key="member.id"
            class="event-offspring-choice"
        >
            <input
                v-model="selectedIds"
                type="checkbox"
                :value="member.id"
                :disabled="
                    !selectedIds.includes(member.id) &&
                    (!member.eligible || selectedIds.length >= maxCompanions)
                "
            />
            <span
                >{{ member.name
                }}<small v-if="member.ownerName">{{
                    t('Owner: {name}', { name: member.ownerName })
                }}</small
                ><small
                    v-if="!couple && (member.kennelName || member.breederName)"
                    >{{ member.kennelName ?? member.breederName }}</small
                ><small>{{ admission(member) }}</small></span
            >
        </label>
        <p class="event-note">
            {{
                t(
                    couple
                        ? 'Selected dogs: {count} of 2'
                        : 'Selected dogs: {count} of 3–5',
                    { count: number(selectedIds.length + 1) },
                )
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
