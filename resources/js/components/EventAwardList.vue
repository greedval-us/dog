<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { groupEventAwards } from '@/lib/eventAwards';
import type { EventTitle } from '@/types/game-event';

const props = defineProps<{ awards?: EventTitle[] }>();
const { t } = useI18n();
const groups = computed(() => groupEventAwards(props.awards ?? []));
</script>

<template>
    <div v-if="groups.length" class="event-award-groups">
        <section
            v-for="group in groups"
            :key="group.kind"
            :aria-label="t(group.label)"
        >
            <strong class="event-award-kind">{{ t(group.label) }}</strong>
            <ul class="event-entry-awards">
                <li
                    v-for="(award, index) in group.entries"
                    :key="`${award.code ?? award.name}:${index}`"
                >
                    {{ award.name }}
                </li>
            </ul>
        </section>
    </div>
</template>
