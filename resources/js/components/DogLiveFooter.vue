<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { PawPrint } from '@lucide/vue';
const { t } = useI18n();
</script>

<template>
    <footer class="doglive-footer">
        <span
            ><PawPrint :size="16" />DogLive <i>·</i>
            {{ t('More than a game') }}</span
        ><span>{{ t('Play. Care. Grow together.') }}</span>
        <div v-if="$slots.controls" class="footer-controls">
            <slot name="controls" />
        </div>
    </footer>
</template>
