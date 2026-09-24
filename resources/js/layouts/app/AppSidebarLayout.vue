<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Coins, Gem, Menu, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DogLiveBrand from '@/components/DogLiveBrand.vue';
import DogLiveFooter from '@/components/DogLiveFooter.vue';
import DogLiveNavigation from '@/components/DogLiveNavigation.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';

const page = usePage();
const menuOpen = ref(false);
const user = computed(() => page.props.auth.user);
const formatNumber = (value: unknown) =>
    new Intl.NumberFormat('ru-RU').format(Number(value) || 0);
watch(
    () => page.url,
    () => {
        menuOpen.value = false;
    },
);
</script>

<template>
    <div class="doglive-shell">
        <a class="skip-link" href="#main-content">Перейти к содержимому</a>
        <aside class="doglive-sidebar">
            <DogLiveBrand />
            <DogLiveNavigation />
            <div class="sidebar-story">
                <span class="section-kicker">Каждый день — вместе</span>
                <p>Большая дружба начинается с маленькой заботы.</p>
            </div>
            <Link :href="dashboard()" class="sidebar-caption"
                >Твой маленький мир DogLive</Link
            >
        </aside>
        <div class="doglive-workspace">
            <header class="doglive-topbar">
                <DogLiveBrand compact class="mobile-brand" />
                <span class="topbar-greeting"
                    >Рады видеть тебя,
                    <strong>{{ user.username }}</strong></span
                >
                <div class="account-controls">
                    <div class="wallet" aria-label="Баланс игрока">
                        <span :title="formatNumber(user.coins) + ' монет'"
                            ><Coins class="coin-icon" :size="20" /><b>{{
                                formatNumber(user.coins)
                            }}</b></span
                        >
                        <span :title="formatNumber(user.gems) + ' кристаллов'"
                            ><Gem class="gem-icon" :size="20" /><b>{{
                                formatNumber(user.gems)
                            }}</b></span
                        >
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="plain"
                                class="profile-link"
                                :aria-label="'Меню игрока ' + user.username"
                            >
                                <span class="player-avatar">{{
                                    user.username?.charAt(0).toUpperCase()
                                }}</span
                                ><span class="player-name">{{
                                    user.username
                                }}</span
                                ><ChevronDown :size="14" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="account-menu"
                            ><UserMenuContent :user="user"
                        /></DropdownMenuContent>
                    </DropdownMenu>
                    <Button
                        variant="plain"
                        class="mobile-menu-button"
                        :aria-expanded="menuOpen"
                        aria-controls="mobile-navigation"
                        :aria-label="menuOpen ? 'Закрыть меню' : 'Открыть меню'"
                        @click="menuOpen = !menuOpen"
                        ><X v-if="menuOpen" /><Menu v-else
                    /></Button>
                </div>
            </header>
            <DogLiveNavigation
                v-if="menuOpen"
                id="mobile-navigation"
                class="mobile-navigation"
            />
            <main id="main-content" class="doglive-content" tabindex="-1">
                <slot />
            </main>
            <DogLiveFooter />
        </div>
    </div>
</template>
