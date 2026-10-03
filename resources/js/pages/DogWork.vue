<script setup lang="ts">
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    Clock,
    Coins,
    Gem,
    PawPrint,
    Users,
    Zap,
} from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OwnedDogSelector from '@/components/OwnedDogSelector.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { index, store, complete } from '@/routes/dog-work';
import { dashboard } from '@/routes';
import { index as kennel } from '@/routes/kennel';
import type { DogWorkBoard, DogWorkOffer } from '@/types/dog-work';

const props = defineProps<{ board: DogWorkBoard }>();
const { t, number, locale } = useI18n();
const form = useForm({
    pet_id: props.board.selectedPetId,
    offer_id: 0,
    token: props.board.token,
});
const finish = useForm({ token: '' });
const moving = ref(false);
const errorPanel = ref<HTMLElement | null>(null);
const localNow = ref(Date.now());
const offset = ref(Date.parse(props.board.serverNow) - Date.now());
const timestamp = computed(() => localNow.value + offset.value);
const pending = computed(
    () => form.processing || finish.processing || moving.value,
);
const expired = computed(
    () => timestamp.value >= Date.parse(props.board.resetsAt),
);
const errors = computed(() => [
    ...Object.values(form.errors),
    ...Object.values(finish.errors),
]);
const resetsAt = computed(() =>
    new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: props.board.timezone,
    }).format(new Date(props.board.resetsAt)),
);
useIntervalFn(() => {
    localNow.value = Date.now();
}, 1000);
const poll = usePoll(30_000, { only: ['board'] }, { mode: 'rest' });
watch(pending, (value) => {
    if (value) poll.stop();
    else poll.start();
});
watch(
    () => props.board,
    (board) => {
        offset.value = Date.parse(board.serverNow) - Date.now();
        form.pet_id = board.selectedPetId;
        form.token = board.token;
    },
);
function chooseDog(pet: number) {
    if (pending.value) return;
    moving.value = true;
    form.clearErrors();
    finish.clearErrors();
    router.get(
        index({ query: { pet } }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                moving.value = false;
            },
        },
    );
}
function startWork(offer: DogWorkOffer) {
    if (pending.value || expired.value || offer.reason || !form.pet_id) return;
    form.offer_id = offer.id;
    finish.clearErrors();
    form.submit(store(), {
        preserveScroll: true,
        onError: () => nextTick(() => errorPanel.value?.focus()),
    });
}
function collect(token: string) {
    if (pending.value) return;
    finish.token = token;
    form.clearErrors();
    finish.submit(complete(), {
        preserveScroll: true,
        onError: () => nextTick(() => errorPanel.value?.focus()),
    });
}
function secondsLeft(endsAt: string) {
    return Math.max(
        0,
        Math.ceil((Date.parse(endsAt) - timestamp.value) / 1000),
    );
}
function countdown(seconds: number) {
    return [
        Math.floor(seconds / 3600),
        Math.floor(seconds / 60) % 60,
        seconds % 60,
    ]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}
</script>

