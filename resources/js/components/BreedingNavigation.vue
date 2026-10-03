<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Heart, PawPrint, ShoppingBag } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import { index as breeding } from '@/routes/breeding';
import { index as puppies, market } from '@/routes/puppies';

defineProps<{ active: 'breeding' | 'puppies' | 'market' }>();
const { t } = useI18n();
const items = [
    { value: 'breeding', label: 'Breeding', icon: Heart, route: breeding },
    { value: 'puppies', label: 'My puppies', icon: PawPrint, route: puppies },
    {
        value: 'market',
        label: 'Puppy market',
        icon: ShoppingBag,
        route: market,
    },
] as const;
</script>

<template>
    <nav class="breeding-navigation" :aria-label="t('Breeding sections')">
        <Link
            v-for="item in items"
            :key="item.value"
            :href="item.route()"
            class="breeding-navigation-link"
            :class="{ 'is-active': active === item.value }"
            :aria-current="active === item.value ? 'page' : undefined"
        >
            <component :is="item.icon" :size="17" aria-hidden="true" />{{
                t(item.label)
            }}
        </Link>
    </nav>
</template>
