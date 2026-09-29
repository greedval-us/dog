<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import StatusEffects from '@/components/StatusEffects.vue';
import type { ItemRisk } from '@/types/pet-care';

defineProps<{ risks: ItemRisk[]; includesTraining?: boolean }>();
const { t, locale, number } = useI18n();
</script>

<template>
    <div v-if="risks.length" class="item-risk-warning">
        <p v-for="risk in risks" :key="risk.effect.code">
            {{
                t('Chance per action: {effect} — {chance}%', {
                    effect:
                        risk.effect.name[locale] ??
                        risk.effect.name.en ??
                        risk.effect.code,
                    chance: number(risk.chance / 100),
                })
            }}
        </p>
        <small>{{
            t(
                includesTraining
                    ? 'Training and equipment risks are shown together. Matching effects use the highest chance; equal chances use the longest duration.'
                    : 'Chance is shown for this item’s quality. Matching effects use the highest chance; equal chances use the longest duration.',
            )
        }}</small>
        <details class="item-risk-details">
            <summary>{{ t('Effect details') }}</summary>
            <StatusEffects
                :effects="risks.map((risk) => risk.effect)"
                preview
            />
        </details>
    </div>
</template>
