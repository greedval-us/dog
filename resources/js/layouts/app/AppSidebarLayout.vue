<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Link, usePage } from '@inertiajs/vue3';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';

const page = usePage();
const menuOpen = ref(false);
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
            <div class="sidebar-story">
                <Heart
                    class="sidebar-story-icon"
                    :size="25"
                    aria-hidden="true"
                />
                <span class="section-kicker">{{
                    t('Together every day')
                }}</span>
                <p>{{ t('A great friendship starts with a little care.') }}</p>
            </div>
            <Link :href="dashboard()" class="sidebar-caption">{{
                t('Your little world of DogLive')
            }}</Link>
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
                    <div class="wallet" :aria-label="t('Player balance')">
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
                            /><b :key="user.coins" class="wallet-amount">{{
                                formatNumber(user.coins)
                            }}</b></span
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
                            /><b :key="user.gems" class="wallet-amount">{{
                                formatNumber(user.gems)
                            }}</b></span
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
                />
            </Transition>
            <main id="main-content" class="doglive-content" tabindex="-1">
                <slot />
            </main>
            <DogLiveFooter />
        </div>
    </div>
</template>
