<script setup lang="ts">
import StatusEffects from '@/components/StatusEffects.vue';
import { useI18n } from '@/composables/useI18n';
import { stateLabels } from '@/lib/petLabels';
import type { DogState } from '@/types/pet';
import type { StatusEffect } from '@/types/pet-care';

defineProps<{ bonuses: Record<string, number>; effects: StatusEffect[] }>();
const { t, number } = useI18n();
</script>

<template>
    <div class="item-bonuses">
        <p v-if="Object.keys(bonuses).length">{{ t('Bonus per use') }}</p>
        <dl v-if="Object.keys(bonuses).length" class="shop-properties">
            <div v-for="(value, state) in bonuses" :key="state">
                <dt>{{ t(stateLabels[state as DogState] ?? state) }}</dt>
                <dd>+{{ number(value) }} {{ t('p.p.') }}</dd>
            </div>
        </dl>
        <StatusEffects :effects="effects" preview />
    </div>
</template>
