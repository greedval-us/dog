<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';
import ItemBonuses from '@/components/ItemBonuses.vue';
import ItemRisks from '@/components/ItemRisks.vue';
import type { StatusEffect, ItemRisk } from '@/types/pet-care';
import type { EventCompetitionGear } from '@/types/game-event';

const props = defineProps<{
    characteristics: Record<string, unknown>;
    bonuses?: Record<string, number>;
    grantedEffects?: StatusEffect[];
    risks?: ItemRisk[];
    competition?: EventCompetitionGear | null;
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
        <div v-if="competition" class="equipment-details">
            <h4>{{ t('Competition equipment') }}</h4>
            <dl class="shop-properties">
                <div>
                    <dt>{{ t('Equipment slot') }}</dt>
                    <dd>{{ t(`events.slot.${competition.slot}`) }}</dd>
                </div>
                <div>
                    <dt>{{ t('Use phase') }}</dt>
                    <dd>
                        {{
                            t(
                                competition.phase === 'preparation'
                                    ? 'Preparation only'
                                    : 'During the performance',
                            )
                        }}
                    </dd>
                </div>
                <div>
                    <dt>{{ t('Compatible disciplines') }}</dt>
                    <dd>
                        {{
                            competition.disciplines
                                .map((discipline) =>
                                    t(`events.discipline.${discipline}`),
                                )
                                .join(', ')
                        }}
                    </dd>
                </div>
                <div>
                    <dt>{{ t('Dog sizes') }}</dt>
                    <dd>
                        {{
                            competition.sizes.length
                                ? competition.sizes
                                      .map((size) => t(`events.size.${size}`))
                                      .join(', ')
                                : t('All sizes')
                        }}
                    </dd>
                </div>
                <div v-for="(value, key) in competition.modifiers" :key="key">
                    <dt>{{ t(`events.modifier.${key}`) }}</dt>
                    <dd>
                        {{ value > 0 ? '+' : '' }}{{ number(value * 100) }}%
                    </dd>
                </div>
            </dl>
            <p class="event-note">
                {{
                    t(
                        'Choose equipment to support your dog’s weaker sides. No item is best for every course.',
                    )
                }}
            </p>
        </div>
    </div>
</template>
