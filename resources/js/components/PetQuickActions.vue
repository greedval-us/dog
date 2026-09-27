<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Brush, CircleDot, Footprints, Moon, Soup } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, useId, watch } from 'vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { stateLabels } from '@/lib/petLabels';
import { store, complete } from '@/routes/pets/care';
import { index as shop } from '@/routes/shop';
import type { DogState, PlayerPet } from '@/types/pet';
import type { CareGroup, PetCare } from '@/types/pet-care';

const props = defineProps<{ pet: PlayerPet; care: PetCare }>();
const { t } = useI18n();
const id = useId();
const open = ref(false);
const group = ref<CareGroup>('feed');
const now = ref(Date.parse(props.care.serverNow));
let clock: ReturnType<typeof setInterval> | undefined;
let serverAnchor = now.value;
let localAnchor = Date.now();
onMounted(() => {
    clock = setInterval(() => {
        now.value = serverAnchor + Date.now() - localAnchor;
    }, 1000);
});
onUnmounted(() => clearInterval(clock));
watch(
    () => props.care.serverNow,
    (value) => {
        serverAnchor = Date.parse(value);
        localAnchor = Date.now();
        now.value = serverAnchor;
    },
);

const actions = [
    { group: 'feed', label: 'Feed', icon: Soup, tone: 'sage' },
    { group: 'walk', label: 'Walk', icon: Footprints, tone: 'blue' },
    { group: 'play', label: 'Play', icon: CircleDot, tone: 'amber' },
    { group: 'groom', label: 'Groom', icon: Brush, tone: 'blue' },
    { group: 'sleep', label: 'Sleep', icon: Moon, tone: 'violet' },
] as const;
const categories: Record<string, string> = {
    food: 'Food',
    collars: 'Collar',
    leashes: 'Leash',
    toys: 'Toy',
    care: 'Care product',
};
const form = useForm({
    variant: '',
    token: props.care.token,
    items: {} as Record<string, number>,
});
const finish = useForm({ token: '' });
const options = computed(() =>
    props.care.options.filter((option) => option.group === group.value),
);
const selected = computed(() =>
    options.value.find((option) => option.id === form.variant),
);
const groupLabel = computed(
    () =>
        actions.find((action) => action.group === group.value)?.label ??
        'Quick actions',
);
const itemsFor = (category: string) =>
    props.care.items.filter((item) => item.category === category);
const secondsLeft = (date?: string) =>
    date ? Math.max(0, Math.ceil((Date.parse(date) - now.value) / 1000)) : 0;
