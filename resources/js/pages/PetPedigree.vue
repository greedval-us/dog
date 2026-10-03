<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, GitBranch, MoveHorizontal } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import PetPedigreeBranch from '@/components/PetPedigreeBranch.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { show } from '@/routes/pets';
import type { PetPedigree } from '@/types/pet-pedigree';

const props = defineProps<{ pedigree: PetPedigree }>();
const { t, number } = useI18n();
const viewport = ref<HTMLElement | null>(null);
const availableGenerations = computed(() =>
    Math.min(3, props.pedigree.generations),
);
const visibleGenerations = ref(availableGenerations.value);
const generationLabels: Record<number, string> = {
    1: 'Parents',
    2: 'Parents and grandparents',
    3: 'Three generations',
};
function centerRoot(): void {
    if (viewport.value) {
        viewport.value.scrollLeft =
            (viewport.value.scrollWidth - viewport.value.clientWidth) / 2;
    }
}
async function initializeView(): Promise<void> {
    visibleGenerations.value = window.matchMedia('(max-width: 650px)').matches
        ? 1
        : availableGenerations.value;
    await nextTick();
    centerRoot();
}
onMounted(initializeView);
watch(visibleGenerations, async () => {
    await nextTick();
    centerRoot();
});
watch(
    () => props.pedigree.root.pet.id,
    async () => {
        await initializeView();
    },
);
</script>

<template>
    <div class="pedigree-page">
        <Head
            :title="t('Pedigree — {name}', { name: pedigree.root.pet.name })"
        />
        <div class="pet-public-heading">
            <Heading
                :title="
                    t('Pedigree — {name}', { name: pedigree.root.pet.name })
                "
                :description="
                    t(
                        'Follow the family branches and select a dog to see its card and attributes.',
                    )
                "
            />
            <Button as-child variant="secondary">
                <Link :href="show(pedigree.root.pet.id)">
                    <ArrowLeft :size="17" aria-hidden="true" />{{
                        t('Back to dog card')
                    }}
                </Link>
            </Button>
        </div>
        <SurfaceCard
            v-if="pedigree.hasAncestors"
            class="pedigree-surface"
            :data-generations="visibleGenerations"
        >
            <div class="pedigree-guide">
                <p>
                    <GitBranch :size="18" aria-hidden="true" />{{
                        t('Up to {generations} generations of ancestors', {
                            generations: number(pedigree.generations),
                        })
                    }}
                </p>
                <p id="pedigree-scroll-hint">
                    <MoveHorizontal :size="18" aria-hidden="true" />{{
                        t('Scroll sideways to explore every branch.')
                    }}
                </p>
                <label
                    class="pedigree-generation-picker"
                    for="pedigree-generations"
                >
                    <span>{{ t('Ancestor generations') }}</span>
                    <select
                        id="pedigree-generations"
                        v-model.number="visibleGenerations"
                        class="dog-work-select"
                    >
                        <option
                            v-for="generation in availableGenerations"
                            :key="generation"
                            :value="generation"
                        >
                            {{ t(generationLabels[generation]) }}
                        </option>
                    </select>
                </label>
            </div>
            <div
                ref="viewport"
                class="pedigree-viewport"
                role="region"
                :aria-label="
                    t('Family tree of {name}', { name: pedigree.root.pet.name })
                "
                :aria-describedby="
                    visibleGenerations > 1 ? 'pedigree-scroll-hint' : undefined
                "
                tabindex="0"
            >
                <ul class="pedigree-tree" :aria-label="t('Pedigree')">
                    <PetPedigreeBranch
                        :node="pedigree.root"
                        relation="root"
                        :depth="0"
                        :generations="pedigree.generations"
                        :root-id="pedigree.root.pet.id"
                    />
                </ul>
            </div>
            <p class="pedigree-depth-note">
                {{
                    t(
                        'For earlier generations, open an ancestor’s card and follow their pedigree.',
                    )
                }}
            </p>
        </SurfaceCard>
        <SurfaceCard v-else class="pedigree-empty">
            <GitBranch :size="36" :stroke-width="1.5" aria-hidden="true" />
            <h2>{{ t('No known ancestors yet') }}</h2>
            <p>
                {{
                    t(
                        'There are no parent records for this dog. A pedigree becomes available for dogs born through breeding.',
                    )
                }}
            </p>
        </SurfaceCard>
    </div>
</template>
