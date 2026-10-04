<script setup lang="ts">
import {
    Bone,
    CircleDot,
    Dumbbell,
    Footprints,
    Package,
    Ribbon,
    Shirt,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps<{ category: string }>();
const { t } = useI18n();
const icons = {
    food: Bone,
    sports: Dumbbell,
    clothing: Shirt,
    collars: Ribbon,
    leashes: Footprints,
    care: Sparkles,
    toys: CircleDot,
    ammunition: Ribbon,
    competition: Ribbon,
};
const icon = computed(
    () => icons[props.category as keyof typeof icons] ?? Package,
);
</script>

<template>
    <span
        class="shop-artwork"
        :class="'shop-artwork--' + category"
        role="img"
        :aria-label="t('Item image placeholder')"
    >
        <span class="shop-artwork-circle"
            ><component
                :is="icon"
                :size="48"
                :stroke-width="1.25"
                aria-hidden="true"
        /></span>
        <span class="shop-artwork-caption" aria-hidden="true">DogLive</span>
    </span>
</template>
