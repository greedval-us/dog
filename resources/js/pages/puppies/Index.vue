<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Clock, House, PawPrint, RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import BreedingNavigation from '@/components/BreedingNavigation.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PuppyCard from '@/components/PuppyCard.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import CursorPagination from '@/components/CursorPagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useBreedingMessages } from '@/composables/useBreedingMessages';
import { useI18n } from '@/composables/useI18n';
import { dashboard } from '@/routes';
import { index, keep, surrender } from '@/routes/puppies';
import { store as list, destroy } from '@/routes/puppies/listing';
import type { Puppy, PuppyPagination } from '@/types/breeding';

const props = defineProps<{
    puppies: Puppy[];
    pregnancies: {
        id: number;
        fatherName: string;
        motherName: string;
        dueAt: string;
    }[];
    freeSlots: number;
    blocked: boolean;
    pagination: PuppyPagination;
    parentId: number | null;
}>();
const { t, number } = useI18n();
const { date } = useBreedingMessages();
const selected = ref<Puppy | null>(null);
const action = ref<'keep' | 'list' | 'surrender' | null>(null);
const moving = ref(false);
const keepForm = useForm({ name: '', operation_token: '' });
const listForm = useForm({ price: 500 });
const transferForm = useForm({});
const withdrawForm = useForm({});
const pending = computed(
    () =>
        moving.value ||
        keepForm.processing ||
        listForm.processing ||
        transferForm.processing ||
        withdrawForm.processing,
);
const errors = computed(() =>
    [
        ...Object.values(keepForm.errors),
        ...Object.values(listForm.errors),
        ...Object.values(transferForm.errors),
    ].filter((error): error is string => typeof error === 'string'),
);
const withdrawErrors = computed(() =>
    Object.values(withdrawForm.errors).filter(
        (error): error is string => typeof error === 'string',
    ),
);
const title = computed(() =>
    t(
        action.value === 'keep'
            ? 'Take your puppy home'
            : action.value === 'list'
              ? 'Offer a puppy for sale'
              : 'Send puppy to the kennel',
    ),
);
const pageLink = (cursor: string) =>
    index.url({ query: { parent: props.parentId, cursor } });
function open(puppy: Puppy, nextAction: 'keep' | 'list' | 'surrender') {
    if (pending.value || props.blocked) return;
    selected.value = puppy;
    action.value = nextAction;
    keepForm.clearErrors();
    listForm.clearErrors();
    transferForm.clearErrors();
    keepForm.name = puppy.name;
    keepForm.operation_token = crypto.randomUUID();
    listForm.price = puppy.price ?? 500;
}
function submit() {
    if (!selected.value || pending.value || props.blocked) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            action.value = null;
        },
    };
    if (action.value === 'keep' && props.freeSlots > 0)
        keepForm.submit(keep(selected.value.id), options);
    else if (action.value === 'list')
        listForm.submit(list(selected.value.id), options);
    else if (action.value === 'surrender')
        transferForm.submit(surrender(selected.value.id), options);
}
</script>

