<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import { update } from '@/routes/boarding';
import type { BoardingSettings } from '@/types/player';
defineProps<{ boarding: BoardingSettings }>();
const { t, number } = useI18n();
</script>

<template>
    <SurfaceCard :title="t('Boarding while you are away')">
        <Form
            v-bind="update.form()"
            :options="{ preserveScroll: true }"
            class="form-stack"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <p class="field-hint">
                {{
                    t(
                        'Automatic boarding starts after {hours} h without game activity.',
                        { hours: number(boarding.inactivitySeconds / 3600) },
                    )
                }}
            </p>
            <FormField
                id="boarding-plan"
                :label="t('Boarding plan')"
                :error="errors.plan"
                v-slot="{ field }"
            >
                <select
                    v-bind="field"
                    name="plan"
                    class="ui-input"
                    :disabled="processing"
                >
                    <option value="off" :selected="boarding.plan === 'off'">
                        {{ t('Boarding off') }}
                    </option>
                    <option value="cheap" :selected="boarding.plan === 'cheap'">
                        {{
                            t(
                                'Basic boarding · {price} coins per dog per hour',
                                { price: number(boarding.hourlyPrices.cheap) },
                            )
                        }}
                    </option>
                    <option
                        value="premium"
                        :selected="boarding.plan === 'premium'"
                    >
                        {{
                            t(
                                'Premium boarding · {price} coins per dog per hour',
                                {
                                    price: number(
                                        boarding.hourlyPrices.premium,
                                    ),
                                },
                            )
                        }}
                    </option>
                </select>
            </FormField>
            <div class="boarding-details">
                <div>
                    <h3>{{ t('Basic boarding') }}</h3>
                    <strong>{{
                        t('{price} coins per dog per hour', {
                            price: number(boarding.hourlyPrices.cheap),
                        })
                    }}</strong>
                    <p class="field-hint">
                        {{
                            t(
                                'Bond −1 percentage point/h, mood −0.5 percentage points/h. Attributes decay twice as fast as normal.',
                            )
                        }}
                    </p>
                </div>
                <div>
                    <h3>{{ t('Premium boarding') }}</h3>
                    <strong>{{
                        t('{price} coins per dog per hour', {
                            price: number(boarding.hourlyPrices.premium),
                        })
                    }}</strong>
                    <p class="field-hint">
                        {{
                            t(
                                'Bond −0.5 percentage points/h, mood stays steady. Attributes decay by 0.03 points/h.',
                            )
                        }}
                    </p>
                </div>
            </div>
            <div class="boarding-payment-note">
                <p>
                    {{
                        t(
                            'Boarding maintains your dogs’ needs during paid hours.',
                        )
                    }}
                </p>
                <p>
                    {{
                        t(
                            'Each hour is paid in advance for each dog. If coins run out, new hours stop; a paid hour lasts until it ends.',
                        )
                    }}
                </p>
            </div>
            <FormActions
                :processing="processing"
                :saved="recentlySuccessful"
                test-id="update-boarding-button"
            />
        </Form>
    </SurfaceCard>
</template>
