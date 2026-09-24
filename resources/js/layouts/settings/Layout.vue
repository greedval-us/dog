<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Palette, ShieldCheck, UserRound } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as appearance } from '@/routes/appearance';
import { edit as profile } from '@/routes/profile';
import { edit as security } from '@/routes/security';

const items = [
    { title: 'Профиль', href: profile(), icon: UserRound },
    { title: 'Безопасность', href: security(), icon: ShieldCheck },
    { title: 'Оформление', href: appearance(), icon: Palette },
];
const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <div class="settings-page">
        <Heading
            title="Настройки"
            description="Твой профиль и уютное пространство для игры."
        />
        <div class="settings-layout">
            <nav class="settings-navigation" aria-label="Настройки аккаунта">
                <Link
                    v-for="item in items"
                    :key="item.title"
                    :href="item.href"
                    :aria-current="isCurrentUrl(item.href) ? 'page' : undefined"
                    ><component :is="item.icon" :size="19" /><span>{{
                        item.title
                    }}</span></Link
                >
            </nav>
            <div class="settings-content"><slot /></div>
        </div>
    </div>
</template>
