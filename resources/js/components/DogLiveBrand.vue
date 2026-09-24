<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { PawPrint } from '@lucide/vue';
import { home } from '@/routes';

defineProps<{ href?: InertiaLinkProps['href']; compact?: boolean }>();
</script>

<template>
    <Link
        :href="href ?? home()"
        class="doglive-brand"
        :class="{ 'doglive-brand--compact': compact }"
        aria-label="DogLive — главная"
    >
        <PawPrint class="brand-paw" :stroke-width="2.6" />
        <span
            ><strong>DogLive</strong
            ><small v-if="!compact">Больше, чем игра</small></span
        >
    </Link>
</template>
