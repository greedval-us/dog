<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Link } from '@inertiajs/vue3';
import { House, PawPrint, Settings } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { index as kennel } from '@/routes/kennel';
import { edit } from '@/routes/profile';

const { currentUrl, isCurrentUrl } = useCurrentUrl();
const items = [
    { label: 'My dog', icon: PawPrint, href: dashboard(), settings: false },
    { label: 'Kennel', icon: House, href: kennel(), settings: false },
    { label: 'Settings', icon: Settings, href: edit(), settings: true },
];
const isActive = (item: (typeof items)[number]) =>
    item.settings
        ? currentUrl.value.startsWith('/settings')
        : isCurrentUrl(item.href);
const { t } = useI18n();
</script>

<template>
    <nav class="side-nav" :aria-label="t('Main navigation')">
        <Link
            v-for="item in items"
            :key="item.label"
            :href="item.href"
            class="nav-item"
            :class="{ active: isActive(item) }"
            :aria-current="isActive(item) ? 'page' : undefined"
            :aria-label="t(item.label)"
            ><component :is="item.icon" /><span>{{ t(item.label) }}</span></Link
        >
    </nav>
</template>
