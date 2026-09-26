<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { PawPrint } from '@lucide/vue';
defineProps<{ title: string; description: string; kicker?: string | null }>();
const { t } = useI18n();
</script>

<template>
    <section class="empty-state">
        <div class="empty-state-symbol" aria-hidden="true">
            <slot name="icon"><PawPrint /></slot>
        </div>
        <span v-if="kicker !== null" class="section-kicker">{{
            kicker ?? t('A great friendship starts here')
        }}</span>
        <h2>{{ title }}</h2>
        <p>{{ description }}</p>
        <div v-if="$slots.default" class="empty-state-actions"><slot /></div>
    </section>
</template>
