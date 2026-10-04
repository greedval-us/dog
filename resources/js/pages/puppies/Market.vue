<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Coins, House, PawPrint, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import BreedingNavigation from '@/components/BreedingNavigation.vue';
import CursorPagination from '@/components/CursorPagination.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import HelpHint from '@/components/HelpHint.vue';
import InputError from '@/components/InputError.vue';
import PuppyCard from '@/components/PuppyCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { purchaseShortfall } from '@/lib/purchaseAvailability';
import { dashboard } from '@/routes';
import { index as myPuppies, market, purchase } from '@/routes/puppies';
import type { Puppy, PuppyPagination } from '@/types/breeding';

const props = defineProps<{
    puppies: Puppy[];
    source: 'players' | 'kennel';
    freeSlots: number;
    blocked: boolean;
    kennelPrice: number;
    pagination: PuppyPagination;
}>();
const { t, number } = useI18n();
const page = usePage();
const moving = ref(false);
const selected = ref<Puppy | null>(null);
const form = useForm({ name: '', expected_price: 0, operation_token: '' });
const pending = computed(() => form.processing || moving.value);
const price = (puppy: Puppy) => puppy.price ?? props.kennelPrice;
const reason = (puppy: Puppy): string | null => {
    if (puppy.seller?.username === page.props.auth.user.username)
        return t('This puppy already belongs to you. Manage it in My puppies.');
    if (props.blocked) return t('breeding.errors.blocked');
    if (props.freeSlots < 1)
        return t('A free dog slot is needed to keep a puppy.');
    const shortfall = purchaseShortfall(
        price(puppy),
        Number(page.props.auth.user.coins),
    );
    return shortfall > 0
        ? t('You need {amount} more coins.', { amount: number(shortfall) })
        : null;
};
const pageLink = (cursor: string) =>
    market.url({ query: { source: props.source, cursor } });
function choose(puppy: Puppy) {
    if (pending.value || reason(puppy)) return;
    selected.value = puppy;
    form.name = puppy.name;
    form.expected_price = price(puppy);
    form.operation_token = crypto.randomUUID();
    form.clearErrors();
}
function buy() {
    if (!selected.value || pending.value || reason(selected.value)) return;
    form.submit(purchase(selected.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
        },
    });
}
</script>

