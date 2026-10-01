<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, Clock, Coins, GraduationCap, Sparkles } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { statLabels } from '@/lib/petLabels';
import { store } from '@/routes/pets/skills';
import type { PlayerPet } from '@/types/pet';
import type { PetSkill, PetSkills } from '@/types/pet-skill';

const props = defineProps<{ pet: PlayerPet; data: PetSkills }>();
const { t, number, locale } = useI18n();
const selectedLevels = reactive<Record<number, number>>({});
const pendingSkill = ref<number | null>(null);
const form = useForm({ skill_id: 0, level: 1, expected_price: 0, token: '' });
const error = computed(() => Object.values(form.errors).join(' '));
let lastAttempt = '';

function selectedLevel(skill: PetSkill): number {
    return selectedLevels[skill.id] ?? Math.min(skill.level + 1, 5);
}

function nextLesson(skill: PetSkill) {
    return skill.level < 5 ? skill.levels[skill.level] : null;
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function coolingDown(skill: PetSkill): boolean {
    return (
        !!skill.cooldownUntil &&
        Date.parse(skill.cooldownUntil) > Date.parse(props.data.serverNow)
    );
}

function train(skill: PetSkill) {
    const lesson = nextLesson(skill);
    if (!skill.canTrain || !lesson || form.processing) return;
    const signature = `${skill.id}:${skill.level + 1}:${lesson.price}`;
    if (signature !== lastAttempt) {
        form.token = props.data.token;
        lastAttempt = signature;
    }
    form.skill_id = skill.id;
    form.level = skill.level + 1;
    form.expected_price = lesson.price;
    pendingSkill.value = skill.id;
    form.clearErrors();
    form.submit(store(props.pet.id), {
        preserveScroll: true,
        only: ['pet', 'skills', 'auth', 'care'],
        onSuccess: () => {
            delete selectedLevels[skill.id];
            lastAttempt = '';
        },
        onFinish: () => {
            pendingSkill.value = null;
        },
    });
}
</script>

<template>
    <section class="pet-skills" :aria-label="t('Skills')">
        <SurfaceCard
            :title="t('Lessons with an instructor')"
            class="pet-skills-intro"
        >
            <p>
                {{
                    t(
                        'Pay an instructor in coins to teach your dog one skill level. Wait 24 hours before the next lesson for the same skill.',
                    )
                }}
            </p>
            <p>
                {{
                    t(
                        "Requirements use this dog's genetic maximum. The required value is rounded up to a whole point.",
                    )
                }}
            </p>
            <p>
                {{
                    t(
                        'Learned levels stay with your dog. A skill becomes inactive when attributes fall below its requirements and returns when they recover.',
                    )
                }}
            </p>
        </SurfaceCard>
        <p v-if="error" class="pet-skill-error" role="alert">{{ error }}</p>
        <p v-if="!data.skills.length" class="pet-feature-note">
            {{ t('No skill lessons are available yet.') }}
        </p>
        <div v-else class="pet-skills-grid">
            <SurfaceCard
                v-for="skill in data.skills"
                :key="skill.id"
                class="pet-skill-card"
            >
                <div class="pet-skill-heading">
                    <span class="pet-skill-icon"
                        ><Sparkles :size="22" aria-hidden="true"
                    /></span>
                    <div>
                        <h3>{{ skill.name }}</h3>
                        <span>{{
                            t('Level {level} / 5', {
                                level: number(skill.level),
                            })
                        }}</span>
                    </div>
                    <span
                        class="pet-skill-status"
                        :class="{ 'is-active': skill.active }"
                    >
                        <Check
                            v-if="skill.active"
                            :size="14"
                            aria-hidden="true"
                        />
                        {{
                            skill.level === 0
                                ? t('Not learned')
                                : skill.active
                                  ? t('Active skill')
                                  : t('Inactive skill')
                        }}
                    </span>
                </div>
                <p class="pet-skill-description">{{ skill.description }}</p>
                <div
                    class="pet-skill-levels"
                    :aria-label="t('View skill level requirements')"
                >
                    <button
                        v-for="(level, index) in skill.levels"
                        :key="index"
                        type="button"
                        :aria-pressed="selectedLevel(skill) === index + 1"
                        :aria-label="
                            t('View level {level}', {
                                level: number(index + 1),
                            })
                        "
                        :class="{ 'is-learned': index < skill.level }"
                        :disabled="form.processing"
                        @click="selectedLevels[skill.id] = index + 1"
                    >
                        <Check
                            v-if="index < skill.level"
                            :size="14"
                            aria-hidden="true"
                        />{{ number(index + 1) }}
                    </button>
                </div>
                <div
                    v-if="skill.levels[selectedLevel(skill) - 1]"
                    class="pet-skill-requirements"
                >
                    <h4>
                        {{
                            t('Requirements for level {level}', {
                                level: number(selectedLevel(skill)),
                            })
                        }}
                    </h4>
                    <dl>
                        <div
                            v-for="(minimum, stat) in skill.levels[
                                selectedLevel(skill) - 1
                            ].requirements"
                            :key="stat"
                        >
                            <dt>
                                {{ t(statLabels[stat]) }} ·
                                {{
                                    t('{percent}% of maximum', {
                                        percent: number(
                                            skill.levels[
                                                selectedLevel(skill) - 1
                                            ].requirementPercentages[stat] ?? 0,
                                        ),
                                    })
                                }}
                            </dt>
                            <dd
                                :class="{
                                    'is-low':
                                        pet.stats[stat].value < (minimum ?? 0),
                                }"
                            >
                                <Check
                                    v-if="
                                        pet.stats[stat].value >= (minimum ?? 0)
                                    "
                                    :size="14"
                                    aria-hidden="true"
                                />
                                {{ number(pet.stats[stat].value) }} /
                                {{ number(minimum ?? 0) }}
                            </dd>
                        </div>
                    </dl>
                    <p>
                        <Coins
                            :size="15"
                            class="coin-icon"
                            aria-hidden="true"
                        />{{
                            t('Instructor fee: {amount} coins', {
                                amount: number(
                                    skill.levels[selectedLevel(skill) - 1]
                                        .price,
                                ),
                            })
                        }}
                    </p>
                </div>
                <div
                    v-if="skill.level > 0 && !skill.active"
                    class="pet-skill-inactive"
                >
                    <p>
                        {{
                            t(
                                'This learned skill is temporarily inactive. Restore the requirements for your current level.',
                            )
                        }}
                    </p>
                    <span
                        v-for="(minimum, stat) in skill.activeRequirements"
                        :key="stat"
                    >
                        {{ t(statLabels[stat]) }}:
                        {{ number(pet.stats[stat].value) }} /
                        {{ number(minimum ?? 0) }}
                        ·
                        {{
                            t('{percent}% of maximum', {
                                percent: number(
                                    skill.activeRequirementPercentages[stat] ??
                                        0,
                                ),
                            })
                        }}
                    </span>
                </div>
                <div class="pet-skill-footer">
                    <p
                        v-if="coolingDown(skill) && skill.cooldownUntil"
                        class="pet-skill-cooldown"
                    >
                        <Clock :size="15" aria-hidden="true" />{{
                            t('Next lesson: {date}', {
                                date: formatDate(skill.cooldownUntil),
                            })
                        }}
                    </p>
                    <p v-if="skill.reason" class="pet-skill-reason">
                        {{ t(skill.reason) }}
                    </p>
                    <Button
                        v-if="nextLesson(skill)"
                        :disabled="!skill.canTrain || form.processing"
                        @click="train(skill)"
                    >
                        <GraduationCap :size="17" aria-hidden="true" />
                        {{
                            pendingSkill === skill.id && form.processing
                                ? t('Learning…')
                                : t('Learn level {level} · {amount} coins', {
                                      level: number(skill.level + 1),
                                      amount: number(
                                          nextLesson(skill)?.price ?? 0,
                                      ),
                                  })
                        }}
                    </Button>
                </div>
            </SurfaceCard>
        </div>
    </section>
</template>
