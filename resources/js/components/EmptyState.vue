<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
defineProps<{ title: string; description: string }>();
</script>

<template>
    <section class="empty-state">
        <div class="empty-state-symbol" aria-hidden="true"><PawPrint /></div>
        <span class="section-kicker">Большая дружба начинается здесь</span>
        <h2>{{ title }}</h2>
        <p>{{ description }}</p>
        <div v-if="$slots.default" class="empty-state-actions"><slot /></div>
    </section>
</template>
