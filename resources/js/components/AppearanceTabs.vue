<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Monitor, Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
const { appearance, updateAppearance } = useAppearance();
const options = [
    {
        value: 'light',
        icon: Sun,
        label: 'Light',
        description: 'Like a sunny morning',
    },
    {
        value: 'dark',
        icon: Moon,
        label: 'Dark',
        description: 'For quiet evenings',
    },
    {
        value: 'system',
        icon: Monitor,
        label: 'System',
        description: 'Match your device',
    },
] as const;
const { t } = useI18n();
</script>
<template>
    <div class="appearance-options" role="group" :aria-label="t('Color theme')">
        <Button
            v-for="option in options"
            :key="option.value"
            variant="plain"
            class="appearance-option"
            :class="{ selected: appearance === option.value }"
            :aria-pressed="appearance === option.value"
            @click="updateAppearance(option.value)"
            ><component :is="option.icon" :size="24" /><strong>{{
                t(option.label)
            }}</strong
            ><span>{{ t(option.description) }}</span></Button
        >
    </div>
</template>
