<script setup lang="ts">
defineProps<{ message?: string }>();
</script>
<template>
    <p v-if="message" class="field-error" role="alert">{{ message }}</p>
</template>
