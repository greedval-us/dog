import { useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useServerClock } from '@/composables/useServerClock';
import { useGameEventPolling } from '@/composables/useGameEventPolling';
import { entryWasWithdrawn } from '@/lib/gameEventEntries';
import { register, update, cancel } from '@/routes/game-events';
import type {
    EventDecision,
    EventEquipment,
    GameEventEntry,
    GameEventEntryProps,
} from '@/types/game-event';

const balancedStages: EventDecision[] = ['balanced', 'balanced', 'balanced'];

export function useGameEventEntry(props: GameEventEntryProps) {
    const page = usePage();
    const entryPlan = (entry: GameEventEntry | null) => ({
        stages: [
            ...(props.event.discipline === 'progeny'
                ? balancedStages
                : (entry?.plan.stages ?? balancedStages)),
        ],
        offspring_ids: [...(entry?.plan.offspring_ids ?? [])],
    });
    const entryGearIds = (entry: GameEventEntry | null) =>
        props.event.discipline === 'progeny' ? [] : [...(entry?.gearIds ?? [])];
    const ownEntry = computed(() => props.entry);
    const form = useForm({
        pet_id:
            ownEntry.value?.petId ??
            props.dogs.find(
                (dog) =>
                    props.event.discipline === 'progeny' ||
                    (dog.isActive && !dog.isBusy),
            )?.id ??
            null,
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
            form.plan.offspring_ids.length < 3,
    );
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
            missingOffspring.value
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
        withdrawn,
        editable,
        errors,
        insufficientFunds,
        shortfall,
        validEquipment,
        missingKit,
        missingOffspring,
        toggleGear,
        submit,
        withdraw,
    };
}
