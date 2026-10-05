<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
import { ref, watch } from 'vue';
import { image } from '@/routes/breeds';

const props = withDefaults(
    defineProps<{
        breed: string | null;
        variant?: 'portrait' | 'icon';
        alt?: string;
        lazy?: boolean;
    }>(),
    { variant: 'portrait', alt: '', lazy: false },
);
const failed = ref(false);
watch(
    () => [props.breed, props.variant],
    () => {
        failed.value = false;
    },
);
</script>

<template>
    <span class="breed-artwork" :class="'breed-artwork-' + variant">
        <img
            v-if="breed && !failed"
            :src="image.url({ breed, variant })"
            :alt="alt"
            :width="variant === 'icon' ? 56 : 320"
            :height="variant === 'icon' ? 56 : 320"
            decoding="async"
            :loading="lazy ? 'lazy' : 'eager'"
            @error="failed = true"
        />
        <PawPrint v-else :aria-label="alt || undefined" :aria-hidden="!alt" />
    </span>
</template>
