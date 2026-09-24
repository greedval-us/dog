<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
}>();
const field = computed(() => ({
    id: props.id,
    'aria-invalid': Boolean(props.error),
    'aria-describedby':
        [
            props.hint ? `${props.id}-hint` : null,
            props.error ? `${props.id}-error` : null,
        ]
            .filter(Boolean)
            .join(' ') || undefined,
}));
</script>

<template>
    <div class="form-field">
        <div class="field-heading">
            <Label :for="id">{{ label }}</Label
            ><slot name="aside" />
        </div>
        <slot :field="field" />
        <p v-if="hint" :id="`${id}-hint`" class="field-hint">{{ hint }}</p>
        <InputError :id="`${id}-error`" :message="error" />
    </div>
</template>
