<script setup lang="ts">
import { CircleAlert, Info } from '@lucide/vue';

withDefaults(
    defineProps<{ message: string | null; tone?: 'warning' | 'info' }>(),
    { tone: 'warning' },
);
</script>

<template>
    <div v-if="message" class="action-hint" :class="'action-hint-' + tone">
        <component
            :is="tone === 'warning' ? CircleAlert : Info"
            :size="16"
            aria-hidden="true"
        />
        <div>
            <p>{{ message }}</p>
            <slot />
        </div>
    </div>
</template>
