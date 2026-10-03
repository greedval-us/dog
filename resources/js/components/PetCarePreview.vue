<script setup lang="ts">
import { Zap } from '@lucide/vue';
import { computed } from 'vue';
import ItemRisks from '@/components/ItemRisks.vue';
import StatusEffects from '@/components/StatusEffects.vue';
import { useI18n } from '@/composables/useI18n';
import { stateLabels, statLabels } from '@/lib/petLabels';
import { petCarePreview } from '@/lib/petCarePreview';
import type { DogStat, PlayerPet } from '@/types/pet';
import type { CareItem, CareOption, PetCare } from '@/types/pet-care';

const props = defineProps<{
    pet: PlayerPet;
    care: PetCare;
    option: CareOption;
    items: CareItem[];
    now: number;
    trainingOnly: boolean;
}>();
const { t, number, locale } = useI18n();
const localized = (value: Record<string, string>) =>
    value[locale.value] ?? value.en ?? '';
const preview = computed(() =>
    petCarePreview(props.option, props.items, props.pet, props.care, props.now),
);
const benefits = computed(() =>
    preview.value.stateEffects
        .filter((effect) => effect.amount > 0)
        .map((effect) => ({ ...effect, label: stateLabels[effect.state] })),
);
const costs = computed(() =>
    preview.value.stateEffects
        .filter((effect) => effect.amount < 0)
        .map((effect) => ({ ...effect, label: stateLabels[effect.state] })),
);
const statGains = computed(() =>
    Object.entries(preview.value.statGains).map(([stat, gain]) => ({
        label: statLabels[stat as DogStat],
        gain,
    })),
);
const grantedEffects = computed(() => preview.value.grantedEffects);
const recoveryEffects = computed(() => preview.value.recoveryEffects);
const risks = computed(() => preview.value.risks);
const energyCostPercentage = computed(() => preview.value.energyCostPercentage);
const energyGainCapped = computed(() => preview.value.energyGainCapped);
</script>

<template>
    <div class="contents">
        <section class="pet-care-preview" :aria-label="t('Result')">
            <h3>{{ t('Your dog will get') }}</h3>
            <div v-if="benefits.length" class="pet-care-benefits">
                <div
                    v-for="effect in benefits"
                    :key="effect.label"
                    class="pet-care-benefit"
                >
                    <strong>+{{ effect.amount }}%</strong
                    ><span>{{ t(effect.label) }}</span>
                </div>
            </div>
            <p v-else-if="!statGains.length && !trainingOnly">
                {{ t('These needs are already full.') }}
            </p>
            <p v-if="trainingOnly && !statGains.length">
                {{ t('Select equipment to preview attribute gains.') }}
            </p>
            <div v-if="statGains.length" class="pet-care-benefits">
                <div
                    v-for="stat in statGains"
                    :key="stat.label"
                    class="pet-care-benefit"
                >
                    <strong>+{{ number(stat.gain ?? 0) }}</strong
                    ><span>{{ t(stat.label) }}</span>
                </div>
            </div>
            <p v-if="statGains.length" class="pet-care-hint">
                {{
                    t(
                        'Preview for the selected equipment and current mood and bond. Gains are capped by genetic potential.',
                    )
                }}
            </p>
            <p v-if="energyGainCapped">
                {{
                    t(
                        'Energy is capped at 100%. The result shows what your dog needs now.',
                    )
                }}
            </p>
            <div v-if="costs.length || option.energy" class="pet-care-costs">
                <span v-if="option.energy"
                    ><Zap :size="14" aria-hidden="true" />{{
                        t('Energy cost')
                    }}: −{{ number(energyCostPercentage) }}%</span
                >
                <span v-for="effect in costs" :key="effect.label"
                    >{{ t(effect.label) }} {{ effect.amount }}%</span
                >
            </div>
        </section>
        <StatusEffects :effects="grantedEffects" preview />
        <p
            v-for="effect in recoveryEffects"
            :key="effect.code"
            class="pet-care-hint"
        >
            {{
                t(
                    'Recovery: {effect} lasts {count} min less after completion.',
                    {
                        effect: localized(effect.name),
                        count: number(
                            Math.ceil(
                                (option.statusRecovery[effect.code] ?? 0) / 60,
                            ),
                        ),
                    },
                )
            }}
        </p>
        <ItemRisks :risks="risks" :includes-training="trainingOnly" />
    </div>
</template>
