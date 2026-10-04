<script setup lang="ts">
import { useGameEventPresentation } from '@/composables/useGameEventPresentation';
import { useI18n } from '@/composables/useI18n';
import type { CompetitionGear } from '@/types/competition-gear';

defineProps<{ gear: CompetitionGear }>();
const { t } = useI18n();
const { phaseLabel, modifier } = useGameEventPresentation();
</script>

<template>
    <div class="equipment-details">
        <h4>{{ t('Competition equipment') }}</h4>
        <dl class="shop-properties">
            <div>
                <dt>{{ t('Equipment slot') }}</dt>
                <dd>{{ t(`events.slot.${gear.slot}`) }}</dd>
            </div>
            <div>
                <dt>{{ t('Use phase') }}</dt>
                <dd>
                    {{ phaseLabel(gear.phase) }}
                </dd>
            </div>
            <div>
                <dt>{{ t('Compatible disciplines') }}</dt>
                <dd>
                    {{
                        gear.disciplines
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
                        gear.sizes.length
                            ? gear.sizes
                                  .map((size) => t(`events.size.${size}`))
                                  .join(', ')
                            : t('All sizes')
                    }}
                </dd>
            </div>
            <div v-for="(value, key) in gear.modifiers" :key="key">
                <dt>{{ t(`events.modifier.${key}`) }}</dt>
                <dd>
                    {{ modifier(value) }}
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
</template>
