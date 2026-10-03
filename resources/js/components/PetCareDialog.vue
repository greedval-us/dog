<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Clock3, RotateCcw, Dumbbell } from '@lucide/vue';
import { computed, onUnmounted, useId, watch } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import InputError from '@/components/InputError.vue';
import PetCarePreview from '@/components/PetCarePreview.vue';
import PetCareSupplies from '@/components/PetCareSupplies.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { useCareItems } from '@/composables/useCareItems';
import { careOptionReason } from '@/lib/petCareAvailability';
import { careModifiers } from '@/lib/petCarePreview';
import {
    careActions,
    careCategoryLabels as categories,
    careDuration,
} from '@/lib/petCarePresentation';
import { store } from '@/routes/pets/care';
import { index as shop } from '@/routes/shop';
import { index as dogWork } from '@/routes/dog-work';
import type { PlayerPet } from '@/types/pet';
import type { CareGroup, CareItemSelection, PetCare } from '@/types/pet-care';

const props = defineProps<{
    pet: PlayerPet;
    care: PetCare;
    group: CareGroup;
    variant: string;
    now: number;
    busyMessage: string;
}>();
const open = defineModel<boolean>('open', { required: true });
const { t, number } = useI18n();
const id = useId();
const trainingOnly = computed(() => props.group === 'training');
const duration = (seconds: number) => careDuration(seconds, t);
const options = computed(() =>
    props.care.options.filter((option) => option.group === props.group),
);
const form = useForm({
    variant: '',
    token: props.care.token,
    items: {} as CareItemSelection,
});
const selected = computed(() =>
    options.value.find((option) => option.id === form.variant),
);
const {
    items,
    cursors: itemCursors,
    failed: itemsError,
    loading: itemsLoading,
    itemsFor,
    load: loadItems,
    cancel: cancelItems,
} = useCareItems(
    () => selected.value,
    () => form.items,
);
const selectedItems = computed(() =>
    items.value.filter((item) => Object.values(form.items).includes(item.id)),
);
const missingItems = computed(
    () =>
        selected.value?.requirements.some(
            (category) =>
                !itemsFor(category).some(
                    (item) => item.id === form.items[category],
                ),
        ) ?? true,
);
const action = computed(() =>
    careActions.find((action) => action.group === props.group),
);
const groupLabel = computed(() => action.value?.label ?? 'Training');
const groupIcon = computed(() => action.value?.icon ?? Dumbbell);
const groupTone = computed(() => action.value?.tone ?? 'blue');
const error = computed(() => Object.values(form.errors).join(' '));
const startReason = computed(() => {
    if (!selected.value) return t('No options are available yet.');
    if (props.care.blocked)
        return t('Care is unavailable for this dog or account.');
    if (props.care.working) return t('Your dog is working.');
    if (props.care.busy) return t(props.busyMessage);
    const remaining =
        Math.max(
            0,
            Math.ceil(
                (Date.parse(props.care.cooldowns[props.group] ?? '') -
                    props.now) /
                    1000,
            ),
        ) || 0;
    if (remaining)
        return t('Available in {time}', {
            time:
                Math.floor(remaining / 60) +
                ':' +
                String(remaining % 60).padStart(2, '0'),
        });
    const reason = careOptionReason(
        selected.value,
        props.pet.energy.value,
        careModifiers(props.care, props.now).energy_cost_percent ?? 0,
    );
    if (reason) return t(reason);
    if (itemsLoading.value) return t('Loading supplies...');
    if (itemsError.value) return t('Could not load data. Please retry.');
    if (missingItems.value)
        return t(
            'Required supplies: {items}. Choose an item or visit the shop.',
            {
                items: selected.value.requirements
                    .filter(
                        (category) =>
                            !itemsFor(category).some(
                                (item) => item.id === form.items[category],
                            ),
                    )
                    .map((category) => t(categories[category]))
                    .join(', '),
            },
        );
    return null;
});
const unavailable = computed(() => startReason.value !== null);
function chooseVariant(variant: string): void {
    form.variant = variant;
    form.items = {};
    form.clearErrors();
    void loadItems();
}
watch(
    () => [open.value, props.group, props.variant] as const,
    ([isOpen]) => {
        if (!isOpen) {
            cancelItems();
            return;
        }
        form.token = props.care.token;
        chooseVariant(props.variant);
    },
);
onUnmounted(() => form.cancel());
function start(): void {
    if (unavailable.value || form.processing) return;
    form.transform((data) => ({
        ...data,
        items: Object.fromEntries(
            Object.entries(data.items).filter(([, value]) => value != null),
        ),
    })).post(store.url(props.pet.id), {
        preserveScroll: true,
        only: ['pet', 'care'],
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="pet-care-dialog">
            <DialogHeader class="pet-care-heading">
                <span
                    class="pet-care-heading-icon"
                    :class="'pet-tone-' + groupTone"
                    ><component :is="groupIcon" :size="24" aria-hidden="true"
                /></span>
                <div>
                    <DialogTitle
                        >{{ t(groupLabel) }} · {{ pet.name }}</DialogTitle
                    >
                    <DialogDescription>{{
                        t('Choose what suits your dog today.')
                    }}</DialogDescription>
                </div>
            </DialogHeader>
            <form class="pet-care-form" @submit.prevent="start">
                <fieldset class="pet-care-choices" :disabled="form.processing">
                    <legend class="sr-only">
                        {{
                            t(
                                trainingOnly
                                    ? 'Training options'
                                    : 'Care options',
                            )
                        }}
                    </legend>
                    <label
                        v-for="option in options"
                        :key="option.id"
                        class="pet-care-choice"
                    >
                        <input
                            type="radio"
                            :name="id + '-variant'"
                            :value="option.id"
                            :checked="form.variant === option.id"
                            @change="chooseVariant(option.id)"
                        />
                        <span>
                            <strong>{{ t(option.label) }}</strong>
                            <small v-if="option.group === 'sleep'">{{
                                t('Up to +{amount}% energy', {
                                    amount: number(option.effects.energy ?? 0),
                                })
                            }}</small>
                            <small>{{
                                option.requirements.length
                                    ? option.requirements
                                          .map((category) =>
                                              t(categories[category]),
                                          )
                                          .join(' + ')
                                    : t('No items needed')
                            }}</small>
                            <span class="pet-care-choice-time"
                                ><Clock3 :size="13" aria-hidden="true" />{{
                                    duration(option.duration)
                                }}</span
                            >
                        </span>
                    </label>
                </fieldset>
                <ActionHint v-if="!selected" :message="startReason" />
                <template v-if="selected">
                    <PetCareSupplies
                        :option="selected"
                        v-model="form.items"
                        :selected-items="selectedItems"
                        :items-for="itemsFor"
                        :cursors="itemCursors"
                        :loading="itemsLoading"
                        :failed="itemsError"
                        :processing="form.processing"
                        :missing-items="missingItems"
                        @reload="loadItems()"
                        @load-more="loadItems"
                    />
                    <PetCarePreview
                        :pet="pet"
                        :care="care"
                        :option="selected"
                        :items="selectedItems"
                        :now="now"
                        :training-only="trainingOnly"
                    />
                    <div class="pet-care-timing">
                        <div>
                            <Clock3 :size="16" aria-hidden="true" /><span
                                >{{ t('Duration')
                                }}<strong>{{
                                    duration(selected.duration)
                                }}</strong></span
                            >
                        </div>
                        <div>
                            <RotateCcw :size="16" aria-hidden="true" /><span
                                >{{ t('Rest after action')
                                }}<strong>{{
                                    duration(selected.cooldown)
                                }}</strong></span
                            >
                        </div>
                    </div>
                    <p class="pet-care-hint">
                        {{
                            t(
                                trainingOnly
                                    ? 'All training sessions share the same rest period.'
                                    : 'The rest period applies to both options in this group.',
                            )
                        }}
                    </p>
                    <ActionHint
                        :id="id + '-start-reason'"
                        :message="startReason"
                    >
                        <Link
                            v-if="missingItems && !itemsLoading && !itemsError"
                            :href="shop()"
                            class="text-link"
                            >{{ t('Visit the shop') }}</Link
                        >
                        <Link
                            v-else-if="care.working"
                            :href="dogWork({ query: { pet: pet.id } })"
                            class="text-link"
                            >{{ t('Open the job board') }}</Link
                        >
                    </ActionHint>
                    <slot name="completion" />
                    <InputError :message="error" />
                    <div class="pet-care-footer">
                        <p>
                            {{
                                t(
                                    'Costs now. Results apply automatically when the timer ends.',
                                )
                            }}
                        </p>
                        <Button
                            type="submit"
                            :disabled="unavailable || form.processing"
                            :aria-describedby="
                                startReason ? id + '-start-reason' : undefined
                            "
                            :aria-busy="form.processing"
                            >{{
                                form.processing
                                    ? t('Starting...')
                                    : t('Start · {time}', {
                                          time: duration(selected.duration),
                                      })
                            }}</Button
                        >
                    </div>
                </template>
            </form>
        </DialogContent>
    </Dialog>
</template>
