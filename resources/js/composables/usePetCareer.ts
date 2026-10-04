import { router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { dashboard } from '@/routes';
import type { PetCareer } from '@/types/pet-career';

export function usePetCareer(
    petId: () => number,
    career: () => PetCareer | null | undefined,
) {
    const loading = ref(false);
    const failed = ref(false);
    const currentCursor = ref<string | null>(null);
    const data = computed(() =>
        career()?.petId === petId() ? career() : undefined,
    );
    let cancel: (() => void) | undefined;
    let alive = false;

    function load(cursor: string | null = null): void {
        if (loading.value || !alive) return;
        loading.value = true;
        failed.value = false;
        currentCursor.value = cursor;
        router.get(
            dashboard.url({
                query: { pet: petId(), career_cursor: cursor ?? undefined },
            }),
            {},
            {
                only: ['career'],
                async: true,
                preserveState: true,
                preserveScroll: true,
                preserveErrors: true,
                replace: true,
                onCancelToken: (token) => {
                    cancel = () => token.cancel();
                },
                onSuccess: (page) => {
                    if (!alive) return;
                    const result = page.props.career as
                        | PetCareer
                        | null
                        | undefined;
                    failed.value = result?.petId !== petId();
                },
                onError: () => {
                    if (alive) failed.value = true;
                },
                onHttpException: () => {
                    if (alive) failed.value = true;
                    return false;
                },
                onNetworkError: () => {
                    if (alive) failed.value = true;
                    return false;
                },
                onFinish: () => {
                    if (!alive) return;
                    loading.value = false;
                    cancel = undefined;
                },
            },
        );
    }

    onMounted(() => {
        alive = true;
        if (!data.value) load();
    });
    onUnmounted(() => {
        alive = false;
        cancel?.();
    });

    return {
        data,
        loading,
        failed,
        load,
        retry: () => load(currentCursor.value),
    };
}