const duration = (seconds: number) => {
    if (seconds < 60) return t('{count} sec', { count: seconds });
    return t('{count} min', { count: Math.ceil(seconds / 60) });
};
const countdown = (seconds: number) =>
    `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
const cooldown = computed(() => secondsLeft(props.care.cooldowns[group.value]));
const missingItems = computed(
    () =>
        selected.value?.requirements.some(
            (category) =>
                !itemsFor(category).some(
                    (item) => item.id === form.items[category],
                ),
        ) ?? true,
);
const error = computed(() => Object.values(form.errors).join(' '));
const finishError = computed(() => Object.values(finish.errors).join(' '));
const effects = computed(() => {
    const result = { ...selected.value?.effects };
    for (const [category, state] of [
        ['toys', 'mood'],
        ['care', 'cleanliness'],
    ] as const) {
        const item = props.care.items.find(
            (entry) =>
                entry.id === form.items[category] &&
                entry.category === category,
        );
        if (item && result[state] !== undefined)
            result[state] += Math.max(0, Math.min(10, item.quality) - 1);
    }
    return Object.entries(result).map(([state, amount]) => {
        const current = props.pet.states[state as DogState];
        return {
            label: stateLabels[state as DogState],
            amount:
                Math.round(
                    Math.max(-current, Math.min(100 - current, amount)) * 10,
                ) / 10,
        };
    });
});
const unavailable = computed(
    () =>
        props.care.blocked ||
        props.care.busy ||
        cooldown.value > 0 ||
        !!selected.value?.reason ||
        missingItems.value,
);

function chooseVariant(variant: string) {
    form.variant = variant;
    form.items = {};
    form.clearErrors();
    const option = props.care.options.find((entry) => entry.id === variant);
    for (const category of option?.requirements ?? []) {
        const first = itemsFor(category)[0];
        if (first) form.items[category] = first.id;
    }
}

function choose(value: CareGroup) {
    group.value = value;
    const first =
        options.value.find(
            (option) =>
                !option.reason &&
                option.requirements.every(
                    (category) => itemsFor(category).length > 0,
                ),
        ) ?? options.value[0];
    if (first) chooseVariant(first.id);
    form.token = props.care.token;
    open.value = true;
}

function start() {
    if (unavailable.value || form.processing) return;
    form.post(store.url(props.pet.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}

function finishActivity() {
    if (!props.care.active || finish.processing) return;
    finish.token = props.care.active.token;
    finish.post(complete.url(props.pet.id), { preserveScroll: true });
}
</script>

<template>
    <SurfaceCard id="pet-care" class="pet-care" :title="t('Quick actions')">
        <div class="pet-care-actions">
            <Button
                v-for="action in actions"
                :key="action.group"
                type="button"
                variant="plain"
                class="pet-care-action"
                :class="'pet-tone-' + action.tone"
                :disabled="care.blocked || care.busy"
                @click="choose(action.group)"
            >
                <span
                    ><component :is="action.icon" :size="26" aria-hidden="true"
                /></span>
                {{ t(action.label) }}
                <small v-if="secondsLeft(care.cooldowns[action.group])">{{
                    countdown(secondsLeft(care.cooldowns[action.group]))
                }}</small>
            </Button>
        </div>
        <div v-if="care.active" class="pet-care-progress">
            <strong>{{ t(care.active.label) }}</strong>
            <p v-if="secondsLeft(care.active.endsAt)">
                {{
                    t('Finishes in {time}', {
                        time: countdown(secondsLeft(care.active.endsAt)),
                    })
                }}
            </p>
            <p v-else role="status">
                {{ t('Your dog is ready. Finish to apply the result.') }}
            </p>
            <Button
                type="button"
                :disabled="
                    secondsLeft(care.active.endsAt) > 0 || finish.processing
                "
                @click="finishActivity"
            >
                {{ t(finish.processing ? 'Finishing...' : 'Finish activity') }}
            </Button>
            <InputError :message="finishError" />
        </div>
        <p v-else-if="care.busy" class="pet-care-notice">
            {{ t('Your dog is busy with another activity.') }}
        </p>
        <p v-else-if="care.blocked" class="pet-care-notice">
            {{ t('Care is unavailable for this dog or account.') }}
        </p>
        <p v-else class="pet-feature-note">
            {{ t('Choose an action to compare its options.') }}
        </p>

        <Dialog v-model:open="open">
            <DialogContent class="pet-care-dialog">
                <DialogHeader>
                    <DialogTitle
                        >{{ t(groupLabel) }} · {{ pet.name }}</DialogTitle
                    >
                    <DialogDescription>{{
                        t(
                            'Choose how to care for your dog. Items and energy are spent at the start; results arrive when you finish.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <form class="pet-care-form" @submit.prevent="start">
                    <fieldset
                        class="pet-care-choices"
                        :disabled="form.processing"
                    >
                        <legend class="sr-only">{{ t('Care options') }}</legend>
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
                            <span
                                ><strong>{{ t(option.label) }}</strong
                                ><small>{{
                                    t(option.description)
                                }}</small></span
                            >
                        </label>
                    </fieldset>
                    <template v-if="selected">
                        <div
                            v-for="category in selected.requirements"
                            :key="category"
                            class="pet-care-item-field"
                        >
                            <label :for="id + '-' + category">{{
                                t(categories[category])
                            }}</label>
                            <select
                                v-if="itemsFor(category).length"
                                :id="id + '-' + category"
                                v-model="form.items[category]"
                                :disabled="form.processing"
                            >
                                <option
                                    v-for="item in itemsFor(category)"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }} ·
                                    {{
                                        t('{count} uses left', {
                                            count: item.remainingUses,
                                        })
                                    }}
                                    ·
                                    {{
                                        t('Quality {value}', {
                                            value: item.quality,
                                        })
                                    }}
                                </option>
                            </select>
                            <p v-else>
                                {{ t('No suitable item in your inventory.') }}
                            </p>
                            <small v-if="form.items[category]">{{
                                t(
                                    'Consumes 1 use. The item disappears after its last use.',
                                )
                            }}</small>
                        </div>
                        <Button
                            v-if="selected.requirements.length"
                            as-child
                            variant="secondary"
                            ><Link :href="shop()">{{
                                t('Visit the shop')
                            }}</Link></Button
                        >
                        <div class="pet-care-preview">
                            <strong>{{ t('Result on completion') }}</strong>
                            <dl>
                                <div
                                    v-for="effect in effects"
                                    :key="effect.label"
                                >
                                    <dt>{{ t(effect.label) }}</dt>
                                    <dd>
                                        {{ effect.amount > 0 ? '+' : ''
                                        }}{{ effect.amount }}%
                                    </dd>
                                </div>
                                <div v-if="selected.energy">
                                    <dt>{{ t('Energy spent at start') }}</dt>
                                    <dd>−{{ selected.energy }}</dd>
                                </div>
                                <div>
                                    <dt>{{ t('Duration') }}</dt>
                                    <dd>{{ duration(selected.duration) }}</dd>
                                </div>
                                <div>
                                    <dt>{{ t('Cooldown after activity') }}</dt>
                                    <dd>{{ duration(selected.cooldown) }}</dd>
                                </div>
                            </dl>
                            <p>
                                {{
                                    t(
                                        'Variants share a cooldown. Other actions remain available after this activity.',
                                    )
                                }}
                            </p>
                        </div>
                        <p
                            v-if="cooldown"
                            class="pet-care-notice"
                            role="status"
                        >
                            {{
                                t('Available in {time}', {
                                    time: countdown(cooldown),
                                })
                            }}
                        </p>
                        <p v-if="selected.reason" class="pet-care-notice">
                            {{ t(selected.reason) }}
                        </p>
                        <p v-if="missingItems" class="pet-care-notice">
                            {{
                                t(
                                    'Buy the missing items or choose an option without supplies.',
                                )
                            }}
                        </p>
                        <InputError :message="error" />
                        <Button
                            type="submit"
                            :disabled="unavailable || form.processing"
                            >{{
                                t(
                                    form.processing
                                        ? 'Starting...'
                                        : 'Start activity',
                                )
                            }}</Button
                        >
                    </template>
                </form>
            </DialogContent>
        </Dialog>
    </SurfaceCard>
</template>
