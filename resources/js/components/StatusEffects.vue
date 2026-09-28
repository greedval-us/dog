<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { StatusEffect } from '@/types/pet-care';

const props = defineProps<{
    effects: StatusEffect[];
    serverNow?: string;
    preview?: boolean;
}>();
const { t, locale, number } = useI18n();
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
const minutes = (effect: StatusEffect) =>
    Math.max(
        1,
        Math.ceil(
            (props.preview
                ? (effect.duration_seconds ?? 0)
                : (effect.expires_at ?? 0) - now.value / 1000) / 60,
        ),
    );
</script>

<template>
    <ul
        v-if="visible.length"
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
                <span>{{ t(effect.kind === 'buff' ? 'Buff' : 'Debuff') }}</span>
            </div>
            <p>{{ text(effect.description) }}</p>
            <small v-if="effect.duration_seconds">{{
                t(
                    preview
                        ? 'After completion · {count} min'
                        : '{count} min left',
                    { count: number(minutes(effect)) },
                )
            }}</small>
            <small v-else>{{ t('Until the need is restored') }}</small>
        </li>
    </ul>
</template>
