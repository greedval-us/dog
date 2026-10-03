<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { HeartPulse, ShieldPlus, Stethoscope } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OwnedDogSelector from '@/components/OwnedDogSelector.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { index as kennel } from '@/routes/kennel';
import { index, store } from '@/routes/veterinarian';
import type {
    VeterinaryClinic,
    VeterinaryOffer,
    VeterinaryService,
} from '@/types/veterinarian';

const props = defineProps<{ clinic: VeterinaryClinic }>();
const { t, number, locale } = useI18n();
const form = useForm({
    pet_id: props.clinic.selectedPetId,
    service: 'treatment' as VeterinaryService,
    disease_episode_id: null as number | null,
    expected_price: 0,
    token: props.clinic.token,
});
const moving = ref(false);
const errorPanel = ref<HTMLElement | null>(null);
const pending = computed(() => moving.value || form.processing);
const errors = computed(() => Object.values(form.errors));
const treatment = computed(() =>
    props.clinic.services.find((service) => service.code === 'treatment'),
);
const preventive = computed(() =>
    props.clinic.services.filter((service) => service.code !== 'treatment'),
);
const labels: Record<VeterinaryService, string> = {
    treatment: 'Disease treatment',
    checkup: 'Weekly checkup',
    vaccination: 'Vaccination',
};
watch(
    () => props.clinic,
    (clinic) => {
        form.pet_id = clinic.selectedPetId;
        form.token = clinic.token;
    },
);
function chooseDog(pet: number) {
    if (pending.value) return;
    moving.value = true;
    form.clearErrors();
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
function purchase(service: VeterinaryOffer, episodeId: number | null = null) {
    if (pending.value || service.reason || !form.pet_id) return;
    form.service = service.code;
    form.disease_episode_id = episodeId;
    form.expected_price = service.price;
    form.clearErrors();
    form.submit(store(), {
        preserveScroll: true,
        onError: () => nextTick(() => errorPanel.value?.focus()),
    });
}
function date(value: string) {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <div class="vet-page">
        <Head :title="t('Veterinarian')" />
        <Heading
            :title="t('Veterinarian')"
            :description="t('Treatment and preventive care for your dog.')"
        />

        <SurfaceCard class="vet-intro">
            <div class="vet-heading">
                <span class="player-work-icon"
                    ><Stethoscope aria-hidden="true"
                /></span>
                <div>
                    <h2>{{ t('Your dog’s health') }}</h2>
                    <p>
                        {{
                            t(
                                'Choose a dog. Each service is paid for with coins.',
                            )
                        }}
                    </p>
                </div>
                <span v-if="clinic.health !== null" class="vet-health">
                    <HeartPulse aria-hidden="true" />{{ t('Health') }}:
                    {{ number(clinic.health) }}%
                </span>
            </div>
            <OwnedDogSelector
                v-if="clinic.dogs.length"
                id="vet-dog"
                :dogs="clinic.dogs"
                :selected-pet-id="clinic.selectedPetId"
                select-class="vet-select"
                :disabled="pending"
                @select="chooseDog"
            />
            <div v-else class="vet-empty">
                <p>{{ t('You need a dog to visit the veterinarian.') }}</p>
                <Button as-child
                    ><Link :href="kennel()">{{
                        t('Visit the kennel')
                    }}</Link></Button
                >
            </div>
            <p v-if="clinic.dogs.length && clinic.reason" role="status">
                {{ t(clinic.reason) }}
            </p>
        </SurfaceCard>

        <div
            v-if="errors.length"
            ref="errorPanel"
            class="vet-error"
            role="alert"
            tabindex="-1"
        >
            <InputError
                v-for="message in errors"
                :key="message"
                :message="message"
            />
        </div>

        <template v-if="clinic.selectedPetId">
            <SurfaceCard
                :title="t('Disease treatment')"
                :description="
                    t('Treat each disease separately to remove its debuff.')
                "
            >
                <div v-if="!clinic.diseases.length" class="vet-healthy">
                    <ShieldPlus aria-hidden="true" />
                    <p>{{ t('No diseases requiring treatment.') }}</p>
                </div>
                <ul v-else class="vet-diseases">
                    <li
                        v-for="disease in clinic.diseases"
                        :key="disease.id"
                        class="vet-disease"
                    >
                        <div>
                            <h3>{{ disease.name }}</h3>
                            <p>
                                {{
                                    t('Detected on {date}', {
                                        date: date(disease.startedAt),
                                    })
                                }}
                            </p>
                            <p>{{ t('Until treatment') }}</p>
                        </div>
                        <Button
                            v-if="treatment"
                            :disabled="pending || !!treatment.reason"
                            @click="purchase(treatment, disease.id)"
                        >
                            {{
                                form.processing &&
                                form.disease_episode_id === disease.id
                                    ? t('Processing...')
                                    : t('Treat for {coins} coins', {
                                          coins: number(treatment.price),
                                      })
                            }}
                        </Button>
                    </li>
                </ul>
                <p
                    v-if="
                        clinic.diseases.length &&
                        treatment?.reason &&
                        !clinic.reason
                    "
                    class="vet-reason"
                >
                    {{ t(treatment.reason) }}
                </p>
            </SurfaceCard>

            <div class="vet-services">
                <SurfaceCard
                    v-for="service in preventive"
                    :key="service.code"
                    :title="t(labels[service.code])"
                    class="vet-service"
                >
                    <p v-if="service.code === 'checkup'">
                        {{
                            t(
                                'Once every {days} days. Restores up to {health} health points.',
                                {
                                    days: clinic.checkupDays,
                                    health: clinic.checkupHealth,
                                },
                            )
                        }}
                    </p>
                    <p v-else>
                        {{
                            t(
                                '+{bonus}% health recovery during care for {days} days.',
                                {
                                    bonus: clinic.vaccinationBonus,
                                    days: clinic.vaccinationDays,
                                },
                            )
                        }}
                    </p>
                    <p>
                        {{
                            t(
                                'Checkups and vaccinations do not cure existing diseases.',
                            )
                        }}
                    </p>
                    <dl v-if="service.lastVisitAt" class="vet-dates">
                        <div>
                            <dt>{{ t('Last visit') }}</dt>
                            <dd>{{ date(service.lastVisitAt) }}</dd>
                        </div>
                        <div v-if="service.availableAt">
                            <dt>
                                {{
                                    t(
                                        service.code === 'vaccination'
                                            ? 'Vaccination active until'
                                            : 'Next checkup from',
                                    )
                                }}
                            </dt>
                            <dd>{{ date(service.availableAt) }}</dd>
                        </div>
                    </dl>
                    <p
                        v-if="service.reason && !clinic.reason"
                        class="vet-reason"
                    >
                        {{ t(service.reason) }}
                    </p>
                    <Button
                        :disabled="pending || !!service.reason"
                        @click="purchase(service)"
                    >
                        {{
                            form.processing && form.service === service.code
                                ? t('Processing...')
                                : t('Pay {coins} coins', {
                                      coins: number(service.price),
                                  })
                        }}
                    </Button>
                </SurfaceCard>
            </div>

            <SurfaceCard
                v-if="clinic.history.length"
                :title="t('Recent visits')"
            >
                <ul class="vet-history">
                    <li v-for="visit in clinic.history" :key="visit.id">
                        <div>
                            <h3>
                                {{ t(labels[visit.service])
                                }}<template v-if="visit.diseaseName">
                                    · {{ visit.diseaseName }}</template
                                >
                            </h3>
                            <p>{{ date(visit.performedAt) }}</p>
                        </div>
                        <span>{{
                            t('{coins} coins', { coins: number(visit.price) })
                        }}</span>
                    </li>
                </ul>
            </SurfaceCard>
        </template>
    </div>
</template>
