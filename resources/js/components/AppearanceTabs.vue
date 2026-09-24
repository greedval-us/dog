<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
const { appearance, updateAppearance } = useAppearance();
const options = [
    {
        value: 'light',
        icon: Sun,
        label: 'Светлая',
        description: 'Как солнечное утро',
    },
    {
        value: 'dark',
        icon: Moon,
        label: 'Тёмная',
        description: 'Для тихих вечеров',
    },
    {
        value: 'system',
        icon: Monitor,
        label: 'Системная',
        description: 'Как на устройстве',
    },
] as const;
</script>
<template>
    <div class="appearance-options" role="group" aria-label="Цветовая тема">
        <Button
            v-for="option in options"
            :key="option.value"
            variant="plain"
            class="appearance-option"
            :class="{ selected: appearance === option.value }"
            :aria-pressed="appearance === option.value"
            @click="updateAppearance(option.value)"
            ><component :is="option.icon" :size="24" /><strong>{{
                option.label
            }}</strong
            ><span>{{ option.description }}</span></Button
        >
    </div>
</template>
