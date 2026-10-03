import { useHttp } from '@inertiajs/vue3';
import { onUnmounted, ref } from 'vue';
import { careItems } from '@/routes';
import type {
    CareCategory,
    CareItem,
    CareItemSelection,
    CareOption,
} from '@/types/pet-care';

export function useCareItems(
    selected: () => CareOption | undefined,
    selection: () => CareItemSelection,
) {
    const items = ref<CareItem[]>([]);
    const cursors = ref<Partial<Record<CareCategory, string | null>>>({});
    const failed = ref(false);
    const loading = ref(false);
    const request = useHttp<
        Record<string, never>,
        { items: CareItem[]; nextCursor: string | null }
    >({});
    let generation = 0;

    function cancel(): void {
        generation++;
        request.cancel();
        loading.value = false;
    }

    function itemsFor(category: CareCategory): CareItem[] {
        const uses = selected()?.uses[category] ?? 1;
        return items.value.filter(
            (item) => item.category === category && item.remainingUses >= uses,
        );
    }

    async function load(category?: CareCategory): Promise<void> {
        cancel();
        const currentGeneration = generation;
        failed.value = false;
        const option = selected();
        if (!option) return;
        if (!category) {
            items.value = [];
            cursors.value = {};
        }
        loading.value = true;
        try {
            for (const key of category
                ? [category]
                : [...option.requirements, ...option.optional]) {
                const response = await request.get(
                    careItems.url({
                        query: {
                            category: key,
                            uses: option.uses[key] ?? 1,
                            cursor: category ? cursors.value[key] : undefined,
                        },
                    }),
                );
                if (currentGeneration !== generation) return;
                const known = new Set(items.value.map((item) => item.id));
                items.value.push(
                    ...response.items.filter((item) => !known.has(item.id)),
                );
                cursors.value[key] = response.nextCursor;
                if (
                    option.requirements.includes(key) &&
                    !selection()[key] &&
                    response.items[0]
                ) {
                    selection()[key] = response.items[0].id;
                }
            }
        } catch {
            if (currentGeneration === generation) failed.value = true;
        } finally {
            if (currentGeneration === generation) loading.value = false;
        }
    }

    onUnmounted(cancel);
    return { items, cursors, failed, loading, itemsFor, load, cancel };
}
