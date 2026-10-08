<script setup lang="ts">
import {
    Card,
    CardContent,
    CardHeader,
    CardDescription,
} from '@/components/ui/card';
import HelpHint from '@/components/HelpHint.vue';
withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        hint?: string;
        titleTag?: 'h2' | 'h3' | 'h4';
    }>(),
    { titleTag: 'h2' },
);
</script>

<template>
    <Card class="surface-card">
        <CardHeader v-if="title || $slots.header">
            <slot name="header"
                ><div class="surface-card-heading">
                    <component
                        :is="titleTag"
                        data-slot="card-title"
                        class="leading-none font-semibold"
                        >{{ title }}</component
                    ><HelpHint v-if="hint" :text="hint" :label="title" />
                </div>
                <CardDescription v-if="description">{{
                    description
                }}</CardDescription></slot
            >
        </CardHeader>
        <CardContent v-if="$slots.default"><slot /></CardContent>
    </Card>
</template>
