<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
const { t } = useI18n();
</script>
<template>
    <div class="settings-stack">
        <Head :title="t('Appearance')" /><SurfaceCard
            :title="t('Your DogLive')"
            :hint="
                t(
                    'Choose a look for your world. This setting is saved on this device.',
                )
            "
            ><AppearanceTabs
        /></SurfaceCard>
        <SurfaceCard
            :title="t('Interface language')"
            :hint="
                t(
                    'Choose your DogLive language. This setting is saved in your account.',
                )
            "
        >
            <LanguageSwitcher />
        </SurfaceCard>
    </div>
</template>
