<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Backpack,
    BriefcaseBusiness,
    House,
    Heart,
    PawPrint,
    Settings,
    ShoppingBag,
    Stethoscope,
    Trophy,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { index as kennel } from '@/routes/kennel';
import { index as shop } from '@/routes/shop';
import { index as inventory } from '@/routes/inventory';
import { index as dogWork } from '@/routes/dog-work';
import { index as veterinarian } from '@/routes/veterinarian';
import { index as breeding } from '@/routes/breeding';
import { index as gameEvents } from '@/routes/game-events';
import { edit } from '@/routes/profile';
import { show as playerProfile } from '@/routes/players';

const { currentUrl, isCurrentUrl } = useCurrentUrl();
const page = usePage();
const items = computed(() => [
    { label: 'My dog', icon: PawPrint, href: dashboard(), settings: false },
    { label: 'Kennel', icon: House, href: kennel(), settings: false },
    { label: 'Shop', icon: ShoppingBag, href: shop(), settings: false },
    { label: 'Inventory', icon: Backpack, href: inventory(), settings: false },
    { label: 'Breeding', icon: Heart, href: breeding(), settings: false },
    {
        label: 'Events and shows',
        icon: Trophy,
        href: gameEvents(),
        settings: false,
    },
    {
        label: 'Veterinarian',
        icon: Stethoscope,
        href: veterinarian(),
        settings: false,
    },
    {
        label: 'Work with a dog',
        icon: BriefcaseBusiness,
        href: dogWork(),
        settings: false,
    },
    {
        label: 'Player card',
        icon: UserRound,
        href: playerProfile(page.props.auth.user.username),
        settings: false,
    },
    { label: 'Settings', icon: Settings, href: edit(), settings: true },
]);
const isActive = (item: (typeof items.value)[number]) =>
    item.settings
        ? currentUrl.value.startsWith('/settings')
        : item.label === 'Events and shows'
          ? currentUrl.value.startsWith(gameEvents.url())
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
            :class="{
                active: isActive(item),
                'nav-item--account': item.label === 'Player card',
            }"
            :aria-current="isActive(item) ? 'page' : undefined"
            :aria-label="t(item.label)"
            :title="t(item.label)"
            ><component :is="item.icon" aria-hidden="true" /><span>{{
                t(item.label)
            }}</span></Link
        >
    </nav>
</template>
