<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Link } from '@inertiajs/vue3';
import { Palette, ShieldCheck, UserRound } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as appearance } from '@/routes/appearance';
import { edit as profile } from '@/routes/profile';
import { edit as security } from '@/routes/security';

const items = [
    { title: 'Profile', href: profile(), icon: UserRound },
    { title: 'Security', href: security(), icon: ShieldCheck },
    { title: 'Appearance', href: appearance(), icon: Palette },
];
const { isCurrentUrl } = useCurrentUrl();
const { t } = useI18n();
</script>

<template>
    <div class="settings-page">
        <Heading
            :title="t('Settings')"
            :description="t('Your profile and a cozy place to play.')"
        />
        <div class="settings-layout">
            <nav
                class="settings-navigation"
                :aria-label="t('Account settings')"
            >
                <Link
                    v-for="item in items"
                    :key="item.title"
                    :href="item.href"
                    :aria-current="isCurrentUrl(item.href) ? 'page' : undefined"
                    ><component :is="item.icon" :size="19" /><span>{{
                        t(item.title)
                    }}</span></Link
                >
            </nav>
            <div class="settings-content"><slot /></div>
        </div>
    </div>
</template>
