<script setup lang="ts">
import { Brush, CircleDot, Footprints, Moon, Soup } from '@lucide/vue';
import { useId } from 'vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();
const noteId = useId();
const actions = [
    { label: 'Feed', icon: Soup, tone: 'sage' },
    { label: 'Walk', icon: Footprints, tone: 'blue' },
    { label: 'Play', icon: CircleDot, tone: 'amber' },
    { label: 'Groom', icon: Brush, tone: 'blue' },
    { label: 'Sleep', icon: Moon, tone: 'violet' },
] as const;
</script>

<template>
    <SurfaceCard class="pet-care">
        <template #header
            ><div class="pet-section-heading">
                <h2>{{ t('Quick actions') }}</h2>
                <span class="coming-soon-badge">{{ t('Soon') }}</span>
            </div></template
        >
        <div class="pet-care-actions">
            <Button
                v-for="action in actions"
                :key="action.label"
                type="button"
                variant="plain"
                class="pet-care-action"
                :class="'pet-tone-' + action.tone"
                disabled
                :aria-describedby="noteId"
                :title="
                    t('{feature} — coming soon', { feature: t(action.label) })
                "
            >
                <span><component :is="action.icon" :size="26" /></span
                >{{ t(action.label) }}
            </Button>
        </div>
        <p :id="noteId" class="pet-feature-note">
            {{ t('Care actions are coming soon.') }}
        </p>
    </SurfaceCard>
</template>