<template>
    <div class="breeding-page">
        <Head :title="t('Puppy market')" />
        <div class="page-heading-with-help">
            <Heading :title="t('Puppy market')" />
            <HelpHint
                :label="t('Puppy market')"
                :text="
                    t(
                        'A new friend can come from another player or from the kennel.',
                    )
                "
            />
        </div>
        <BreedingNavigation active="market" />
        <nav class="breeding-navigation" :aria-label="t('Puppy source')">
            <Link
                :href="market({ query: { source: 'players' } })"
                class="breeding-navigation-link"
                :class="{ 'is-active': source === 'players' }"
                :aria-current="source === 'players' ? 'page' : undefined"
                preserve-scroll
                ><Users :size="17" aria-hidden="true" />{{
                    t('From players')
                }}</Link
            ><Link
                :href="market({ query: { source: 'kennel' } })"
                class="breeding-navigation-link"
                :class="{ 'is-active': source === 'kennel' }"
                :aria-current="source === 'kennel' ? 'page' : undefined"
                preserve-scroll
                ><House :size="17" aria-hidden="true" />{{
                    t('From the kennel')
                }}</Link
            >
        </nav>
        <div class="breeding-summary">
            <span class="breeding-summary-stat">
                <PawPrint :size="18" aria-hidden="true" />
                <strong>{{
                    t('Free places: {count}', { count: number(freeSlots) })
                }}</strong>
                <HelpHint
                    :label="t('Take your puppy home')"
                    :text="
                        t(
                            'Starting characteristics are 20% of genetic potential. Your puppy’s active life begins when it goes home.',
                        )
                    "
                />
            </span>
            <span v-if="source === 'kennel'" class="breeding-summary-stat">
                <Coins :size="18" aria-hidden="true" />
                <strong>{{
                    t('{amount} coins', { amount: number(kennelPrice) })
                }}</strong>
                <HelpHint
                    :label="t('From the kennel')"
                    :text="
                        t(
                            'Kennel puppies cost {amount} coins. Their characteristics and coat are already known.',
                            { amount: number(kennelPrice) },
                        )
                    "
                />
            </span>
        </div>
        <div v-if="puppies.length" class="puppy-grid">
            <PuppyCard v-for="puppy in puppies" :key="puppy.id" :puppy="puppy"
                ><p v-if="puppy.seller" class="field-hint">
                    {{
                        t('Seller: {username}', {
                            username: puppy.seller.username,
                        })
                    }}
                </p>
                <ActionHint :message="reason(puppy)"
                    ><Link
                        v-if="
                            puppy.seller?.username ===
                            page.props.auth.user.username
                        "
                        :href="myPuppies()"
                        class="text-link"
                        >{{ t('My puppies') }}</Link
                    ><Link
                        v-if="freeSlots < 1"
                        :href="dashboard()"
                        class="text-link"
                        >{{ t('Manage dog places') }}</Link
                    ></ActionHint
                >
                <div class="breeding-offer-footer">
                    <strong
                        ><Coins :size="17" aria-hidden="true" />{{
                            t('{amount} coins', {
                                amount: number(price(puppy)),
                            })
                        }}</strong
                    ><Button
                        :disabled="pending || Boolean(reason(puppy))"
                        @click="choose(puppy)"
                        ><PawPrint :size="16" aria-hidden="true" />{{
                            t('Take puppy home')
                        }}</Button
                    >
                </div></PuppyCard
            >
        </div>
        <EmptyState
            v-else
            :title="t('No puppies are available here yet')"
            :description="t('Visit again later or try the other puppy source.')"
            :kicker="null"
        />
        <CursorPagination
            :previous-cursor="pagination.previousCursor"
            :next-cursor="pagination.nextCursor"
            :page-link="pageLink"
            :label="t('Puppy market pages')"
            :disabled="pending"
            @start="moving = true"
            @finish="moving = false"
        />
        <Dialog
            :open="Boolean(selected)"
            @update:open="!pending && !$event && (selected = null)"
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>{{ t('Take your puppy home') }}</DialogTitle
                    ><DialogDescription
                        >{{ selected?.name }} · {{ selected?.breed }} ·
                        {{ selected?.coatLabel }}</DialogDescription
                    ></DialogHeader
                >
                <form class="form-stack" @submit.prevent="buy">
                    <FormField
                        id="puppy-buy-name"
                        :label="t('Dog name')"
                        :error="form.errors.name"
                        v-slot="{ field }"
                        ><Input
                            v-bind="field"
                            v-model="form.name"
                            maxlength="64"
                            required
                            :disabled="pending"
                    /></FormField>
                    <p>
                        {{
                            t('Purchase price: {amount} coins', {
                                amount: number(form.expected_price),
                            })
                        }}
                    </p>
                    <InputError
                        v-for="error in Object.values(form.errors)"
                        :key="error"
                        :message="error"
                        role="alert"
                    /><DialogFooter
                        ><Button
                            type="button"
                            variant="secondary"
                            :disabled="pending"
                            @click="selected = null"
                            >{{ t('Cancel') }}</Button
                        ><Button
                            type="submit"
                            :disabled="
                                pending ||
                                !selected ||
                                Boolean(selected && reason(selected))
                            "
                            ><Spinner v-if="form.processing" /><PawPrint
                                v-else
                                :size="16"
                                aria-hidden="true"
                            />{{
                                form.processing
                                    ? t('Bringing your dog home...')
                                    : t('Confirm purchase')
                            }}</Button
                        ></DialogFooter
                    >
                </form></DialogContent
            ></Dialog
        >
    </div>
</template>
