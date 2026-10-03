<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import { useI18n } from '@/composables/useI18n';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';

const { t } = useI18n();
</script>

<template>
    <AppSidebarLayout>
        <slot />
        <Toaster :container-aria-label="t('Notifications')" />
    </AppSidebarLayout>
</template>
