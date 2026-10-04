<script setup lang="ts">
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import HelpHint from '@/components/HelpHint.vue';
defineProps<{ title?: string; description?: string; hint?: string }>();
</script>

<template>
    <Card class="surface-card">
        <CardHeader v-if="title || $slots.header">
            <slot name="header"
                ><div class="surface-card-heading">
                    <CardTitle>{{ title }}</CardTitle
                    ><HelpHint v-if="hint" :text="hint" :label="title" />
                </div>
                <CardDescription v-if="description">{{
                    description
                }}</CardDescription></slot
            >
        </CardHeader>
        <CardContent><slot /></CardContent>
    </Card>
</template>
