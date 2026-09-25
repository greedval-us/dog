<script setup lang="ts">
import type { Component } from 'vue';
import { computed, useId } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = withDefaults(
    defineProps<{
        label: string;
        value: number;
        maximum?: number;
        icon: Component;
        tone?: 'sage' | 'amber' | 'blue' | 'violet' | 'rose';
        showPotential?: boolean;
    }>(),
    { maximum: 100, tone: 'sage', showPotential: false },
);
const id = useId();
const { locale } = useI18n();
const percentage = computed(() =>
    props.maximum > 0
        ? Math.min(100, Math.max(0, (props.value / props.maximum) * 100))
        : 0,
);
const number = (value: number) =>
    new Intl.NumberFormat(locale.value).format(value);
</script>

<template>
    <div class="pet-metric" :class="'pet-tone-' + tone">
        <component
            :is="icon"
            class="pet-metric-icon"
            :size="18"
            aria-hidden="true"
        />
        <label :for="id">{{ label }}</label>
        <progress
            :id="id"
            :value="percentage"
            max="100"
            :aria-label="label"
            :aria-valuetext="
                showPotential
                    ? number(value) + ' / ' + number(maximum)
                    : number(value) + '%'
            "
        >
            {{ number(percentage) }}%
        </progress>
        <span class="pet-metric-value"
            >{{ number(value) }}<template v-if="!showPotential">%</template
            ><small v-else> / {{ number(maximum) }}</small></span
        >
    </div>
</template>
