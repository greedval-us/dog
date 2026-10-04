<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Clock3,
    RotateCcw,
    Package,
    Check,
    Zap,
    Dumbbell,
    ChevronRight,
    Heart,
    Droplets,
    HandHeart,
    Soup,
} from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import HelpHint from '@/components/HelpHint.vue';
import InputError from '@/components/InputError.vue';
import PetCareDialog from '@/components/PetCareDialog.vue';
import PetCareIncidents from '@/components/PetCareIncidents.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { usePetCareActivity } from '@/composables/usePetCareActivity';
import { stateLabels, statLabels } from '@/lib/petLabels';
import { careOptionReason } from '@/lib/petCareAvailability';
import { careModifiers } from '@/lib/petCarePreview';
import {
    careActions as actions,
    careDuration,
} from '@/lib/petCarePresentation';
import { index as inventory } from '@/routes/inventory';
import { index as dogWork } from '@/routes/dog-work';
import type { PlayerPet } from '@/types/pet';
import type { CareGroup, CareOption, PetCare } from '@/types/pet-care';

const props = withDefaults(
    defineProps<{ pet: PlayerPet; care: PetCare; trainingOnly?: boolean }>(),
    { trainingOnly: false },
);
const { t, number } = useI18n();
const id = useId();
const open = ref(false);
const group = ref<CareGroup>('feed');
const variant = ref('');
const {
    now,
    secondsLeft,
    countdown,
    progress,
    readyToFinish,
    busyMessage,
    completionFailed,
    finish,
    finishError,
    finishActivity,
} = usePetCareActivity(
    () => props.pet.id,
    () => props.care,
    () => !props.trainingOnly,
);
const duration = (seconds: number) => careDuration(seconds, t);
const trainingOptions = computed(() =>
    props.care.options.filter((option) => option.group === 'training'),
);
const modifiers = computed(() => careModifiers(props.care, now.value));
const optionReason = (option: CareOption) =>
    careOptionReason(
        option,
        props.pet.energy.value,
        modifiers.value.energy_cost_percent ?? 0,
    );
function groupReason(value: CareGroup, option?: CareOption): string | null {
    if (props.care.blocked)
        return t('Care is unavailable for this dog or account.');
    if (props.care.working) return t('Your dog is working.');
    if (props.care.busy) return t(busyMessage.value);
    const remaining = secondsLeft(props.care.cooldowns[value]);
    if (remaining)
        return t('Available in {time}', { time: countdown(remaining) });
    if (option) {
        const reason = optionReason(option);
        return reason ? t(reason) : null;
    }
    const variants = props.care.options.filter((item) => item.group === value);
    if (!variants.length) return t('No options are available yet.');
    return variants.every((item) => optionReason(item))
        ? t(optionReason(variants[0])!)
        : null;
}
const actionHints = computed(() => {
    if (props.care.blocked || props.care.busy || props.care.working) return [];
    const hints = new Map<string, string[]>();
    for (const action of actions) {
        const reason = groupReason(action.group);
        if (!reason || secondsLeft(props.care.cooldowns[action.group]))
            continue;
        const labels = hints.get(reason) ?? [];
        labels.push(t(action.label));
        hints.set(reason, labels);
    }
    return [...hints].map(([reason, labels]) => ({
        reason,
        message: labels.join(' · ') + ': ' + reason,
    }));
});
function actionStatus(value: CareGroup): string {
    if (props.care.blocked) return t('Unavailable');
    if (props.care.busy || props.care.working) return t('Busy');
    const remaining = secondsLeft(props.care.cooldowns[value]);
    if (remaining) return t('Rest · {time}', { time: countdown(remaining) });
    return groupReason(value) ? t('Check requirements') : t('Choose options');
}
function choose(value: CareGroup, selectedVariant?: string): void {
    group.value = value;
    const options = props.care.options.filter(
        (option) => option.group === value,
    );
    const first = options.find((option) => !optionReason(option)) ?? options[0];
    variant.value = selectedVariant ?? first?.id ?? '';
    open.value = true;
}
</script>

