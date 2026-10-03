<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { ChevronRight, Clock3, ShieldAlert, Sparkles } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import type { StatusEffect } from '@/types/pet-care';

const props = defineProps<{
    effects: StatusEffect[];
    serverNow?: string;
    preview?: boolean;
    compact?: boolean;
}>();
const { t, locale, number } = useI18n();
const open = ref(false);
const filter = ref<'all' | 'buff' | 'debuff'>('all');
watch(open, (value) => {
    if (value) filter.value = 'all';
});
const now = ref(props.serverNow ? Date.parse(props.serverNow) : Date.now());
let anchor = now.value;
let localAnchor = Date.now();
let timer: ReturnType<typeof setInterval> | undefined;
watch(
    () => props.serverNow,
    (value) => {
        anchor = value ? Date.parse(value) : Date.now();
        localAnchor = Date.now();
        now.value = anchor;
    },
);
onMounted(() => {
    timer = setInterval(() => {
        now.value = anchor + Date.now() - localAnchor;
    }, 1000);
});
onUnmounted(() => clearInterval(timer));
const visible = computed(() =>
    props.effects.filter(
        (effect) =>
            props.preview ||
            !effect.expires_at ||
            effect.expires_at * 1000 > now.value,
    ),
);
const text = (value: Record<string, string>) =>
    value[locale.value] ?? value.en ?? '';
const effectDuration = (effect: StatusEffect) => {
    const minutes = Math.max(
        1,
        Math.ceil(
            (props.preview
                ? (effect.duration_seconds ?? 0)
                : (effect.expires_at ?? 0) - now.value / 1000) / 60,
        ),
    );
    if (minutes < 60) return t('{count} min', { count: number(minutes) });
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return (
        t('{count} h', { count: number(hours) }) +
        (rest ? ` ${t('{count} min', { count: number(rest) })}` : '')
    );
};
const buffs = computed(() =>
    visible.value.filter((effect) => effect.kind === 'buff'),
);
const debuffs = computed(() =>
    visible.value.filter((effect) => effect.kind === 'debuff'),
);
const filters = computed(() => [
    {
        value: 'all' as const,
        label: t('All effects'),
        count: visible.value.length,
    },
    { value: 'buff' as const, label: t('Buffs'), count: buffs.value.length },
    {
        value: 'debuff' as const,
        label: t('Debuffs'),
        count: debuffs.value.length,
    },
]);
const filtered = computed(() =>
    visible.value
        .filter(
            (effect) => filter.value === 'all' || effect.kind === filter.value,
        )
        .sort(
            (a, b) => (a.expires_at ?? Infinity) - (b.expires_at ?? Infinity),
        ),
);
</script>

<template>
    <div v-if="compact || visible.length" :class="{ 'pet-effects': compact }">
        <Dialog v-if="compact" v-model:open="open">
            <DialogTrigger as-child>
                <Button
                    type="button"
                    variant="plain"
                    class="pet-effects-summary"
                    :aria-label="`${t('Pet effects')}: ${t('Buffs')} ${number(buffs.length)}, ${t('Debuffs')} ${number(debuffs.length)}`"
                    :title="t('Pet effects')"
                >
                    <span class="pet-effects-summary-label">{{
                        t('Effects')
                    }}</span>
                    <span class="pet-effects-counts" aria-hidden="true">
                        <span
                            class="pet-effects-count pet-effects-count-positive"
                            :class="{
                                'pet-effects-count-empty': !buffs.length,
                            }"
                        >
                            <Sparkles :size="13" /><b>{{
                                number(buffs.length)
                            }}</b>
                        </span>
                        <span
                            class="pet-effects-count pet-effects-count-negative"
                            :class="{
                                'pet-effects-count-empty': !debuffs.length,
                            }"
                        >
                            <ShieldAlert :size="13" /><b>{{
                                number(debuffs.length)
                            }}</b>
                        </span>
                    </span>
                    <ChevronRight :size="12" aria-hidden="true" />
                </Button>
            </DialogTrigger>
            <DialogContent class="pet-effects-dialog">
                <DialogHeader class="pet-effects-heading">
                    <span class="pet-effects-heading-icon"
                        ><Sparkles :size="22" aria-hidden="true"
                    /></span>
                    <DialogTitle>{{ t('Pet effects') }}</DialogTitle>
                    <DialogDescription>{{
                        t(
                            'See what is helping your dog and what needs attention.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <div
                    class="pet-effects-filters"
                    role="group"
                    :aria-label="t('Filter effects')"
                >
                    <button
                        v-for="option in filters"
                        :key="option.value"
                        type="button"
                        :aria-pressed="filter === option.value"
                        :class="`pet-effects-filter-${option.value}`"
                        @click="filter = option.value"
                    >
                        {{ option.label
                        }}<span>{{ number(option.count) }}</span>
                    </button>
                </div>
                <div
                    class="pet-effects-scroll"
                    tabindex="0"
                    role="region"
                    :aria-label="t('Lasting effects')"
                >
                    <StatusEffects
                        v-if="filtered.length"
                        :effects="filtered"
                        :server-now="new Date(now).toISOString()"
                    />
                    <div v-else class="pet-effects-empty" role="status">
                        <Sparkles :size="28" aria-hidden="true" />
                        <strong>{{
                            t(
                                filter === 'buff'
                                    ? 'No active buffs'
                                    : filter === 'debuff'
                                      ? 'No active debuffs'
                                      : 'No active effects',
                            )
                        }}</strong>
                        <p>
                            {{
                                t(
                                    'Effects appear here as you care for your dog.',
                                )
                            }}
                        </p>
                    </div>
                </div>
                <p class="pet-effects-footnote">
                    <Clock3 :size="14" aria-hidden="true" />{{
                        t(
                            'Timed effects disappear automatically when they expire.',
                        )
                    }}
                </p>
                <p class="pet-effects-footnote">
                    {{
                        t(
                            'Identical effects refresh without stacking. Only one condition tier applies. Total modifiers are limited to ±50%.',
                        )
                    }}
                </p>
            </DialogContent>
        </Dialog>
        <ul
            v-else
            class="pet-status-list"
            :aria-label="t(preview ? 'Effects after use' : 'Lasting effects')"
        >
            <li
                v-for="effect in visible"
                :key="effect.code"
                class="pet-status"
                :class="{ 'pet-status-negative': effect.kind === 'debuff' }"
            >
                <div class="pet-status-title">
                    <strong>{{ text(effect.name) }}</strong>
                    <span>{{
                        t(effect.kind === 'buff' ? 'Buff' : 'Debuff')
                    }}</span>
                </div>
                <p>{{ text(effect.description) }}</p>
                <small v-if="effect.disease_id">{{
                    t('Until treatment')
                }}</small>
                <small v-else-if="effect.duration_seconds">{{
                    t(
                        preview
                            ? 'After completion · {duration}'
                            : '{duration} left',
                        { duration: effectDuration(effect) },
                    )
                }}</small>
                <small v-else>{{ t('While the condition is met') }}</small>
            </li>
        </ul>
    </div>
</template>
