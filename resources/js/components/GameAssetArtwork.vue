<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
import { ref, watch } from 'vue';
import { image } from '@/routes/assets';

const props = withDefaults(
    defineProps<{
        assetId: number | null;
        variant?: 'image' | 'icon';
        alt?: string;
        lazy?: boolean;
    }>(),
    { variant: 'image', alt: '', lazy: false },
);
const failed = ref(false);
watch(
    () => [props.assetId, props.variant],
    () => {
        failed.value = false;
    },
);
</script>

<template>
    <span class="game-asset-artwork" :class="'game-asset-' + variant">
        <img
            v-if="assetId && !failed"
            :src="image.url({ asset: assetId, variant })"
            :alt="alt"
            width="320"
            height="320"
            decoding="async"
            :loading="lazy ? 'lazy' : 'eager'"
            @error="failed = true"
        />
        <PawPrint v-else :aria-label="alt || undefined" :aria-hidden="!alt" />
    </span>
</template>
