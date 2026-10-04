import { useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useServerClock } from '@/composables/useServerClock';
import { complete } from '@/routes/pets/care';
import type { PetCare } from '@/types/pet-care';

export function usePetCareActivity(
    petId: () => number,
    care: () => PetCare,
    autoComplete: () => boolean,
) {
    const { now } = useServerClock(() => care().serverNow);
    const mounted = ref(false);
    const completionFailed = ref(false);
    const finish = useForm({ token: '' });
    let lastCompletionAttempt: string | null = null;
    onMounted(() => {
        mounted.value = true;
    });
    onUnmounted(() => {
        finish.cancel();
    });
    const secondsLeft = (date?: string): number =>
        date
            ? Math.max(0, Math.ceil((Date.parse(date) - now.value) / 1000))
            : 0;
    const countdown = (seconds: number): string =>
        `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
    const readyToFinish = computed(
        () =>
            care().active !== null && secondsLeft(care().active?.endsAt) === 0,
    );
    const progress = computed(() => {
        const active = care().active;
        if (!active) return 0;
        const elapsed = now.value - Date.parse(active.startedAt);
        const total = Date.parse(active.endsAt) - Date.parse(active.startedAt);
        return total <= 0
            ? 100
            : Math.max(0, Math.min(100, (elapsed / total) * 100));
    });
    const busyMessage = computed(() =>
        completionFailed.value
            ? 'Could not apply the result. Please retry.'
            : readyToFinish.value
              ? 'Applying the activity result...'
              : 'Your dog is busy with another activity.',
    );
    const finishError = computed(() => Object.values(finish.errors).join(' '));

    function finishActivity(): void {
        const active = care().active;
        if (
            !active ||
            !readyToFinish.value ||
            care().blocked ||
            finish.processing
        )
            return;
        const token = active.token;
        lastCompletionAttempt = token;
        completionFailed.value = false;
        finish.token = token;
        finish.post(complete.url(petId()), {
            preserveScroll: true,
            only: ['pet', 'care'],
            onFinish: () => {
                completionFailed.value = care().active?.token === token;
            },
        });
    }

    watch(
        () =>
            mounted.value &&
            autoComplete() &&
            !care().blocked &&
            readyToFinish.value
                ? care().active?.token
                : null,
        (token) => {
            if (token && token !== lastCompletionAttempt) finishActivity();
        },
    );

    return {
        now,
        secondsLeft,
        countdown,
        progress,
        readyToFinish,
        busyMessage,
        completionFailed,
        finish,
        finishError,
        finishActivity,
    };
}