<template>
    <div class="dog-work-page">
        <Head :title="t('Work with a dog')" />
        <Heading
            :title="t('Work with a dog')"
            :description="t('Your dog’s skills open new ways to earn.')"
        />
        <SurfaceCard class="dog-work-intro">
            <div class="dog-work-intro-heading">
                <span class="player-work-icon"
                    ><BriefcaseBusiness aria-hidden="true"
                /></span>
                <div>
                    <h2>{{ t('Today’s job board') }}</h2>
                    <p>
                        {{
                            t('New jobs at {time} (Moscow time)', {
                                time: resetsAt,
                            })
                        }}
                    </p>
                </div>
            </div>
            <p>
                {{
                    t(
                        'A shared daily selection with limited places. Each player can take each job once.',
                    )
                }}
            </p>
            <p>
                {{
                    t(
                        'Your dog stays busy until you collect the reward after the timer ends.',
                    )
                }}
            </p>
            <OwnedDogSelector
                v-if="board.dogs.length"
                id="work-dog"
                :dogs="board.dogs"
                :selected-pet-id="board.selectedPetId"
                select-class="dog-work-select"
                :disabled="pending"
                show-status
                @select="chooseDog"
            />
            <div v-else class="dog-work-empty">
                <p>{{ t('Choose a dog to take a job.') }}</p>
                <Button as-child
                    ><Link :href="kennel()">{{
                        t('Visit the kennel')
                    }}</Link></Button
                >
            </div>
            <p v-if="expired" role="status">
                {{ t('Updating today’s jobs...') }}
            </p>
            <div
                v-if="errors.length"
                ref="errorPanel"
                role="alert"
                tabindex="-1"
            >
                <InputError
                    v-for="message in errors"
                    :key="message"
                    :message="message"
                />
            </div>
        </SurfaceCard>

        <SurfaceCard
            v-if="board.shifts.length"
            :title="t('Jobs in progress')"
            class="dog-work-active"
        >
            <article
                v-for="shift in board.shifts"
                :key="shift.token"
                class="dog-work-shift"
            >
                <div>
                    <h3>{{ shift.name }}</h3>
                    <Link
                        :href="dashboard({ query: { pet: shift.petId } })"
                        class="text-link"
                    >
                        <PawPrint :size="16" aria-hidden="true" />{{
                            shift.petName
                        }}
                    </Link>
                </div>
                <span class="player-work-reward"
                    ><Coins :size="17" aria-hidden="true" />{{
                        number(shift.coins)
                    }}
                    <template v-if="shift.gems"
                        ><Gem :size="17" aria-hidden="true" />{{
                            number(shift.gems)
                        }}</template
                    >
                </span>
                <div class="dog-work-shift-action">
                    <span
                        v-if="secondsLeft(shift.endsAt)"
                        class="dog-work-timer"
                    >
                        <Clock :size="17" aria-hidden="true" />{{
                            countdown(secondsLeft(shift.endsAt))
                        }}
                    </span>
                    <Button
                        v-else
                        :disabled="pending"
                        @click="collect(shift.token)"
                    >
                        {{
                            t(
                                finish.processing &&
                                    finish.token === shift.token
                                    ? 'Finishing...'
                                    : 'Collect reward',
                            )
                        }}
                    </Button>
                </div>
            </article>
        </SurfaceCard>

        <div
            v-if="board.offers.length"
            class="dog-work-grid"
            :aria-busy="pending"
        >
            <SurfaceCard
                v-for="offer in board.offers"
                :key="offer.id"
                class="dog-work-job"
                :title="offer.name"
                :description="offer.description"
            >
                <dl class="dog-work-details">
                    <div>
                        <dt>{{ t('Required skill') }}</dt>
                        <dd>
                            {{ offer.skill }} ·
                            {{
                                t('Level {level}', { level: offer.skillLevel })
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ t('Duration') }}</dt>
                        <dd>
                            <Clock :size="16" aria-hidden="true" />{{
                                offer.duration < 60
                                    ? t('{seconds} sec', {
                                          seconds: number(offer.duration),
                                      })
                                    : t('{minutes} min', {
                                          minutes: number(offer.duration / 60),
                                      })
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ t('Energy cost') }}</dt>
                        <dd>
                            <Zap :size="16" aria-hidden="true" />{{
                                number(offer.energy)
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ t('Places left') }}</dt>
                        <dd>
                            <Users :size="16" aria-hidden="true" />{{
                                number(offer.places)
                            }}
                            / {{ number(offer.limit) }}
                        </dd>
                    </div>
                </dl>
                <div class="dog-work-job-footer">
                    <span class="player-work-reward"
                        ><Coins :size="18" aria-hidden="true" />{{
                            number(offer.coins)
                        }}
                        <template v-if="offer.gems"
                            ><Gem :size="18" aria-hidden="true" />{{
                                number(offer.gems)
                            }}</template
                        >
                    </span>
                    <Button
                        :disabled="pending || expired || Boolean(offer.reason)"
                        @click="startWork(offer)"
                    >
                        {{
                            t(
                                offer.status === 'completed'
                                    ? 'Completed'
                                    : offer.status === 'cancelled'
                                      ? 'Cancelled'
                                      : offer.status === 'started'
                                        ? 'In progress'
                                        : form.processing &&
                                            form.offer_id === offer.id
                                          ? 'Starting...'
                                          : 'Take job',
                            )
                        }}
                    </Button>
                </div>
                <p v-if="offer.reason" class="dog-work-reason">
                    {{ t(offer.reason) }}
                </p>
            </SurfaceCard>
        </div>
        <SurfaceCard
            v-else
            :title="t('No jobs available today')"
            :description="t('Check back tomorrow for a new selection.')"
        />
    </div>
</template>
