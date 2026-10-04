<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { ArrowRight, Check, Save } from '@lucide/vue';

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
            ><Spinner v-if="processing" /><component
                v-else
                :is="label ? ArrowRight : Save"
                :size="17"
                aria-hidden="true"
            />{{ label ?? t('Save changes') }}</Button
        >
        <Transition name="form-feedback"
            ><span v-if="saved" class="form-success" role="status"
                ><Check :size="16" aria-hidden="true" />{{
                    t('Changes saved')
                }}</span
            ></Transition
        >
    </div>
</template>
