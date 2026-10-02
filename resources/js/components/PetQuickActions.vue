<script setup lang="ts">
import { Link, useForm, useHttp } from '@inertiajs/vue3';
import {
    Brush,
    CircleDot,
    Footprints,
    Moon,
    Soup,
    Clock3,
    RotateCcw,
    Package,
    Check,
    ShoppingBag,
    Zap,
    Dumbbell,
    ChevronRight,
    Heart,
    Droplets,
    HandHeart,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, useId, watch } from 'vue';
import ItemBonuses from '@/components/ItemBonuses.vue';
import ActionHint from '@/components/ActionHint.vue';
import ItemRisks from '@/components/ItemRisks.vue';
import StatusEffects from '@/components/StatusEffects.vue';
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
import { stateLabels, statLabels } from '@/lib/petLabels';
import { careEnergyCost, careOptionReason } from '@/lib/petCareAvailability';
import { store, complete } from '@/routes/pets/care';
import { careItems } from '@/routes';
import { index as shop } from '@/routes/shop';
import { index as inventory } from '@/routes/inventory';
import { index as dogWork } from '@/routes/dog-work';
import type { DogState, DogStat, PlayerPet } from '@/types/pet';
import type {
    CareGroup,
    PetCare,
    ItemRisk,
    StatusEffect,
    CareItem,
    CareOption,
} from '@/types/pet-care';

const props = withDefaults(
    defineProps<{ pet: PlayerPet; care: PetCare; trainingOnly?: boolean }>(),
    { trainingOnly: false },
);
const { t, number, locale } = useI18n();
const localized = (value: Record<string, string>) =>
    value[locale.value] ?? value.en ?? '';
const eventDate = (value: string) =>
    new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
const id = useId();
const open = ref(false);
const items = ref<CareItem[]>([]);
const itemCursors = ref<Record<string, string | null>>({});
const itemsError = ref(false);
const itemRequest = useHttp<
    Record<string, never>,
    { items: CareItem[]; nextCursor: string | null }
>({});
let itemsGeneration = 0;
const group = ref<CareGroup>('feed');
const now = ref(Date.parse(props.care.serverNow));
const mounted = ref(false);
const completionFailed = ref(false);
let lastCompletionAttempt: string | null = null;
let clock: ReturnType<typeof setInterval> | undefined;
let serverAnchor = now.value;
let localAnchor = Date.now();
onMounted(() => {
    mounted.value = true;
    clock = setInterval(() => {
        now.value = serverAnchor + Date.now() - localAnchor;
    }, 1000);
});
onUnmounted(() => {
    clearInterval(clock);
    finish.cancel();
    itemsGeneration++;
    itemRequest.cancel();
});
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
    clothing: 'Clothing',
    sports: 'Sports equipment',
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
const trainingOptions = computed(() =>
    props.care.options.filter((option) => option.group === 'training'),
);
const statGains = computed(() => {
    const equipment = selectedItems.value.find(
        (item) => item.category === 'sports',
    );
    if (!equipment) return [];
    return Object.entries(
        selected.value?.gainsByQuality[equipment.quality] ?? {},
    ).map(([stat, gain]) => ({ label: statLabels[stat as DogStat], gain }));
});
const selected = computed(() =>
    options.value.find((option) => option.id === form.variant),
);
const groupLabel = computed(
    () =>
        actions.find((action) => action.group === group.value)?.label ??
        (props.trainingOnly ? 'Training' : 'Quick actions'),
);
const groupIcon = computed(
    () =>
        actions.find((action) => action.group === group.value)?.icon ??
        Dumbbell,
);
const groupTone = computed(
    () =>
        actions.find((action) => action.group === group.value)?.tone ?? 'blue',
);
const itemsFor = (
    category: string,
    uses = selected.value?.uses[category] ?? 1,
) =>
    items.value.filter(
        (item) => item.category === category && item.remainingUses >= uses,
    );
