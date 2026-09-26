<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';

defineProps<{
    characteristics: Record<string, number | string | boolean>;
}>();
const { t, number } = useI18n();
</script>

<template>
    <dl class="shop-properties">
        <slot />
        <div v-for="(value, key) in characteristics" :key="key">
            <dt>{{ t(key) }}</dt>
            <dd>
                {{
                    typeof value === 'number' ? number(value) : t(String(value))
                }}
            </dd>
        </div>
    </dl>
</template>
