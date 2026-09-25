<script setup lang="ts">
import type { Component } from 'vue';
import { useI18n } from '@/composables/useI18n';
defineProps<{ title: string; description: string; icon: Component }>();
const { t } = useI18n();
</script>

<template>
    <section class="pet-feature-placeholder">
        <span class="pet-placeholder-icon"
            ><component :is="icon" :size="30" aria-hidden="true"
        /></span>
        <span class="coming-soon-badge">{{ t('Soon') }}</span>
        <h2>{{ title }}</h2>
        <p>{{ description }}</p>
    </section>
</template>
