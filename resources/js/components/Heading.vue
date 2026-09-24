<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        variant?: 'default' | 'small';
    }>(),
    { variant: 'default' },
);
</script>
<template>
    <header
        class="page-heading"
        :class="{ 'page-heading--small': variant === 'small' }"
    >
        <component :is="variant === 'small' ? 'h2' : 'h1'">{{
            title
        }}</component>
        <p v-if="description">{{ description }}</p>
    </header>
</template>
