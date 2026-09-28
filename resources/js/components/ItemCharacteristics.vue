<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import ItemBonuses from '@/components/ItemBonuses.vue';
import ItemRisks from '@/components/ItemRisks.vue';
import type { StatusEffect, ItemRisk } from '@/types/pet-care';

defineProps<{
    characteristics: Record<string, number | string | boolean>;
    bonuses?: Record<string, number>;
    grantedEffects?: StatusEffect[];
    risks?: ItemRisk[];
}>();
const { t, number } = useI18n();
</script>

<template>
    <div>
        <dl class="shop-properties">
            <slot />
            <div v-for="(value, key) in characteristics" :key="key">
                <dt>{{ t(key) }}</dt>
                <dd>
                    {{
                        typeof value === 'number'
                            ? number(value)
                            : t(String(value))
                    }}
                </dd>
            </div>
        </dl>
        <ItemBonuses
            v-if="bonuses || grantedEffects"
            :bonuses="bonuses ?? {}"
            :effects="grantedEffects ?? []"
        />
        <ItemRisks :risks="risks ?? []" />
    </div>
</template>
