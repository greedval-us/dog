<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import {
    PopoverTrigger,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
} from 'reka-ui';
import { onUnmounted, ref, useId, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';

defineProps<{ text: string; label?: string }>();

const { t } = useI18n();
const id = useId();
const open = ref(false);
const pinned = ref(false);
const trigger = ref<HTMLButtonElement | null>(null);
let closeTimer: ReturnType<typeof setTimeout> | undefined;

function cancelClose() {
    clearTimeout(closeTimer);
}

function show() {
    cancelClose();
    open.value = true;
}

function focusHint(event: FocusEvent) {
    if ((event.target as HTMLElement).matches(':focus-visible')) {
        show();
    }
}

function togglePinned() {
    cancelClose();
    pinned.value = !pinned.value;
    open.value = pinned.value;
}

watch(open, (value) => {
    if (!value) {
        pinned.value = false;
    }
});

function leave() {
    cancelClose();
    closeTimer = setTimeout(() => {
        const contentId = trigger.value?.getAttribute('aria-controls');
        const content = contentId ? document.getElementById(contentId) : null;
        if (
            document.activeElement !== trigger.value &&
            !content?.contains(document.activeElement)
        ) {
            open.value = false;
        }
    }, 160);
}

onUnmounted(cancelClose);
</script>

<template>
    <span class="help-hint">
        <PopoverRoot v-model:open="open">
            <PopoverTrigger as-child>
                <button
                    ref="trigger"
                    type="button"
                    class="help-hint-trigger"
                    :aria-label="label ?? t('Help')"
                    :aria-describedby="`${id}-description`"
                    @pointerenter="$event.pointerType === 'mouse' && show()"
                    @pointerleave="leave"
                    @focus="focusHint"
                    @blur="leave"
                    @click="togglePinned"
                >
                    <CircleHelp :size="17" aria-hidden="true" />
                </button>
            </PopoverTrigger>
            <span :id="`${id}-description`" class="sr-only">{{ text }}</span>
            <PopoverPortal>
                <PopoverContent
                    class="help-hint-content"
                    side="bottom"
                    align="start"
                    :side-offset="8"
                    :collision-padding="16"
                    :aria-label="label ?? t('Help')"
                    @open-auto-focus.prevent
                    @close-auto-focus.prevent
                    @pointerenter="cancelClose"
                    @pointerleave="leave"
                >
                    {{ text }}
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </span>
</template>
