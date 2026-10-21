<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ChevronDown, Target } from '@lucide/vue';
import { computed } from 'vue';
import PetTitleGoal from '@/components/PetTitleGoal.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { index } from '@/routes/game-events';
import type { PetTitleProgress } from '@/types/pet-title-progress';

const props = defineProps<{ progress: PetTitleProgress }>();
const { t, number, locale } = useI18n();
const highlightedGoals = computed(() => props.progress.goals.slice(0, 3));
const otherGoals = computed(() => props.progress.goals.slice(3));
const updatedAt = computed(() =>
    new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Europe/Moscow',
    }).format(new Date(props.progress.generatedAt)),
);
</script>

<template>
    <section class="pet-title-progress" :aria-label="t('Title progress')">
        <div class="pet-title-progress-heading">
            <h3>
                <Target :size="21" aria-hidden="true" />{{
                    t('Title progress')
                }}
            </h3>
            <p class="pet-career-hint">
                {{
                    t(
                        'Certificates, missing conditions and events for your dog’s next titles.',
                    )
                }}
            </p>
        </div>
        <div v-if="progress.goals.length" class="pet-title-goals">
            <PetTitleGoal
                v-for="goal in highlightedGoals"
                :key="goal.key"
                :goal="goal"
            />
            <details v-if="otherGoals.length" class="pet-title-more">
                <summary>
                    {{ t('Other title goals')
                    }}<span>{{ number(otherGoals.length) }}</span
                    ><ChevronDown
                        :size="17"
                        class="pet-title-chevron"
                        aria-hidden="true"
                    />
                </summary>
                <div class="pet-title-goals">
                    <PetTitleGoal
                        v-for="goal in otherGoals"
                        :key="goal.key"
                        :goal="goal"
                    />
                </div>
            </details>
        </div>
        <div v-else class="pet-title-empty">
            <p>{{ t('No title goals are available yet.') }}</p>
            <Button as-child variant="outline" size="sm"
                ><Link :href="index()"
                    >{{ t('View event calendar')
                    }}<ArrowRight :size="15" aria-hidden="true" /></Link
            ></Button>
        </div>
        <p class="pet-career-hint">
            {{ t('Progress updated: {date}', { date: updatedAt }) }}
        </p>
    </section>
</template>
