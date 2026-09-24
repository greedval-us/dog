<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PawPrint, Settings } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';

const { currentUrl, isCurrentUrl } = useCurrentUrl();
const items = [
    { label: 'Моя собака', icon: PawPrint, href: dashboard(), settings: false },
    { label: 'Настройки', icon: Settings, href: edit(), settings: true },
];
const isActive = (item: (typeof items)[number]) =>
    item.settings
        ? currentUrl.value.startsWith('/settings')
        : isCurrentUrl(item.href);
</script>

<template>
    <nav class="side-nav" aria-label="Основная навигация">
        <Link
            v-for="item in items"
            :key="item.label"
            :href="item.href"
            class="nav-item"
            :class="{ active: isActive(item) }"
            :aria-current="isActive(item) ? 'page' : undefined"
            :aria-label="item.label"
            ><component :is="item.icon" /><span>{{ item.label }}</span></Link
        >
    </nav>
</template>
