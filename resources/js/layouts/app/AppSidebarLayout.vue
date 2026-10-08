<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown, Coins, Gem, Heart, Menu, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DogLiveBrand from '@/components/DogLiveBrand.vue';
import DogLiveFooter from '@/components/DogLiveFooter.vue';
import DogLiveNavigation from '@/components/DogLiveNavigation.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import SystemNotifications from '@/components/SystemNotifications.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import PlayerAvatar from '@/components/PlayerAvatar.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const page = usePage();
const menuOpen = ref(false);
function closeMenu() {
    menuOpen.value = false;
    document.getElementById('mobile-menu-toggle')?.focus();
}
const user = computed(() => page.props.auth.user);
const formatNumber = (value: unknown) => number(Number(value) || 0);
watch(
    () => page.url,
    () => {
        menuOpen.value = false;
    },
);
const { t, number } = useI18n();
</script>

<template>
    <div class="doglive-shell">
        <a class="skip-link" href="#main-content">{{ t('Skip to content') }}</a>
        <aside class="doglive-sidebar">
            <DogLiveBrand />
            <DogLiveNavigation />
            <span class="sidebar-note"
                ><Heart :size="16" aria-hidden="true" />{{
                    t('Together every day')
                }}</span
            >
        </aside>
        <div class="doglive-workspace">
            <header class="doglive-topbar">
                <DogLiveBrand compact class="mobile-brand" />
                <span class="topbar-greeting"
                    >{{ t('Good to see you,') }}
                    <strong>{{ user.username }}</strong></span
                >
                <div class="account-controls">
                    <div class="header-tools">
                        <LanguageSwitcher />
                        <SystemNotifications />
                    </div>
                    <div
                        class="wallet"
                        role="group"
                        :aria-label="t('Player balance')"
                    >
                        <span
                            :title="
                                t('Coins: {amount}', {
                                    amount: formatNumber(user.coins),
                                })
                            "
                            ><Coins
                                class="coin-icon"
                                :size="20"
                                aria-hidden="true"
                            /><b
                                :key="user.coins"
                                class="wallet-amount"
                                aria-hidden="true"
                                >{{ formatNumber(user.coins) }}</b
                            ><span class="sr-only">{{
                                t('Coins: {amount}', {
                                    amount: formatNumber(user.coins),
                                })
                            }}</span></span
                        >
                        <span
                            :title="
                                t('Gems: {amount}', {
                                    amount: formatNumber(user.gems),
                                })
                            "
                            ><Gem
                                class="gem-icon"
                                :size="20"
                                aria-hidden="true"
                            /><b
                                :key="user.gems"
                                class="wallet-amount"
                                aria-hidden="true"
                                >{{ formatNumber(user.gems) }}</b
                            ><span class="sr-only">{{
                                t('Gems: {amount}', {
                                    amount: formatNumber(user.gems),
                                })
                            }}</span></span
                        >
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="plain"
                                class="profile-link"
                                :aria-label="
                                    t('Player menu {username}', {
                                        username: user.username,
                                    })
                                "
                            >
                                <PlayerAvatar
                                    :username="user.username"
                                    :version="user.avatarVersion"
                                />
                                <span class="player-name">{{
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
                        id="mobile-menu-toggle"
                        variant="plain"
                        class="mobile-menu-button"
                        :aria-expanded="menuOpen"
                        aria-controls="mobile-navigation"
                        :aria-label="
                            menuOpen ? t('Close menu') : t('Open menu')
                        "
                        @click="menuOpen = !menuOpen"
                        ><X v-if="menuOpen" /><Menu v-else
                    /></Button>
                </div>
            </header>
            <Transition name="mobile-menu">
                <DogLiveNavigation
                    v-if="menuOpen"
                    id="mobile-navigation"
                    class="mobile-navigation"
                    @keydown.esc="closeMenu"
                />
            </Transition>
            <main id="main-content" class="doglive-content" tabindex="-1">
                <slot />
            </main>
            <DogLiveFooter />
            <Toaster :container-aria-label="t('Notifications')" />
        </div>
    </div>
</template>