<template>
    <div class="breeding-page">
        <Head :title="t('My puppies')" />
        <div class="breeding-heading">
            <Heading
                :title="t('My puppies')"
                :description="
                    t(
                        'Keep a new friend, find a buyer or let the kennel find a home.',
                    )
                "
            /><Button
                variant="secondary"
                :disabled="pending"
                @click="router.reload()"
                ><RefreshCw :size="16" aria-hidden="true" />{{
                    t('Refresh')
                }}</Button
            >
        </div>
        <BreedingNavigation active="puppies" />
        <SurfaceCard class="breeding-intro"
            ><p>
                {{
                    t(
                        'Each puppy needs a decision within 7 days after birth: keep it, sell it or send it to the kennel. Unclaimed puppies go to the kennel automatically.',
                    )
                }}
            </p>
            <p class="field-hint">
                {{
                    t(
                        'Puppies do not age while waiting. Taking one home uses a free dog slot.',
                    )
                }}
            </p>
            <strong>{{
                t('Free places: {count}', { count: number(freeSlots) })
            }}</strong
            ><ActionHint
                :message="blocked ? t('breeding.errors.blocked') : null"
        /></SurfaceCard>
        <p v-if="parentId" class="field-hint">
            {{ t('Showing puppies of the selected parent.') }}
            <Link :href="index()" class="text-link">{{
                t('Show all puppies')
            }}</Link>
        </p>
        <SurfaceCard
            v-if="pregnancies.length"
            :title="t('Expected litters')"
            class="breeding-intro"
            ><div
                v-for="litter in pregnancies"
                :key="litter.id"
                class="breeding-pregnancy"
            >
                <Clock :size="20" aria-hidden="true" />
                <div>
                    <strong
                        >{{ litter.motherName }} ×
                        {{ litter.fatherName }}</strong
                    >
                    <p>
                        {{
                            t('Puppies arrive: {date}', {
                                date: date(litter.dueAt),
                            })
                        }}
                    </p>
                </div>
            </div></SurfaceCard
        >
        <InputError
            v-for="error in withdrawErrors"
            :key="error"
            :message="error"
            role="alert"
        />
        <div v-if="puppies.length" class="puppy-grid">
            <PuppyCard v-for="puppy in puppies" :key="puppy.id" :puppy="puppy">
                <p v-if="puppy.status === 'listed'" class="puppy-sale-status">
                    {{
                        t('For sale: {amount} coins', {
                            amount: number(puppy.price ?? 0),
                        })
                    }}
                </p>
                <ActionHint
                    v-if="freeSlots < 1"
                    :message="t('A free dog slot is needed to keep a puppy.')"
                    ><Link :href="dashboard()" class="text-link">{{
                        t('Manage dog places')
                    }}</Link></ActionHint
                >
                <div class="puppy-actions">
                    <Button
                        :disabled="pending || blocked || freeSlots < 1"
                        @click="open(puppy, 'keep')"
                        ><PawPrint :size="16" aria-hidden="true" />{{
                            t('Keep puppy')
                        }}</Button
                    ><Button
                        variant="secondary"
                        :disabled="pending || blocked"
                        @click="open(puppy, 'list')"
                        >{{
                            t(
                                puppy.status === 'listed'
                                    ? 'Change sale price'
                                    : 'Sell puppy',
                            )
                        }}</Button
                    ><Button
                        v-if="puppy.status === 'listed'"
                        variant="secondary"
                        :disabled="pending || blocked"
                        @click="
                            withdrawForm.submit(destroy(puppy.id), {
                                preserveScroll: true,
                            })
                        "
                        >{{ t('Withdraw from sale') }}</Button
                    ><Button
                        variant="outline"
                        :disabled="pending || blocked"
                        @click="open(puppy, 'surrender')"
                        ><House :size="16" aria-hidden="true" />{{
                            t('Send to kennel')
                        }}</Button
                    >
                </div>
            </PuppyCard>
        </div>
        <EmptyState
            v-else
            :title="t('Your new generation is still ahead')"
            :description="
                t(
                    'Puppies assigned to you will appear here after a litter is born.',
                )
            "
            :kicker="null"
        />
        <CursorPagination
            :previous-cursor="pagination.previousCursor"
            :next-cursor="pagination.nextCursor"
            :page-link="pageLink"
            :label="t('Puppy pages')"
            :disabled="pending"
            @start="moving = true"
            @finish="moving = false"
        />
        <Dialog
            :open="action !== null"
            @update:open="!pending && !$event && (action = null)"
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>{{ title }}</DialogTitle
                    ><DialogDescription
                        >{{ selected?.name }} · {{ selected?.breed }} ·
                        {{ selected?.coatLabel }}</DialogDescription
                    ></DialogHeader
                >
                <form class="form-stack" @submit.prevent="submit">
                    <FormField
                        v-if="action === 'keep'"
                        id="puppy-keep-name"
                        :label="t('Dog name')"
                        :error="keepForm.errors.name"
                        v-slot="{ field }"
                        ><Input
                            v-bind="field"
                            v-model="keepForm.name"
                            maxlength="64"
                            required
                            :disabled="pending" /></FormField
                    ><FormField
                        v-if="action === 'list'"
                        id="puppy-sale-price"
                        :label="t('Sale price in coins')"
                        :error="listForm.errors.price"
                        v-slot="{ field }"
                        ><Input
                            v-bind="field"
                            v-model="listForm.price"
                            type="number"
                            min="1"
                            max="1000000"
                            step="1"
                            required
                            :disabled="pending"
                    /></FormField>
                    <p v-if="action === 'list'" class="field-hint">
                        {{
                            t(
                                'The sale ends at the decision deadline. If no buyer takes the puppy, it goes to the kennel.',
                            )
                        }}
                    </p>
                    <p v-if="action === 'surrender'">
                        {{
                            t(
                                'The puppy leaves your litter and becomes available at the kennel. This transfer cannot be undone.',
                            )
                        }}
                    </p>
                    <InputError
                        v-for="error in errors"
                        :key="error"
                        :message="error"
                        role="alert"
                    /><DialogFooter
                        ><Button
                            type="button"
                            variant="secondary"
                            :disabled="pending"
                            @click="action = null"
                            >{{ t('Cancel') }}</Button
                        ><Button
                            type="submit"
                            :disabled="
                                pending ||
                                blocked ||
                                (action === 'keep' && freeSlots < 1)
                            "
                            >{{
                                pending ? t('Saving...') : t('Confirm')
                            }}</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent></Dialog
        >
    </div>
</template>
