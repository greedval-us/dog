<script setup lang="ts">
import { Dna, Sparkles } from '@lucide/vue';
import BreedingParentCard from '@/components/BreedingParentCard.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import { statLabels } from '@/lib/petLabels';
import type { BreedingForecast } from '@/types/breeding';

defineProps<{ forecast: BreedingForecast }>();
const { t, number } = useI18n();
</script>

<template>
    <SurfaceCard class="breeding-preview">
        <template #header
            ><h2 class="breeding-section-title">
                <Dna :size="22" aria-hidden="true" />{{ t('Puppy forecast') }}
            </h2></template
        >
        <div class="breeding-parents">
            <div>
                <h3 class="breeding-label">{{ t('Father') }}</h3>
                <BreedingParentCard :pet="forecast.father" />
            </div>
            <div>
                <h3 class="breeding-label">{{ t('Mother') }}</h3>
                <BreedingParentCard :pet="forecast.mother" />
            </div>
        </div>
        <div class="breeding-forecast-grid">
            <section>
                <h3>{{ t('Possible genetic potential') }}</h3>
                <dl class="breeding-ranges">
                    <div v-for="range in forecast.ranges" :key="range.stat">
                        <dt>{{ t(statLabels[range.stat]) }}</dt>
                        <dd>{{ number(range.min) }}–{{ number(range.max) }}</dd>
                    </div>
                </dl>
                <p class="field-hint">
                    {{
                        t(
                            'The forecast includes parental training and individual variation. Each puppy starts at 20% of its own genetic potential.',
                        )
                    }}
                </p>
            </section>
            <section>
                <h3>{{ t('Coat color chances') }}</h3>
                <p class="field-hint">
                    {{
                        t(
                            'Game inheritance probabilities include the coats of known ancestors across three generations. A darker family line increases the chance of a dark coat.',
                        )
                    }}
                </p>
                <div
                    class="breeding-table-scroll"
                    tabindex="0"
                    :aria-label="t('Coat color chances')"
                >
                    <table class="breeding-color-table">
                        <caption class="sr-only">
                            {{
                                t('Coat color chances')
                            }}
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ t('Coat color') }}</th>
                                <th scope="col">{{ t('Chance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="color in forecast.colors"
                                :key="color.code"
                            >
                                <th scope="row">
                                    {{ color.label
                                    }}<span
                                        v-if="color.rare"
                                        class="breeding-rare"
                                        ><Sparkles
                                            :size="12"
                                            aria-hidden="true"
                                        />{{ t('Rare') }}</span
                                    >
                                </th>
                                <td>{{ number(color.chance) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <details class="breeding-genetics-note">
            <summary>{{ t('How training affects inheritance') }}</summary>
            <p>
                {{
                    t(
                        'At 70% of a parent’s potential, that parent adds one 3–5% step; at 90%, two. Below 50%, potential decreases smoothly by up to 15%. The base is the average of both parents with individual variation of ±2%.',
                    )
                }}
            </p>
        </details>
        <slot />
    </SurfaceCard>
</template>
