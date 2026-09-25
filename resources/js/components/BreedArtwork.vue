<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
import { ref, watch } from 'vue';
import { image } from '@/routes/breeds';

const props = withDefaults(
    defineProps<{
        breed: string | null;
        variant?: 'portrait' | 'icon';
        alt?: string;
    }>(),
    { variant: 'portrait', alt: '' },
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
            @error="failed = true"
        />
        <PawPrint v-else :aria-label="alt || undefined" :aria-hidden="!alt" />
    </span>
</template>
