<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, CircleHelp, GitBranch, Mars, Venus } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import DogCompetitionCredentials from '@/components/DogCompetitionCredentials.vue';
import { show } from '@/routes/pets';
import type { PedigreeNode } from '@/types/pet-pedigree';

defineProps<{
    node: PedigreeNode | null;
    relation: 'root' | 'father' | 'mother';
    depth: number;
    generations: number;
    rootId: number;
}>();
const { t, number } = useI18n();
const relations = { root: 'Selected dog', father: 'Father', mother: 'Mother' };
</script>

<template>
    <li class="pedigree-branch" :class="{ 'is-root': relation === 'root' }">
        <Link
            v-if="node"
            :href="show(node.pet.id, { query: { from_pedigree: rootId } })"
            class="pedigree-dog"
            :aria-label="
                t('{relationship}: {name}. View dog card', {
                    relationship: t(relations[relation]),
                    name: node.pet.name,
                })
            "
        >
            <span class="pedigree-relation">
                <component
                    :is="
                        relation === 'root'
                            ? GitBranch
                            : node.pet.sex === 'male'
                              ? Mars
                              : Venus
                    "
                    :size="14"
                    aria-hidden="true"
                />{{ t(relations[relation]) }}
            </span>
            <strong>{{ node.pet.name }}</strong>
            <span class="pedigree-breed">{{ node.pet.breed }}</span>
            <span class="pedigree-coat">{{ node.pet.coatColor }}</span>
            <DogCompetitionCredentials
                :exterior="node.pet.exterior"
                :titles="node.pet.titles"
                compact
            />
            <span class="pedigree-dog-footer">
                <span>{{
                    t('Generation {generation}', {
                        generation: number(node.pet.generation),
                    })
                }}</span>
                <ArrowUpRight :size="15" aria-hidden="true" />
            </span>
            <span v-if="node.pet.status !== 'active'" class="pedigree-archived">
                {{
                    t(
                        node.pet.status === 'retired'
                            ? 'Retired'
                            : 'In loving memory',
                    )
                }}
            </span>
        </Link>
        <div v-else class="pedigree-dog pedigree-unknown">
            <span class="pedigree-relation">
                <CircleHelp :size="14" aria-hidden="true" />{{
                    t(relations[relation])
                }}
            </span>
            <strong>{{ t('Unknown parent') }}</strong>
            <span class="pedigree-breed">{{
                t('No record in the pedigree')
            }}</span>
        </div>
        <ul
            v-if="node && depth < generations && (node.father || node.mother)"
            class="pedigree-parents"
            :data-generation="depth + 1"
            :aria-label="t('Parents of {name}', { name: node.pet.name })"
        >
            <PetPedigreeBranch
                :node="node.father"
                relation="father"
                :depth="depth + 1"
                :generations="generations"
                :root-id="rootId"
            />
            <PetPedigreeBranch
                :node="node.mother"
                relation="mother"
                :depth="depth + 1"
                :generations="generations"
                :root-id="rootId"
            />
        </ul>
    </li>
</template>
