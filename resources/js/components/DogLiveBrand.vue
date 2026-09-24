<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { PawPrint } from '@lucide/vue';
import { home } from '@/routes';

defineProps<{ href?: InertiaLinkProps['href']; compact?: boolean }>();
const { t } = useI18n();
</script>

<template>
    <Link
        :href="href ?? home()"
        class="doglive-brand"
        :class="{ 'doglive-brand--compact': compact }"
        :aria-label="t('DogLive — home')"
    >
        <PawPrint class="brand-paw" :stroke-width="2.6" />
        <span
            ><strong>DogLive</strong
            ><small v-if="!compact">{{ t('More than a game') }}</small></span
        >
    </Link>
</template>
