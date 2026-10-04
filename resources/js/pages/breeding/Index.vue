<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    Coins,
    Eye,
    Heart,
    House,
    LockKeyhole,
    Upload,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import BreedingNavigation from '@/components/BreedingNavigation.vue';
import BreedingParentCard from '@/components/BreedingParentCard.vue';
import BreedingPreview from '@/components/BreedingPreview.vue';
import CursorPagination from '@/components/CursorPagination.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import HelpHint from '@/components/HelpHint.vue';
import InputError from '@/components/InputError.vue';
import OwnedDogSelector from '@/components/OwnedDogSelector.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
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
import { useBreedingMessages } from '@/composables/useBreedingMessages';
import { useI18n } from '@/composables/useI18n';
import { purchaseShortfall } from '@/lib/purchaseAvailability';
import { index, store } from '@/routes/breeding';
import { store as list, destroy } from '@/routes/breeding/listings';
import { index as kennel } from '@/routes/kennel';
import type { BreedingForecast, BreedingParent } from '@/types/breeding';

const props = defineProps<{
    access: { level: number; requiredLevel: number; allowed: boolean };
    dogs: BreedingParent[];
    ownListings: {
        id: number;
        petId: number;
        price: number;
        isActive: boolean;
    }[];
    listings: {
        id: number;
        price: number;
        owner: { username: string };
        pet: BreedingParent;
    }[];
    partners: { id: number; price: number; pet: BreedingParent }[];
    selection: {
        petId: number | null;
        kind: 'listing' | 'partner' | null;
        partnerId: number | null;
    };
    preview: BreedingForecast | null;
    operationToken: string;
    selectedPrice?: number;
    ownPair?: boolean;
    listingPagination?: {
        previousCursor: string | null;
        nextCursor: string | null;
    };
}>();
const { t, number } = useI18n();
const { reason, date } = useBreedingMessages();
const page = usePage();
const moving = ref(false);
const confirmOpen = ref(false);
const form = useForm({
    pet_id: props.selection.petId,
    kind: props.selection.kind,
    partner_id: props.selection.partnerId,
    expected_price: 0,
    operation_token: props.operationToken,
});
const listingForm = useForm({ pet_id: props.selection.petId, price: 100 });
const removeForm = useForm({});
const pending = computed(
    () =>
        moving.value ||
        form.processing ||
        listingForm.processing ||
        removeForm.processing,
);
const selectedDog = computed(() =>
    props.dogs.find((dog) => dog.id === props.selection.petId),
);
const partner = computed(() =>
    (props.selection.kind === 'listing' ? props.listings : props.partners).find(
        (offer) => offer.id === props.selection.partnerId,
    ),
);
const price = computed(() => partner.value?.price ?? 0);
const ownPair = computed(
    () => props.ownPair ?? props.preview?.ownPair ?? false,
);
const chargedPrice = computed(
    () => props.selectedPrice ?? (ownPair.value ? 0 : price.value),
);
const shortfall = computed(() =>
    purchaseShortfall(chargedPrice.value, Number(page.props.auth.user.coins)),
);
const actionReason = computed(
    () =>
        reason(props.preview?.reason ?? selectedDog.value?.reason ?? null) ??
        (shortfall.value > 0
            ? t('You need {amount} more coins.', {
                  amount: number(shortfall.value),
              })
            : null),
);
const errors = computed(() =>
    [...Object.values(form.errors), ...Object.values(removeForm.errors)].filter(
        (error): error is string => typeof error === 'string',
    ),
);
const listingErrors = computed(() =>
    Object.entries(listingForm.errors)
        .filter(([key, value]) => key !== 'price' && typeof value === 'string')
        .map(([, value]) => String(value)),
);
watch(
    () => props.selection,
    (selection) => {
        form.pet_id = selection.petId;
        form.kind = selection.kind;
        form.partner_id = selection.partnerId;
        form.operation_token = props.operationToken;
        listingForm.pet_id = selection.petId;
        confirmOpen.value = false;
    },
);
function select(
    pet: number,
    kind: 'listing' | 'partner' | null = null,
    partnerId: number | null = null,
) {
    if (pending.value) return;
    moving.value = true;
    router.get(
        index({ query: { pet, kind, partner: partnerId } }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                moving.value = false;
            },
        },
    );
}
function listingPageLink(cursor: string): string {
    return index.url({
        query: {
            pet: props.selection.petId,
            kind: props.selection.kind,
            partner: props.selection.partnerId,
            cursor,
        },
    });
}
function mate() {
    if (pending.value || actionReason.value || !props.preview) return;
    form.expected_price = price.value;
    form.operation_token = props.operationToken;
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            confirmOpen.value = false;
        },
    });
}
function createListing() {
    if (
        pending.value ||
        !selectedDog.value ||
        selectedDog.value.sex !== 'male' ||
        selectedDog.value.reason
    )
        return;
    listingForm.pet_id = selectedDog.value.id;
    listingForm.submit(list(), { preserveScroll: true });
}
</script>

