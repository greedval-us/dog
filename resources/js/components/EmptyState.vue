<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { PawPrint } from '@lucide/vue';
defineProps<{ title: string; description: string }>();
const { t } = useI18n();
</script>

<template>
    <section class="empty-state">
        <div class="empty-state-symbol" aria-hidden="true"><PawPrint /></div>
        <span class="section-kicker">{{
            t('A great friendship starts here')
        }}</span>
        <h2>{{ title }}</h2>
        <p>{{ description }}</p>
        <div v-if="$slots.default" class="empty-state-actions"><slot /></div>
    </section>
</template>
