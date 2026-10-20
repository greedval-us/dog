<script setup lang="ts">
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';
import { useI18n } from '@/composables/useI18n';
import type { KennelAgeOption } from '@/types/pet-market';

defineProps<{
    options: KennelAgeOption[];
    disabled: boolean;
    error?: string;
}>();
const value = defineModel<number>({ required: true });
const id = useId();
const { t } = useI18n();
</script>

<template>
    <fieldset
        class="kennel-age-picker"
        :disabled="disabled"
        :aria-describedby="error ? id + '-error' : undefined"
    >
        <legend class="catalogue-field-legend">
            {{ t('Choose your dog’s age') }}
        </legend>
        <div class="kennel-age-options">
            <label
                v-for="option in options"
                :key="option.months"
                class="kennel-age-option"
                :class="{ 'is-selected': value === option.months }"
            >
                <input
                    v-model="value"
                    type="radio"
                    name="age_months"
                    :value="option.months"
                    required
                    :aria-invalid="Boolean(error)"
                />
                <span
                    ><strong>{{ option.label }}</strong
                    ><small>{{ option.description }}</small></span
                >
            </label>
        </div>
        <InputError :id="id + '-error'" :message="error" />
    </fieldset>
</template>
