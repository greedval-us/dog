<script setup lang="ts">
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <Card class="surface-card">
        <CardHeader v-if="title || $slots.header">
            <slot name="header"
                ><CardTitle>{{ title }}</CardTitle
                ><CardDescription v-if="description">{{
                    description
                }}</CardDescription></slot
            >
        </CardHeader>
        <CardContent><slot /></CardContent>
    </Card>
</template>
