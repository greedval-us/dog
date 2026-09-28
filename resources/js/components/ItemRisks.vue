<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import type { ItemRisk } from '@/types/pet-care';

defineProps<{ risks: ItemRisk[] }>();
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
                'Chance is shown for this item’s quality. Matching effects use the highest chance; equal chances use the longest duration.',
            )
        }}</small>
    </div>
</template>