<template>
    <div class="breeding-page">
        <Head :title="t('Breeding')" />
        <div class="page-heading-with-help">
            <Heading :title="t('Breeding')" />
            <HelpHint
                :label="t('Breeding')"
                :text="
                    t(
                        'Choose a partner, compare the forecast and begin a new generation.',
                    )
                "
            />
        </div>
        <BreedingNavigation active="breeding" />
        <EmptyState
            v-if="!access.allowed"
            :title="
                access.level >= access.requiredLevel
                    ? t('Breeding is unavailable right now.')
                    : t('Breeding opens at level {level}', {
                          level: number(access.requiredLevel),
                      })
            "
            :description="
                access.level >= access.requiredLevel
                    ? t('breeding.errors.blocked')
                    : t(
                          'Your current level is {level}. Care for your dogs to gain experience.',
                          { level: number(access.level) },
                      )
            "
            :kicker="null"
            ><template #icon><LockKeyhole aria-hidden="true" /></template
        ></EmptyState>
        <template v-else>
            <SurfaceCard class="breeding-intro">
                <template #header>
                    <div class="page-heading-with-help">
                        <h2 class="breeding-section-title">
                            {{ t('Your dog') }}
                        </h2>
                        <HelpHint
                            :label="t('Your dog')"
                            :text="
                                t(
                                    'Both dogs must be at least 7 days old, healthy and available. Only unrelated dogs of the same breed can be paired.',
                                )
                            "
                        />
                    </div>
                </template>
                <OwnedDogSelector
                    v-if="dogs.length"
                    id="breeding-dog"
                    :dogs="dogs"
                    :selected-pet-id="selection.petId"
                    select-class="breeding-select"
                    :disabled="pending"
                    @select="select($event)"
                />
                <template v-else
                    ><p>{{ t('You need a dog to begin breeding.') }}</p>
                    <Button as-child variant="secondary"
                        ><Link :href="kennel()"
                            ><House :size="16" aria-hidden="true" />{{
                                t('Visit the kennel')
                            }}</Link
                        ></Button
                    ></template
                >
                <BreedingParentCard
                    v-if="selectedDog"
                    :pet="selectedDog"
                    compact
                />
                <ActionHint :message="reason(selectedDog?.reason ?? null)" />
                <p v-if="selectedDog?.cooldownUntil" class="field-hint">
                    {{
                        t('Next breeding: {date}', {
                            date: date(selectedDog.cooldownUntil),
                        })
                    }}
                </p>
            </SurfaceCard>
            <div
                v-if="errors.length"
                class="breeding-errors"
                role="alert"
                tabindex="-1"
            >
                <InputError
                    v-for="error in errors"
                    :key="error"
                    :message="error"
                />
            </div>
            <div v-if="selectedDog" class="breeding-offer-sections">
                <section class="breeding-section">
                    <h2 class="breeding-section-title">
                        <Users :size="22" aria-hidden="true" />{{
                            t('Player breeding offers')
                        }}
                    </h2>
                    <div v-if="listings.length" class="breeding-offer-grid">
                        <SurfaceCard
                            v-for="offer in listings"
                            :key="offer.id"
                            class="breeding-offer"
                            :class="{
                                'is-selected':
                                    selection.kind === 'listing' &&
                                    selection.partnerId === offer.id,
                            }"
                        >
                            <BreedingParentCard :pet="offer.pet" compact />
                            <p class="field-hint">
                                {{
                                    t('Owner: {username}', {
                                        username: offer.owner.username,
                                    })
                                }}
                            </p>
                            <div class="breeding-offer-footer">
                                <span
                                    ><Coins :size="17" aria-hidden="true" />{{
                                        t('{amount} coins', {
                                            amount: number(offer.price),
                                        })
                                    }}</span
                                ><Button
                                    variant="secondary"
                                    :aria-pressed="
                                        selection.kind === 'listing' &&
                                        selection.partnerId === offer.id
                                    "
                                    :disabled="
                                        pending ||
                                        Boolean(offer.pet.reason) ||
                                        Boolean(selectedDog.reason)
                                    "
                                    @click="
                                        select(
                                            selectedDog.id,
                                            'listing',
                                            offer.id,
                                        )
                                    "
                                    ><Check
                                        v-if="
                                            selection.kind === 'listing' &&
                                            selection.partnerId === offer.id
                                        "
                                        :size="16"
                                        aria-hidden="true"
                                    /><Eye
                                        v-else
                                        :size="16"
                                        aria-hidden="true"
                                    />{{
                                        t(
                                            selection.kind === 'listing' &&
                                                selection.partnerId === offer.id
                                                ? 'Selected partner'
                                                : 'View forecast',
                                        )
                                    }}</Button
                                >
                            </div>
                            <ActionHint :message="reason(offer.pet.reason)" />
                        </SurfaceCard>
                    </div>
                    <p v-else class="field-hint">
                        {{
                            t(
                                'No player offers match your dog yet. A kennel partner can help.',
                            )
                        }}
                    </p>
                    <CursorPagination
                        :previous-cursor="
                            listingPagination?.previousCursor ?? null
                        "
                        :next-cursor="listingPagination?.nextCursor ?? null"
                        :page-link="listingPageLink"
                        :label="t('Breeding offer pages')"
                        :disabled="pending"
                        @start="moving = true"
                        @finish="moving = false"
                    />
                </section>
                <section class="breeding-section">
                    <div class="page-heading-with-help">
                        <h2 class="breeding-section-title">
                            <House :size="22" aria-hidden="true" />{{
                                t('Kennel partners')
                            }}
                        </h2>
                        <HelpHint
                            :label="t('Kennel partners')"
                            :text="
                                t(
                                    'Kennel partners have fixed characteristics. The kennel keeps its share of the litter.',
                                )
                            "
                        />
                    </div>
                    <div v-if="partners.length" class="breeding-offer-grid">
                        <SurfaceCard
                            v-for="offer in partners"
                            :key="offer.id"
                            class="breeding-offer"
                            :class="{
                                'is-selected':
                                    selection.kind === 'partner' &&
                                    selection.partnerId === offer.id,
                            }"
                        >
                            <BreedingParentCard :pet="offer.pet" compact />
                            <div class="breeding-offer-footer">
                                <span
                                    ><Coins :size="17" aria-hidden="true" />{{
                                        t('{amount} coins', {
                                            amount: number(offer.price),
                                        })
                                    }}</span
                                ><Button
                                    variant="secondary"
                                    :aria-pressed="
                                        selection.kind === 'partner' &&
                                        selection.partnerId === offer.id
                                    "
                                    :disabled="
                                        pending ||
                                        Boolean(offer.pet.reason) ||
                                        Boolean(selectedDog.reason)
                                    "
                                    @click="
                                        select(
                                            selectedDog.id,
                                            'partner',
                                            offer.id,
                                        )
                                    "
                                    ><Check
                                        v-if="
                                            selection.kind === 'partner' &&
                                            selection.partnerId === offer.id
                                        "
                                        :size="16"
                                        aria-hidden="true"
                                    /><Eye
                                        v-else
                                        :size="16"
                                        aria-hidden="true"
                                    />{{
                                        t(
                                            selection.kind === 'partner' &&
                                                selection.partnerId === offer.id
                                                ? 'Selected partner'
                                                : 'View forecast',
                                        )
                                    }}</Button
                                >
                            </div>
                        </SurfaceCard>
                    </div>
                    <p v-else class="field-hint">
                        {{
                            t(
                                'No kennel partner is currently available for this breed.',
                            )
                        }}
                    </p>
                </section>
            </div>
            <div v-if="moving" class="dashboard-loading" role="status">
                <Spinner />
                {{ t('Loading puppy forecast...') }}
            </div>
            <BreedingPreview v-if="preview" :forecast="preview">
                <ActionHint :message="actionReason" />
                <p v-if="ownPair" class="field-hint">
                    {{
                        t(
                            'Both parents belong to you. No breeding fee is charged.',
                        )
                    }}
                </p>
                <div class="breeding-confirm">
                    <div class="breeding-summary-stat">
                        <Coins :size="18" aria-hidden="true" />
                        <strong>{{
                            t('{amount} coins', {
                                amount: number(chargedPrice),
                            })
                        }}</strong>
                        <HelpHint
                            :label="t('Confirm breeding')"
                            :text="
                                t(
                                    'A litter of 2–5 puppies arrives in 24 hours. One random puppy belongs to the father’s owner; the rest belong to the mother’s owner. Both parents rest from breeding for 7 days.',
                                )
                            "
                        />
                    </div>
                    <Button
                        :disabled="pending || Boolean(actionReason)"
                        @click="confirmOpen = true"
                        ><Heart :size="17" aria-hidden="true" />{{
                            t('Begin breeding')
                        }}</Button
                    >
                </div>
            </BreedingPreview>
            <SurfaceCard
                v-if="selectedDog"
                :title="t('Your breeding offers')"
                class="breeding-intro"
            >
                <form
                    v-if="selectedDog.sex === 'male'"
                    class="breeding-listing-form"
                    @submit.prevent="createListing"
                >
                    <FormField
                        id="breeding-price"
                        :label="t('Breeding fee in coins')"
                        :error="listingForm.errors.price"
                        v-slot="{ field }"
                        ><Input
                            v-bind="field"
                            v-model="listingForm.price"
                            type="number"
                            min="1"
                            max="1000000"
                            step="1"
                            required
                            :disabled="pending"
                    /></FormField>
                    <Button
                        type="submit"
                        :disabled="pending || Boolean(selectedDog.reason)"
                        ><Spinner v-if="listingForm.processing" /><Upload
                            v-else
                            :size="16"
                            aria-hidden="true"
                        />{{ t('Publish or update offer') }}</Button
                    >
                </form>
                <p v-else class="field-hint">
                    {{ t('Select a male dog to publish a breeding offer.') }}
                </p>
                <InputError
                    v-for="error in listingErrors"
                    :key="error"
                    :message="error"
                    role="alert"
                />
                <div
                    v-for="offer in ownListings.filter((item) => item.isActive)"
                    :key="offer.id"
                    class="breeding-own-offer"
                >
                    <span
                        >{{ dogs.find((dog) => dog.id === offer.petId)?.name }}
                        ·
                        {{
                            t('{amount} coins', { amount: number(offer.price) })
                        }}</span
                    ><Button
                        variant="secondary"
                        size="sm"
                        :disabled="pending"
                        @click="
                            removeForm.submit(destroy(offer.id), {
                                preserveScroll: true,
                            })
                        "
                        ><X :size="16" aria-hidden="true" />{{
                            t('Withdraw offer')
                        }}</Button
                    >
                </div>
            </SurfaceCard>
        </template>
        <Dialog
            :open="confirmOpen"
            @update:open="!form.processing && (confirmOpen = $event)"
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>{{ t('Confirm breeding') }}</DialogTitle
                    ><DialogDescription>{{
                        t(
                            'A litter of 2–5 puppies arrives in 24 hours. One random puppy belongs to the father’s owner; the rest belong to the mother’s owner. Both parents rest from breeding for 7 days.',
                        )
                    }}</DialogDescription></DialogHeader
                >
                <p>
                    {{
                        t(
                            'Each puppy needs a decision within 7 days after birth: keep it, sell it or send it to the kennel. Unclaimed puppies go to the kennel automatically.',
                        )
                    }}
                </p>
                <p>
                    {{
                        t('Breeding fee: {amount} coins', {
                            amount: number(chargedPrice),
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
                        variant="secondary"
                        :disabled="form.processing"
                        @click="confirmOpen = false"
                        >{{ t('Cancel') }}</Button
                    ><Button
                        :disabled="form.processing || Boolean(actionReason)"
                        @click="mate"
                        ><Spinner v-if="form.processing" /><Heart
                            v-else
                            :size="16"
                            aria-hidden="true"
                        />{{
                            form.processing
                                ? t('Starting breeding...')
                                : t('Confirm breeding')
                        }}</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
    </div>
</template>
