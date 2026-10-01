<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    House,
    PawPrint,
    Settings,
    ShoppingBag,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { index as kennel } from '@/routes/kennel';
import { index as shop } from '@/routes/shop';
import { index as dogWork } from '@/routes/dog-work';
import { edit } from '@/routes/profile';
import { show as playerProfile } from '@/routes/players';

const { currentUrl, isCurrentUrl } = useCurrentUrl();
const page = usePage();
const items = computed(() => [
    {
        label: 'Player card',
        icon: UserRound,
        href: playerProfile(page.props.auth.user.username),
        settings: false,
    },
    { label: 'My dog', icon: PawPrint, href: dashboard(), settings: false },
    { label: 'Kennel', icon: House, href: kennel(), settings: false },
    { label: 'Shop', icon: ShoppingBag, href: shop(), settings: false },
    {
        label: 'Work with a dog',
        icon: BriefcaseBusiness,
        href: dogWork(),
        settings: false,
    },
    { label: 'Settings', icon: Settings, href: edit(), settings: true },
]);
const isActive = (item: (typeof items.value)[number]) =>
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
