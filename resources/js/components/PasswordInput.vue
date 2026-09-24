<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Eye, EyeOff } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    class?: HTMLAttributes['class'];
    autofocus?: boolean;
}>();
const showPassword = ref(false);
const inputRef = useTemplateRef('inputRef');
defineExpose({ $el: inputRef, focus: () => inputRef.value?.$el?.focus() });
const { t } = useI18n();
</script>
<template>
    <div class="password-field">
        <Input
            v-focus="props.autofocus ?? false"
            ref="inputRef"
            :type="showPassword ? 'text' : 'password'"
            :class="cn('password-field-input', props.class)"
            v-bind="$attrs"
        /><Button
            type="button"
            variant="plain"
            class="password-toggle"
            @click="showPassword = !showPassword"
            :aria-label="showPassword ? t('Hide password') : t('Show password')"
            :aria-pressed="showPassword"
            ><EyeOff v-if="showPassword" :size="18" /><Eye v-else :size="18"
        /></Button>
    </div>
</template>
