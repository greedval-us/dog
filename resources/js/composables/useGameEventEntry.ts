import { useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useServerClock } from '@/composables/useServerClock';
import { useGameEventPolling } from '@/composables/useGameEventPolling';
import { entryWasWithdrawn } from '@/lib/gameEventEntries';
import { register, update, cancel } from '@/routes/game-events';
import type {
    EventDecision,
    EventEquipment,
    EventEntryOption,
    GameEventPlan,
    GameEventEntry,
    GameEventEntryProps,
} from '@/types/game-event';

const balancedStages: EventDecision[] = ['balanced', 'balanced', 'balanced'];
const classificationKeys = [
    'exhibition_class',
    'sport_class',
    'program',
    'test_type',
    'handler_category',
] as const;

export function useGameEventEntry(props: GameEventEntryProps) {
    const page = usePage();
    function eventClassificationKeys(): readonly (typeof classificationKeys)[number][] {
        switch (props.event.discipline) {
            case 'conformation':
                return ['exhibition_class'];
            case 'agility':
                return ['sport_class'];
            case 'nosework':
                return props.event.regulation?.format === 'odor_test'
                    ? ['sport_class', 'program', 'test_type']
                    : ['sport_class', 'program'];
            case 'canicross':
                return ['sport_class', 'handler_category'];
            case 'progeny':
                return [];
        }
    }
    const firstDogId =
        props.dogs.find(
            (dog) => dog.isActive && !dog.preparation?.blockingReasons.length,
        )?.id ??
        props.dogs.find((dog) => dog.isActive)?.id ??
        null;
    const entryPlan = (entry: GameEventEntry | null): GameEventPlan => ({
        stages: [
            ...(props.event.discipline === 'progeny'
                ? balancedStages
                : (entry?.plan.stages ??
                  (props.event.regulation
                      ? props.event.stages.map(() => 'balanced' as const)
                      : balancedStages))),
        ],
        ...(props.event.discipline === 'progeny'
            ? { offspring_ids: [...(entry?.plan.offspring_ids ?? [])] }
            : {}),
        ...(props.event.regulation
            ? Object.fromEntries(
                  eventClassificationKeys().flatMap((key) =>
                      entry?.plan[key] ? [[key, entry.plan[key]]] : [],
                  ),
              )
            : props.event.discipline === 'conformation'
              ? {
                    exhibition_class:
                        entry?.plan.exhibition_class ??
                        props.dogs.find(
                            (dog) => dog.id === (entry?.petId ?? firstDogId),
                        )?.defaultClass ??
                        undefined,
                }
              : {}),
    });
    const entryGearIds = (entry: GameEventEntry | null) =>
        props.event.discipline === 'progeny' ? [] : [...(entry?.gearIds ?? [])];
    const ownEntry = computed(() => props.entry);
    const form = useForm({
        pet_id: ownEntry.value?.petId ?? firstDogId ?? null,
        fee: props.event.fee,
        plan: entryPlan(ownEntry.value),
        gear_ids: entryGearIds(ownEntry.value),
        token: '',
    });
    const cancelForm = useForm({ token: '' });
    const errorPanel = ref<HTMLElement | null>(null);
    const { now } = useServerClock(() => props.serverNow, 10_000);
    const pending = computed(() => form.processing || cancelForm.processing);
    useGameEventPolling(
        () => props.event.status,
        () => pending.value,
        () => ownEntry.value?.status,
    );
    const selectedDog = computed(() =>
        props.dogs.find((dog) => dog.id === form.pet_id),
    );
    const regulated = computed(() => Boolean(props.event.regulation));
    const entryOptions = computed(() => selectedDog.value?.entryOptions ?? []);
    const optionMatchesPlan = (option: EventEntryOption) =>
        eventClassificationKeys().every(
            (key) => option.plan[key] === form.plan[key],
        );
    const selectedEntryOption = computed(() =>
        entryOptions.value.find(optionMatchesPlan),
    );
    function selectEntryOption(key: string): void {
        const option = entryOptions.value.find((item) => item.key === key);
        if (!option || option.eligible === false) return;
        for (const key of classificationKeys) delete form.plan[key];
        for (const key of eventClassificationKeys()) {
            if (option.plan[key]) form.plan[key] = option.plan[key];
        }
    }
    const selectedEntryOptionKey = computed({
        get: () => selectedEntryOption.value?.key ?? '',
        set: selectEntryOption,
    });
    const missingEntryOption = computed(
        () =>
            regulated.value &&
            (!selectedEntryOption.value ||
                selectedEntryOption.value.eligible === false),
    );
    const selectedExhibitionClass = computed(() =>
        selectedDog.value?.availableClasses?.find(
            (item) => item.key === form.plan.exhibition_class,
        ),
    );
    const blockedDog = computed(() =>
        Boolean(
            selectedDog.value &&
            (!selectedDog.value.isActive ||
                selectedDog.value.preparation?.blockingReasons.length),
        ),
    );
    const missingExhibitionClass = computed(
        () =>
            !regulated.value &&
            props.event.discipline === 'conformation' &&
            !selectedDog.value?.availableClasses.some(
                (item) => item.key === form.plan.exhibition_class,
            ),
    );
    watch(
        () => [form.pet_id, selectedDog.value?.availableClasses] as const,
        () => {
            if (
                regulated.value ||
                props.event.discipline !== 'conformation' ||
                !selectedDog.value
            )
                return;
            const classes = selectedDog.value?.availableClasses ?? [];
            if (
                !classes.some((item) => item.key === form.plan.exhibition_class)
            ) {
                form.plan.exhibition_class =
                    classes.find(
                        (item) => item.key === selectedDog.value?.defaultClass,
                    )?.key ?? classes[0]?.key;
            }
        },
        { immediate: true },
    );
    watch(
        [() => form.pet_id, entryOptions, regulated],
        () => {
            if (!regulated.value || !selectedDog.value) return;
            if (
                selectedEntryOption.value?.eligible !== false &&
                selectedEntryOption.value
            )
                return;
            if (
                ownEntry.value &&
                classificationKeys.some((key) => form.plan[key])
            )
                return;
            const preferred =
                entryOptions.value.find(
                    (option) =>
                        option.key === selectedDog.value?.defaultEntryOption &&
                        option.eligible !== false,
                ) ??
                entryOptions.value.find((option) => option.eligible !== false);
            if (preferred) selectEntryOption(preferred.key);
            else for (const key of classificationKeys) delete form.plan[key];
        },
        { immediate: true },
    );
    watch([() => props.event.stages, regulated], () => {
        if (
            !regulated.value ||
            ownEntry.value ||
            props.event.discipline === 'progeny'
        )
            return;
        form.plan.stages = props.event.stages.map(
            (_, index) => form.plan.stages[index] ?? 'balanced',
        );
    });
    const registrationOpen = computed(
        () =>
            props.event.status === 'scheduled' &&
            now.value >= Date.parse(props.event.opensAt) &&
            now.value < Date.parse(props.event.closesAt),
    );
    const withdrawn = computed(() => entryWasWithdrawn(ownEntry.value));
    const editable = computed(
        () =>
            !withdrawn.value &&
            registrationOpen.value &&
            (props.event.canRegister || Boolean(ownEntry.value)),
    );
    const errors = computed(() => [
        ...Object.values(form.errors),
        ...Object.values(cancelForm.errors),
    ]);
    const shortfall = computed(
        () => props.event.fee - Number(page.props.auth.user.coins),
    );
    const insufficientFunds = computed(
        () => !ownEntry.value && shortfall.value > 0,
    );
    const validEquipment = computed(() =>
        props.equipment.filter(
            (item) =>
                item.remainingUses > 0 &&
                item.disciplines.includes(props.event.discipline) &&
                (props.event.discipline !== 'agility' ||
                    item.phase === 'preparation') &&
                (props.event.discipline !== 'canicross' ||
                    !['body', 'line', 'handler'].includes(item.slot) ||
                    item.phase === 'performance') &&
                (!item.sizes.length ||
                    item.sizes.includes(selectedDog.value?.size ?? '')),
        ),
    );
    const missingKit = computed(
        () =>
            props.event.discipline === 'canicross' &&
            ['body', 'line', 'handler'].some(
                (slot) =>
                    !validEquipment.value.some(
                        (gear) =>
                            gear.slot === slot &&
                            form.gear_ids.includes(gear.id),
                    ),
            ),
    );
    const missingOffspring = computed(
        () =>
            props.event.discipline === 'progeny' &&
            (form.plan.offspring_ids?.length ?? 0) < 3,
    );
    function applyStagePreset(stages: EventDecision[]): void {
        if (!editable.value || pending.value || !stages.length) return;
        form.plan.stages = regulated.value
            ? props.event.stages.map(
                  (_, index) =>
                      stages[
                          Math.round(
                              (index * (stages.length - 1)) /
                                  Math.max(1, props.event.stages.length - 1),
                          )
                      ],
              )
            : [...stages];
    }
    watch(
        () => ownEntry.value?.id,
        () => {
            const entry = ownEntry.value;
            if (!entry) return;
            form.pet_id = entry.petId;
            form.plan = entryPlan(entry);
            form.gear_ids = entryGearIds(entry);
        },
    );
    watch(
        () => form.pet_id,
        () => {
            if (!ownEntry.value) {
                form.gear_ids = [];
                if (props.event.discipline === 'progeny')
                    form.plan.offspring_ids = [];
            }
        },
    );

    function toggleGear(item: EventEquipment) {
        if (!editable.value || pending.value) return;
        if (form.gear_ids.includes(item.id)) {
            form.gear_ids = form.gear_ids.filter((id) => id !== item.id);
            return;
        }
        const sameSlot = new Set(
            props.equipment
                .filter((gear) => gear.slot === item.slot)
                .map((gear) => gear.id),
        );
        form.gear_ids = [
            ...form.gear_ids.filter((id) => !sameSlot.has(id)),
            item.id,
        ];
    }
    function operationToken(): string {
        if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, (value) =>
            value.toString(16).padStart(2, '0'),
        ).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }
    function submit() {
        if (
            !editable.value ||
            pending.value ||
            !form.pet_id ||
            insufficientFunds.value ||
            missingKit.value ||
            missingOffspring.value ||
            missingExhibitionClass.value ||
            missingEntryOption.value ||
            blockedDog.value
        )
            return;
        if (!form.token) form.token = operationToken();
        form.fee = props.event.fee;
        cancelForm.clearErrors();
        form.submit(
            ownEntry.value ? update(props.event.id) : register(props.event.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.token = '';
                },
                onError: () => nextTick(() => errorPanel.value?.focus()),
            },
        );
    }
    function withdraw() {
        if (!editable.value || pending.value || !ownEntry.value) return;
        if (!cancelForm.token) cancelForm.token = operationToken();
        form.clearErrors();
        cancelForm.submit(cancel(props.event.id), {
            preserveScroll: true,
            onSuccess: () => {
                cancelForm.token = '';
            },
            onError: () => nextTick(() => errorPanel.value?.focus()),
        });
    }

    return {
        ownEntry,
        form,
        cancelForm,
        errorPanel,
        now,
        pending,
        selectedDog,
        selectedExhibitionClass,
        regulated,
        entryOptions,
        selectedEntryOption,
        selectedEntryOptionKey,
        missingEntryOption,
        withdrawn,
        editable,
        errors,
        insufficientFunds,
        shortfall,
        validEquipment,
        missingKit,
        missingOffspring,
        missingExhibitionClass,
        blockedDog,
        applyStagePreset,
        toggleGear,
        submit,
        withdraw,
    };
}
