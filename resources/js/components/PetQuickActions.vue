<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
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
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, useId, watch } from 'vue';
import ItemBonuses from '@/components/ItemBonuses.vue';
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
import { stateLabels } from '@/lib/petLabels';
import { store, complete } from '@/routes/pets/care';
import { index as shop } from '@/routes/shop';
import type { DogState, PlayerPet } from '@/types/pet';
import type {
    CareGroup,
    PetCare,
    ItemRisk,
    StatusEffect,
} from '@/types/pet-care';

const props = defineProps<{ pet: PlayerPet; care: PetCare }>();
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
const selected = computed(() =>
    options.value.find((option) => option.id === form.variant),
);
const groupLabel = computed(
    () =>
        actions.find((action) => action.group === group.value)?.label ??
        'Quick actions',
);
const groupIcon = computed(
    () => actions.find((action) => action.group === group.value)?.icon,
);
const groupTone = computed(
    () => actions.find((action) => action.group === group.value)?.tone,
);
const itemsFor = (
    category: string,
    uses = selected.value?.uses[category] ?? 1,
) =>
    props.care.items.filter(
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
    props.care.items.filter((item) =>
        Object.values(form.items).includes(item.id),
    ),
);
const grantedEffects = computed(() => {
    const combined = new Map<string, StatusEffect>();
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
const risks = computed(() => {
    const combined = new Map<string, ItemRisk>();
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
const energyCost = computed(() => {
    const base = selected.value?.baseEnergy ?? 0;
    return base === 0
        ? 0
        : Math.max(
              1,
              Math.ceil(
                  (base * (100 + liveModifiers.value.energy_cost_percent)) /
                      100,
              ),
          );
});
const selectedReason = computed(() => {
    const reason = selected.value?.reason;
    if (reason && reason !== 'Not enough energy. Let your dog rest first.')
        return reason;
    return props.pet.energy.value < energyCost.value
        ? 'Not enough energy. Let your dog rest first.'
        : null;
});
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
const unavailable = computed(
    () =>
        props.care.blocked ||
        props.care.busy ||
        cooldown.value > 0 ||
        !!selectedReason.value ||
        missingItems.value,
);
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

watch(
    () =>
        mounted.value && !props.care.blocked && readyToFinish.value
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
    const option = props.care.options.find((entry) => entry.id === variant);
    for (const category of option?.requirements ?? []) {
        const first = itemsFor(category, option?.uses[category])[0];
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
                    (category) =>
                        itemsFor(category, option.uses[category]).length > 0,
                ),
        ) ?? options.value[0];
    if (first) chooseVariant(first.id);
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
        onFinish: () => {
            completionFailed.value = props.care.active?.token === token;
        },
    });
}
</script>

<template>
    <SurfaceCard id="pet-care" class="pet-care" :title="t('Quick actions')">
        <section
            v-if="care.recentIncidents.length"
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
                            t('After using {item} (quality {quality}/10).', {
                                item: localized(incident.item_name),
                                quality: number(incident.quality),
                            })
                        }}
                    </p>
                    <p>{{ localized(incident.effect.description) }}</p>
                </div>
            </article>
        </section>
        <div class="pet-care-actions">
            <Button
                v-for="action in actions"
                :key="action.group"
                type="button"
                variant="plain"
                class="pet-care-action"
                :class="'pet-tone-' + action.tone"
                :disabled="care.blocked"
                @click="choose(action.group)"
            >
                <span
                    ><component :is="action.icon" :size="24" aria-hidden="true"
                /></span>
                {{ t(action.label) }}
                <small v-if="secondsLeft(care.cooldowns[action.group])">{{
                    countdown(secondsLeft(care.cooldowns[action.group]))
                }}</small>
            </Button>
        </div>
        <div v-if="care.active" class="pet-care-progress">
            <div class="pet-care-progress-heading">
                <strong>{{ t(care.active.label) }}</strong>
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
                    <template v-if="selected">
                        <section
                            v-if="
                                selected.requirements.length ||
                                selected.optional.length
                            "
                            class="pet-care-supplies"
                            :aria-label="t('Supplies')"
                        >
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
                                <p v-else class="pet-care-missing">
                                    {{
                                        t('No suitable item in your inventory.')
                                    }}
                                </p>
                            </div>
                            <ItemBonuses
                                v-for="item in selectedItems"
                                :key="item.id"
                                :bonuses="item.bonuses"
                                :effects="[]"
                            />
                            <div
                                v-if="missingItems"
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
                            <p v-else>
                                {{ t('These needs are already full.') }}
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
                        <ItemRisks :risks="risks" />
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
                                    'The rest period applies to both options in this group.',
                                )
                            }}
                        </p>
                        <div
                            v-if="care.busy || cooldown || selectedReason"
                            class="pet-care-warning"
                            role="status"
                        >
                            <Clock3
                                v-if="cooldown"
                                :size="16"
                                aria-hidden="true"
                            />
                            <p>
                                {{
                                    care.busy
                                        ? t(busyMessage)
                                        : cooldown
                                          ? t('Available in {time}', {
                                                time: countdown(cooldown),
                                            })
                                          : t(selectedReason ?? '')
                                }}
                            </p>
                        </div>
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
