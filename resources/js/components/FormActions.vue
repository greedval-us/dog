<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    processing?: boolean;
    saved?: boolean;
    label?: string;
    testId?: string;
}>();
const { t } = useI18n();
</script>

<template>
    <div class="form-actions">
        <Button type="submit" :disabled="processing" :data-test="testId"
            ><Spinner v-if="processing" />{{
                label ?? t('Save changes')
            }}</Button
        >
        <span v-if="saved" class="form-success" role="status">{{
            t('Changes saved')
        }}</span>
    </div>
</template>
