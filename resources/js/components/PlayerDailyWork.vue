<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { BriefcaseBusiness, Check, CircleCheck, Coins, Gem } from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';
import InputError from '@/components/InputError.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { store } from '@/routes/daily-work';
import type { DailyWork } from '@/types/player';

const props = defineProps<{ work: DailyWork }>();
const { t, locale, number } = useI18n();
const form = useForm({ work_type_id: 0 });
const resetTime = computed(() =>
    new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: props.work.timezone,
        timeZoneName: 'short',
    }).format(new Date(props.work.resetsAt)),
);
let refreshTimer: ReturnType<typeof setInterval> | undefined;
let refreshing = false;

onMounted(() => {
    refreshTimer = setInterval(() => {
        if (
            !refreshing &&
            !form.processing &&
            Date.now() >= Date.parse(props.work.resetsAt)
        ) {
            refreshing = true;
            router.reload({
                only: ['dailyWork'],
                onFinish: () => {
                    refreshing = false;
                },
            });
        }
    }, 30000);
});
onUnmounted(() => clearInterval(refreshTimer));

function workShift(id: number) {
    form.work_type_id = id;
    form.submit(store(), { preserveScroll: true });
}
</script>

<template>
    <SurfaceCard class="player-work">
        <div class="player-work-heading">
            <div class="player-work-title">
                <span class="player-work-icon"
                    ><BriefcaseBusiness :size="24" aria-hidden="true"
                /></span>
                <div>
                    <h2>{{ t('Daily work') }}</h2>
                    <p>
                        {{ t('A little help, a little reward — every day.') }}
                    </p>
                </div>
            </div>
            <span class="player-work-frequency">{{
                t('One shift a day')
            }}</span>
        </div>
        <div class="player-work-layout">
            <div class="player-work-jobs">
                <div
                    v-if="work.completedToday"
                    class="player-work-complete"
                    role="status"
                >
                    <CircleCheck :size="24" aria-hidden="true" />
                    <div>
                        <h3>{{ t('Today’s shift is complete') }}</h3>
                        <p>
                            {{ t('Earned today') }}:
                            <span class="player-work-reward"
                                ><Coins :size="17" aria-hidden="true" />{{
                                    t('{amount} coins', {
                                        amount: number(work.earnedCoins),
                                    })
                                }}</span
                            >
                            <span
                                v-if="work.earnedGems"
                                class="player-work-reward"
                                ><Gem :size="17" aria-hidden="true" />{{
                                    t('{amount} gems', {
                                        amount: number(work.earnedGems),
                                    })
                                }}</span
                            >
                        </p>
                    </div>
                </div>
                <template v-else>
                    <form
                        v-for="job in work.jobs"
                        :key="job.id"
                        class="player-work-job"
                        @submit.prevent="workShift(job.id)"
                    >
                        <h3>{{ job.name }}</h3>
                        <p>{{ job.description }}</p>
                        <div class="player-work-job-footer">
                            <span class="player-work-reward"
                                ><Coins
                                    :size="20"
                                    class="coin-icon"
                                    aria-hidden="true"
                                />{{
                                    t('{amount} coins', {
                                        amount: number(job.coins),
                                    })
                                }}</span
                            >
                            <Button
                                type="submit"
                                :disabled="form.processing || !work.canWork"
                                ><BriefcaseBusiness
                                    :size="18"
                                    aria-hidden="true"
                                />{{
                                    form.processing &&
                                    form.work_type_id === job.id
                                        ? t('Working...')
                                        : t('Go to work')
                                }}</Button
                            >
                        </div>
                        <p v-if="job.gems" class="player-work-bonus">
                            <Gem :size="16" aria-hidden="true" />{{
                                t('Day five bonus: {amount} gems', {
                                    amount: number(job.gems),
                                })
                            }}
                        </p>
                    </form>
                    <p v-if="!work.jobs.length">
                        {{
                            t(
                                'There is no work available right now. Check back later.',
                            )
                        }}
                    </p>
                    <p v-else-if="!work.canWork">
                        {{ t('Work is unavailable for this account.') }}
                    </p>
                    <InputError
                        :message="form.errors.work_type_id"
                        role="alert"
                    />
                </template>
            </div>
            <div class="player-work-streak">
                <div class="player-work-streak-heading">
                    <h3>{{ t('Five days of good deeds') }}</h3>
                    <span
                        >{{ number(work.progress) }} /
                        {{ number(work.streakLength) }}</span
                    >
                </div>
                <ol class="player-work-days" :aria-label="t('Work streak')">
                    <li
                        v-for="day in work.streakLength"
                        :key="day"
                        :class="{
                            'is-complete': day <= work.progress,
                            'is-next':
                                !work.completedToday &&
                                day === work.progress + 1,
                        }"
                        :aria-current="
                            !work.completedToday && day === work.progress + 1
                                ? 'step'
                                : undefined
                        "
                    >
                        <span class="player-work-day-icon"
                            ><Check
                                v-if="day <= work.progress"
                                :size="19"
                                aria-hidden="true"
                            /><Gem
                                v-else-if="day === work.streakLength"
                                :size="19"
                                aria-hidden="true"
                            /><span v-else>{{ number(day) }}</span></span
                        >
                        <span>{{ t('Day {day}', { day: number(day) }) }}</span
                        ><span v-if="day <= work.progress" class="sr-only">{{
                            t('Completed')
                        }}</span>
                    </li>
                </ol>
                <p>
                    {{
                        t(
                            'Work five days in a row to earn gems. Missing a day restarts the streak.',
                        )
                    }}
                </p>
                <p
                    v-if="
                        work.completedToday &&
                        work.progress === work.streakLength
                    "
                    class="player-work-bonus"
                >
                    {{ t('Five days complete! A new streak starts tomorrow.') }}
                </p>
                <p class="player-work-reset">
                    {{ t('New shift at {time}', { time: resetTime }) }}
                </p>
            </div>
        </div>
    </SurfaceCard>
</template>
