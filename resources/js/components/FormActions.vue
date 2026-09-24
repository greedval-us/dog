<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

withDefaults(
    defineProps<{
        processing?: boolean;
        saved?: boolean;
        label?: string;
        testId?: string;
    }>(),
    { label: 'Сохранить изменения' },
);
</script>

<template>
    <div class="form-actions">
        <Button type="submit" :disabled="processing" :data-test="testId"
            ><Spinner v-if="processing" />{{ label }}</Button
        >
        <span v-if="saved" class="form-success" role="status"
            >Изменения сохранены</span
        >
    </div>
</template>