const secondsLeft = (date?: string) =>
    date ? Math.max(0, Math.ceil((Date.parse(date) - now.value) / 1000)) : 0;
const duration = (seconds: number) => {
    if (seconds < 60) return t('{count} sec', { count: seconds });
    const minutes = Math.floor(seconds / 60);
    return seconds % 60 === 0
        ? t('{count} min', { count: minutes })
        : `${t('{count} min', { count: minutes })} ${t('{count} sec', { count: seconds % 60 })}`;
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
const liveModifiers = computed(() => {
    const result: Record<string, number> = Object.fromEntries(
        props.care.modifierKeys.map((key) => [key, 0]),
    );
    for (const effect of [...props.care.buffs, ...props.care.debuffs]) {
        if (effect.expires_at && effect.expires_at * 1000 <= now.value)
            continue;
        for (const key of props.care.modifierKeys)
            result[key] += effect.modifiers[key] ?? 0;
    }
    for (const key of props.care.modifierKeys)
        result[key] = Math.max(-50, Math.min(50, result[key]));
    return result;
});
const selectedItems = computed(() =>
    items.value.filter((item) => Object.values(form.items).includes(item.id)),
);
const grantedEffects = computed(() => {
    const combined = new Map<string, StatusEffect>(
        (selected.value?.grantedEffects ?? []).map((effect) => [
            effect.code,
            effect,
        ]),
    );
    for (const item of selectedItems.value) {
        for (const effect of item.grantedEffects) {
            if (
                (effect.duration_seconds ?? 0) >
                (combined.get(effect.code)?.duration_seconds ?? 0)
            )
                combined.set(effect.code, effect);
        }
    }
    return [...combined.values()];
});
const recoveryEffects = computed(() =>
    props.care.debuffs.filter(
        (effect) =>
            (effect.expires_at ?? 0) * 1000 > now.value &&
            (selected.value?.statusRecovery[effect.code] ?? 0) > 0,
    ),
);
const risks = computed(() => {
    const combined = new Map<string, ItemRisk>(
        (selected.value?.risks ?? []).map((risk) => [risk.effect.code, risk]),
    );
    for (const item of selectedItems.value) {
        for (const risk of item.risks) {
            if (
                !grantedEffects.value.some(
                    (effect) => effect.code === risk.effect.code,
                ) &&
                (risk.chance > (combined.get(risk.effect.code)?.chance ?? 0) ||
                    (risk.chance === combined.get(risk.effect.code)?.chance &&
                        (risk.effect.duration_seconds ?? 0) >
                            (combined.get(risk.effect.code)?.effect
                                .duration_seconds ?? 0)))
            )
                combined.set(risk.effect.code, risk);
        }
    }
    return [...combined.values()];
});
const energyCost = computed(() =>
    careEnergyCost(
        selected.value?.baseEnergy ?? 0,
        liveModifiers.value.energy_cost_percent,
    ),
);
const optionReason = (option: CareOption) =>
    careOptionReason(
        option,
        props.pet.energy.value,
        liveModifiers.value.energy_cost_percent,
    );
const selectedReason = computed(() =>
    selected.value ? optionReason(selected.value) : null,
);
const energyCostPercentage = computed(() =>
    props.pet.energy.maximum > 0
        ? (energyCost.value / props.pet.energy.maximum) * 100
        : 0,
);
const energyAfterCost = computed(() =>
    Math.max(0, props.pet.states.energy - energyCostPercentage.value),
);
const energyGainCapped = computed(
    () => (selected.value?.effects.energy ?? 0) > 100 - energyAfterCost.value,
);
const effects = computed(() => {
    const result = { ...selected.value?.effects };
    for (const item of selectedItems.value) {
        for (const [state, bonus] of Object.entries(item?.bonus ?? {})) {
            const key = state as DogState;
            result[key] = (result[key] ?? 0) + bonus;
        }
    }
    const itemBonuses: Partial<Record<DogState, number>> = {};
    for (const item of selectedItems.value) {
        for (const [state, bonus] of Object.entries(item.bonuses)) {
            const key = state as DogState;
            itemBonuses[key] = (itemBonuses[key] ?? 0) + bonus;
        }
    }
    for (const [state, bonus] of Object.entries(itemBonuses)) {
        const key = state as DogState;
        result[key] = (result[key] ?? 0) + Math.max(0, Math.min(30, bonus));
    }
    for (const [state, value] of Object.entries(result)) {
        const modifier =
            liveModifiers.value[
                state + (value >= 0 ? '_gain_percent' : '_loss_percent')
            ] ?? 0;
        result[state as DogState] =
            Math.round(((value * (100 + modifier)) / 100) * 10000) / 10000;
    }
    return Object.entries(result).map(([state, amount]) => {
        const current =
            state === 'energy'
                ? energyAfterCost.value
                : props.pet.states[state as DogState];
        return {
            label: stateLabels[state as DogState],
            amount:
                Math.round(
                    Math.max(-current, Math.min(100 - current, amount)) * 10,
                ) / 10,
        };
    });
});
const benefits = computed(() =>
    effects.value.filter((effect) => effect.amount > 0),
);
const costs = computed(() =>
    effects.value.filter((effect) => effect.amount < 0),
);
const progress = computed(() => {
    const active = props.care.active;
    if (!active) return 0;
    const elapsed = now.value - Date.parse(active.startedAt);
    const total = Date.parse(active.endsAt) - Date.parse(active.startedAt);
    return total <= 0
        ? 100
        : Math.max(0, Math.min(100, (elapsed / total) * 100));
});
const readyToFinish = computed(
    () =>
        props.care.active !== null &&
        secondsLeft(props.care.active.endsAt) === 0,
);
const busyMessage = computed(() => {
    if (completionFailed.value)
        return 'Could not apply the result. Please retry.';
    return readyToFinish.value
        ? 'Applying the activity result...'
        : 'Your dog is busy with another activity.';
});
function groupReason(value: CareGroup, option?: CareOption): string | null {
    if (props.care.blocked)
        return t('Care is unavailable for this dog or account.');
    if (props.care.working) return t('Your dog is working.');
    if (props.care.busy) return t(busyMessage.value);
    const remaining = secondsLeft(props.care.cooldowns[value]);
    if (remaining)
        return t('Available in {time}', { time: countdown(remaining) });
    if (option) return optionReason(option) ? t(optionReason(option)!) : null;
    const variants = props.care.options.filter((item) => item.group === value);
    if (!variants.length) return t('No options are available yet.');
    return variants.every((item) => optionReason(item))
        ? t(optionReason(variants[0])!)
        : null;
}
const startReason = computed(() => {
    if (!selected.value) return t('No options are available yet.');
    if (props.care.blocked)
        return t('Care is unavailable for this dog or account.');
    if (props.care.working) return t('Your dog is working.');
    if (props.care.busy) return t(busyMessage.value);
    if (cooldown.value)
        return t('Available in {time}', { time: countdown(cooldown.value) });
    if (selectedReason.value) return t(selectedReason.value);
    if (itemRequest.processing) return t('Loading supplies...');
    if (itemsError.value) return t('Could not load data. Please retry.');
    if (missingItems.value) {
        const missing =
            selected.value?.requirements.filter(
                (category) =>
                    !itemsFor(category).some(
                        (item) => item.id === form.items[category],
                    ),
            ) ?? [];
        return t(
            'Required supplies: {items}. Choose an item or visit the shop.',
            {
                items: missing
                    .map((category) => t(categories[category]))
                    .join(', '),
            },
        );
    }
    return null;
});
const unavailable = computed(() => startReason.value !== null);
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

watch(
    () =>
        mounted.value &&
        !props.trainingOnly &&
        !props.care.blocked &&
        readyToFinish.value
            ? props.care.active?.token
            : null,
    (token) => {
        if (token && token !== lastCompletionAttempt) finishActivity();
    },
);

function chooseVariant(variant: string) {
    form.variant = variant;
    form.items = {};
    form.clearErrors();
    void loadItems();
}

async function loadItems(category?: string) {
    const generation = ++itemsGeneration;
    itemRequest.cancel();
    itemsError.value = false;
    const option = selected.value;
    if (!option) return;
    if (!category) {
        items.value = [];
        itemCursors.value = {};
    }
    try {
        for (const key of category
            ? [category]
            : [...option.requirements, ...option.optional]) {
            const response = await itemRequest.get(
                careItems.url({
                    query: {
                        category: key,
                        uses: option.uses[key] ?? 1,
                        cursor: category ? itemCursors.value[key] : undefined,
                    },
                }),
            );
            if (generation !== itemsGeneration) return;
            const known = new Set(items.value.map((item) => item.id));
            items.value.push(
                ...response.items.filter((item) => !known.has(item.id)),
            );
            itemCursors.value[key] = response.nextCursor;
            if (
                option.requirements.includes(key) &&
                !form.items[key] &&
                response.items[0]
            ) {
                form.items[key] = response.items[0].id;
            }
        }
    } catch {
        if (generation === itemsGeneration) itemsError.value = true;
    }
}

watch(open, (value) => {
    if (!value) {
        itemsGeneration++;
        itemRequest.cancel();
    }
});

function choose(value: CareGroup, variant?: string) {
    group.value = value;
    const first =
        options.value.find((option) => !optionReason(option)) ??
        options.value[0];
    form.variant = '';
    if (first) chooseVariant(variant ?? first.id);
    form.token = props.care.token;
    open.value = true;
}

function start() {
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

function finishActivity() {
    if (
        !props.care.active ||
        !readyToFinish.value ||
        props.care.blocked ||
        finish.processing
    )
        return;
    const token = props.care.active.token;
    lastCompletionAttempt = token;
    completionFailed.value = false;
    finish.token = token;
    finish.post(complete.url(props.pet.id), {
        preserveScroll: true,
        only: ['pet', 'care'],
        onFinish: () => {
            completionFailed.value = props.care.active?.token === token;
        },
    });
}
</script>

<template>
    <SurfaceCard
        :id="trainingOnly ? 'pet-training' : 'pet-care'"
        class="pet-care"
        :title="t(trainingOnly ? 'Training' : 'Quick actions')"
    >
        <template v-if="!trainingOnly" #header>
            <div class="pet-care-title-row">
                <div class="pet-care-title">
                    <span class="pet-care-title-icon"
                        ><HandHeart :size="22" aria-hidden="true"
                    /></span>
                    <div>
                        <h2>{{ t('Time together') }}</h2>
                        <p>
                            {{
                                t('Choose a little adventure for your friend.')
                            }}
                        </p>
                    </div>
                </div>
                <Link :href="inventory()" class="pet-care-inventory"
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
        <section
            v-if="!trainingOnly && care.recentIncidents.length"
            class="pet-care-incidents"
            aria-live="polite"
            :aria-label="t('Recent events')"
        >
            <h3>{{ t('Recent events') }}</h3>
            <article
                v-for="event in care.recentIncidents"
                :key="event.id"
                class="pet-status pet-status-negative"
            >
                <time :datetime="event.occurredAt">{{
                    eventDate(event.occurredAt)
                }}</time>
                <div
                    v-for="incident in event.incidents"
                    :key="incident.effect.code"
                >
                    <strong>{{ localized(incident.effect.name) }}</strong>
                    <p>
                        {{
                            t(
                                incident.quality === 0
                                    ? 'During {item}.'
                                    : 'After using {item} (quality {quality}/10).',
                                {
                                    item: localized(incident.item_name),
                                    quality: number(incident.quality),
                                },
                            )
                        }}
                    </p>
                    <p>{{ localized(incident.effect.description) }}</p>
                </div>
            </article>
        </section>
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
                :aria-describedby="id + '-action-' + action.group"
                @click="choose(action.group)"
            >
                <span class="pet-care-action-icon"
                    ><component :is="action.icon" :size="24" aria-hidden="true"
                /></span>
                <span class="pet-care-action-copy">
                    <strong>{{ t(action.label) }}</strong>
                    <span
                        :id="id + '-action-' + action.group"
                        class="pet-action-reason"
                    >
                        {{ actionStatus(action.group) }}
                        <span
                            v-if="groupReason(action.group)"
                            class="sr-only"
                            >{{ groupReason(action.group) }}</span
                        >
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
        <div v-if="care.active" class="pet-care-progress">
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
        <p v-else-if="care.busy" class="pet-care-notice">
            {{ t('Your dog is busy with another activity.') }}
        </p>
        <p v-else-if="care.blocked" class="pet-care-notice">
            {{ t('Care is unavailable for this dog or account.') }}
        </p>
        <p v-else-if="!trainingOnly" class="pet-feature-note">
            {{ t('Choose an action to compare its options.') }}
        </p>
        <p v-if="trainingOnly" class="pet-training-footnote">
            {{
                t(
                    'One equipment charge per session. Gains depend on quality, mood and bond.',
                )
            }}
        </p>

        <Dialog v-model:open="open">
            <DialogContent class="pet-care-dialog">
                <DialogHeader class="pet-care-heading">
                    <span
                        class="pet-care-heading-icon"
                        :class="'pet-tone-' + groupTone"
                        ><component
                            :is="groupIcon"
                            :size="24"
                            aria-hidden="true"
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
                    <fieldset
                        class="pet-care-choices"
                        :disabled="form.processing"
                    >
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
                                        amount: number(
                                            option.effects.energy ?? 0,
                                        ),
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
                        <section
                            v-if="
                                selected.requirements.length ||
                                selected.optional.length
                            "
                            class="pet-care-supplies"
                            :aria-label="t('Supplies')"
                        >
                            <p
                                v-if="itemRequest.processing"
                                class="dashboard-loading"
                                role="status"
                            >
                                {{ t('Loading...') }}
                            </p>
                            <div v-if="itemsError" role="alert">
                                <p>
                                    {{
                                        t('Could not load data. Please retry.')
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    :disabled="itemRequest.processing"
                                    @click="loadItems()"
                                    >{{ t('Retry') }}</Button
                                >
                            </div>
                            <div
                                v-for="category in [
                                    ...selected.requirements,
                                    ...selected.optional,
                                ]"
                                :key="category"
                                class="pet-care-item-field"
                            >
                                <div class="pet-care-item-heading">
                                    <label
                                        :for="
                                            itemsFor(category).length
                                                ? id + '-' + category
                                                : undefined
                                        "
                                        ><Package
                                            :size="15"
                                            aria-hidden="true"
                                        />{{ t(categories[category]) }}</label
                                    >
                                    <span>{{
                                        t('Uses per action: {count}', {
                                            count: selected.uses[category] ?? 1,
                                        })
                                    }}</span>
                                </div>
                                <select
                                    v-if="itemsFor(category).length"
                                    :id="id + '-' + category"
                                    v-model="form.items[category]"
                                    :disabled="form.processing"
                                >
                                    <option
                                        v-if="
                                            selected.optional.includes(category)
                                        "
                                        :value="undefined"
                                    >
                                        {{ t('Do not use (optional)') }}
                                    </option>
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
                                <p
                                    v-else-if="
                                        !itemRequest.processing && !itemsError
                                    "
                                    class="pet-care-missing"
                                >
                                    {{
                                        t('No suitable item in your inventory.')
                                    }}
                                </p>
                                <Button
                                    v-if="itemCursors[category]"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        itemRequest.processing ||
                                        form.processing
                                    "
                                    @click="loadItems(category)"
                                    >{{ t('Load more') }}</Button
                                >
                            </div>
                            <ItemBonuses
                                v-for="item in selectedItems"
                                :key="item.id"
                                :bonuses="item.bonuses"
                                :effects="[]"
                            />
                            <div
                                v-if="
                                    missingItems &&
                                    !itemRequest.processing &&
                                    !itemsError
                                "
                                class="pet-care-supply-help"
                            >
                                <p>
                                    {{
                                        t(
                                            'Choose another option or get supplies.',
                                        )
                                    }}
                                </p>
                                <Button as-child variant="outline" size="sm"
                                    ><Link :href="shop()"
                                        ><ShoppingBag
                                            :size="15"
                                            aria-hidden="true"
                                        />{{ t('Go to shop') }}</Link
                                    ></Button
                                >
                            </div>
                        </section>
                        <section
                            class="pet-care-preview"
                            :aria-label="t('Result')"
                        >
                            <h3>{{ t('Your dog will get') }}</h3>
                            <div
                                v-if="benefits.length"
                                class="pet-care-benefits"
                            >
                                <div
                                    v-for="effect in benefits"
                                    :key="effect.label"
                                    class="pet-care-benefit"
                                >
                                    <strong>+{{ effect.amount }}%</strong
                                    ><span>{{ t(effect.label) }}</span>
                                </div>
                            </div>
                            <p v-else-if="!statGains.length && !trainingOnly">
                                {{ t('These needs are already full.') }}
                            </p>
                            <p v-if="trainingOnly && !statGains.length">
                                {{
                                    t(
                                        'Select equipment to preview attribute gains.',
                                    )
                                }}
                            </p>
                            <div
                                v-if="statGains.length"
                                class="pet-care-benefits"
                            >
                                <div
                                    v-for="stat in statGains"
                                    :key="stat.label"
                                    class="pet-care-benefit"
                                >
                                    <strong
                                        >+{{ number(stat.gain ?? 0) }}</strong
                                    ><span>{{ t(stat.label) }}</span>
                                </div>
                            </div>
                            <p v-if="statGains.length" class="pet-care-hint">
                                {{
                                    t(
                                        'Preview for the selected equipment and current mood and bond. Gains are capped by genetic potential.',
                                    )
                                }}
                            </p>
                            <p v-if="energyGainCapped">
                                {{
                                    t(
                                        'Energy is capped at 100%. The result shows what your dog needs now.',
                                    )
                                }}
                            </p>
                            <div
                                v-if="costs.length || selected.energy"
                                class="pet-care-costs"
                            >
                                <span v-if="selected.energy"
                                    ><Zap :size="14" aria-hidden="true" />{{
                                        t('Energy cost')
                                    }}: −{{
                                        number(energyCostPercentage)
                                    }}%</span
                                >
                                <span
                                    v-for="effect in costs"
                                    :key="effect.label"
                                    >{{ t(effect.label) }}
                                    {{ effect.amount }}%</span
                                >
                            </div>
                        </section>
                        <StatusEffects :effects="grantedEffects" preview />
                        <p
                            v-for="effect in recoveryEffects"
                            :key="effect.code"
                            class="pet-care-hint"
                        >
                            {{
                                t(
                                    'Recovery: {effect} lasts {count} min less after completion.',
                                    {
                                        effect: localized(effect.name),
                                        count: number(
                                            Math.ceil(
                                                (selected.statusRecovery[
                                                    effect.code
                                                ] ?? 0) / 60,
                                            ),
                                        ),
                                    },
                                )
                            }}
                        </p>
                        <ItemRisks
                            :risks="risks"
                            :includes-training="trainingOnly"
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
                                v-if="
                                    missingItems &&
                                    !itemRequest.processing &&
                                    !itemsError
                                "
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
                        <Button
                            v-if="readyToFinish && completionFailed"
                            type="button"
                            :disabled="finish.processing || care.blocked"
                            @click="finishActivity"
                            >{{ t('Retry completion') }}</Button
                        >
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
                                    startReason
                                        ? id + '-start-reason'
                                        : undefined
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
    </SurfaceCard>
</template>
