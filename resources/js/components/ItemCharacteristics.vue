<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';
import ItemBonuses from '@/components/ItemBonuses.vue';
import CompetitionGearDetails from '@/components/CompetitionGearDetails.vue';
import ItemRisks from '@/components/ItemRisks.vue';
import type { StatusEffect, ItemRisk } from '@/types/pet-care';
import type { CompetitionGear } from '@/types/competition-gear';

const props = defineProps<{
    characteristics: Record<string, unknown>;
    bonuses?: Record<string, number>;
    grantedEffects?: StatusEffect[];
    risks?: ItemRisk[];
    competition?: CompetitionGear | null;
}>();
const { t, number } = useI18n();
const properties = computed(() =>
    Object.fromEntries(
        Object.entries(props.characteristics).filter(
            ([key, value]) =>
                key !== 'competition' &&
                ['string', 'number', 'boolean'].includes(typeof value),
        ),
    ),
);
</script>

<template>
    <div>
        <dl class="shop-properties">
            <slot />
            <div v-for="(value, key) in properties" :key="key">
                <dt>{{ t(String(key)) }}</dt>
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
        <CompetitionGearDetails v-if="competition" :gear="competition" />
    </div>
</template>