<template>
    <SurfaceCard
        :id="trainingOnly ? 'pet-training' : 'pet-care'"
        class="pet-care"
        :title="t(trainingOnly ? 'Training' : 'Quick actions')"
    >
        <template #header>
            <div class="pet-care-title-row">
                <div class="pet-care-title">
                    <span class="pet-care-title-icon"
                        ><component
                            :is="trainingOnly ? Dumbbell : HandHeart"
                            :size="22"
                            aria-hidden="true"
                    /></span>
                    <h2>
                        {{ t(trainingOnly ? 'Training' : 'Quick actions') }}
                    </h2>
                    <HelpHint
                        :label="t(trainingOnly ? 'Training' : 'Quick actions')"
                        :text="
                            t(
                                trainingOnly
                                    ? 'One equipment charge per session. Gains depend on quality, mood and bond.'
                                    : 'Choose an action to compare its options.',
                            )
                        "
                    />
                </div>
                <Link
                    v-if="!trainingOnly"
                    :href="inventory()"
                    class="pet-care-inventory"
                    ><Package :size="16" aria-hidden="true" />{{ t('Inventory')
                    }}<ChevronRight :size="14" aria-hidden="true"
                /></Link>
            </div>
        </template>
        <div v-if="trainingOnly" class="pet-training-readiness">
            <span>{{ t('To start') }}</span>
            <span
                v-for="need in [
                    { key: 'health', icon: Heart },
                    { key: 'satiety', icon: Soup },
                    { key: 'hydration', icon: Droplets },
                ] as const"
                :key="need.key"
                :class="{ 'pet-training-need-low': pet.states[need.key] < 50 }"
            >
                <component :is="need.icon" :size="13" aria-hidden="true" />{{
                    t(stateLabels[need.key])
                }}
                ≥ 50%
            </span>
        </div>
        <PetCareIncidents
            v-if="!trainingOnly"
            :incidents="care.recentIncidents"
        />
        <div v-if="trainingOnly" class="pet-training-options">
            <Button
                v-for="option in trainingOptions"
                :key="option.id"
                type="button"
                variant="plain"
                class="pet-training-option"
                :disabled="care.blocked"
                :aria-describedby="
                    groupReason('training', option)
                        ? id + '-training-' + option.id
                        : undefined
                "
                @click="choose('training', option.id)"
            >
                <span class="pet-training-option-heading"
                    ><Dumbbell :size="16" aria-hidden="true" /><strong>{{
                        option.label
                    }}</strong
                    ><ChevronRight :size="15" aria-hidden="true"
                /></span>
                <span class="pet-training-targets"
                    ><span v-for="(_, stat) in option.statGains" :key="stat">{{
                        t(statLabels[stat])
                    }}</span></span
                >
                <span class="pet-training-meta"
                    ><span
                        ><Clock3 :size="12" aria-hidden="true" />{{
                            duration(option.duration)
                        }}</span
                    ><span
                        :aria-label="`${t('Energy')}: ${number(option.energy)}`"
                        ><Zap :size="12" aria-hidden="true" />{{
                            number(option.energy)
                        }}</span
                    ><span v-if="secondsLeft(care.cooldowns.training)"
                        ><RotateCcw :size="12" aria-hidden="true" />{{
                            countdown(secondsLeft(care.cooldowns.training))
                        }}</span
                    ></span
                >
                <span
                    v-if="groupReason('training', option)"
                    :id="id + '-training-' + option.id"
                    class="pet-action-reason"
                    >{{ groupReason('training', option) }}</span
                >
            </Button>
            <p v-if="!trainingOptions.length" class="pet-feature-note">
                {{ t('No training sessions are available yet.') }}
            </p>
        </div>
        <div v-else class="pet-care-actions">
            <Button
                v-for="action in actions"
                :key="action.group"
                type="button"
                variant="plain"
                class="pet-care-action-cell pet-care-action"
                :class="[
                    'pet-tone-' + action.tone,
                    { 'has-restriction': Boolean(groupReason(action.group)) },
                ]"
                :disabled="care.blocked"
                :aria-label="t(action.label)"
                :aria-describedby="
                    groupReason(action.group)
                        ? id + '-action-' + action.group
                        : undefined
                "
                @click="choose(action.group)"
            >
                <span class="pet-care-action-icon"
                    ><component :is="action.icon" :size="24" aria-hidden="true"
                /></span>
                <span class="pet-care-action-copy">
                    <strong>{{ t(action.label) }}</strong>
                    <span
                        v-if="groupReason(action.group)"
                        :id="id + '-action-' + action.group"
                        class="pet-action-reason"
                    >
                        {{ actionStatus(action.group) }}
                        <span class="sr-only">{{
                            groupReason(action.group)
                        }}</span>
                    </span>
                </span>
                <ChevronRight
                    class="pet-care-action-arrow"
                    :size="15"
                    aria-hidden="true"
                />
            </Button>
        </div>
        <div
            v-if="!trainingOnly && actionHints.length"
            class="pet-action-hints"
        >
            <ActionHint
                v-for="hint in actionHints"
                :key="hint.reason"
                :message="hint.message"
            />
        </div>
        <div v-if="care.active && !trainingOnly" class="pet-care-progress">
            <div class="pet-care-progress-heading">
                <strong
                    ><Clock3 :size="17" aria-hidden="true" />{{
                        t(care.active.label)
                    }}</strong
                >
                <span>{{
                    secondsLeft(care.active.endsAt)
                        ? countdown(secondsLeft(care.active.endsAt))
                        : t(
                              completionFailed
                                  ? 'Retry completion'
                                  : 'Finishing...',
                          )
                }}</span>
            </div>
            <progress
                :value="progress"
                max="100"
                :aria-label="t('Activity progress')"
            />
            <p v-if="!secondsLeft(care.active.endsAt)" role="status">
                {{ t(busyMessage) }}
            </p>
            <Button
                v-if="readyToFinish && completionFailed"
                type="button"
                :disabled="finish.processing || care.blocked"
                @click="finishActivity"
            >
                <Check :size="16" aria-hidden="true" />{{
                    t(finish.processing ? 'Finishing...' : 'Retry completion')
                }}
            </Button>
            <InputError :message="finishError" />
        </div>
        <p v-else-if="care.working" class="pet-care-notice">
            {{ t('Your dog is working.') }}
            <Link
                :href="dogWork({ query: { pet: pet.id } })"
                class="text-link"
                >{{ t('Open the job board') }}</Link
            >
        </p>
        <p v-else-if="care.busy && !care.active" class="pet-care-notice">
            {{ t('Your dog is busy with another activity.') }}
        </p>
        <p v-else-if="care.blocked" class="pet-care-notice">
            {{ t('Care is unavailable for this dog or account.') }}
        </p>

        <PetCareDialog
            v-model:open="open"
            :pet="pet"
            :care="care"
            :group="group"
            :variant="variant"
            :now="now"
            :busy-message="busyMessage"
        >
            <template #completion>
                <Button
                    v-if="readyToFinish && completionFailed"
                    type="button"
                    :disabled="finish.processing || care.blocked"
                    @click="finishActivity"
                    >{{ t('Retry completion') }}</Button
                >
            </template>
        </PetCareDialog>
    </SurfaceCard>
</template>
