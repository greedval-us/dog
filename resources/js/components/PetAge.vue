<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { formatAgeMonths } from '@/lib/petAge';
import type { PetAge } from '@/types/pet';
import type { PetLifecycleStatus } from '@/types/pet-pedigree';

defineProps<{ age?: PetAge; status: PetLifecycleStatus; compact?: boolean }>();
const { t, locale } = useI18n();
</script>

<template>
    <div v-if="age" class="pet-age-summary">
        <p>
            <strong>{{
                t('Age: {age}', { age: formatAgeMonths(age.months, locale) })
            }}</strong>
        </p>
        <p v-if="!compact" class="field-hint">
            {{ t('One real day equals one game month.') }}
        </p>
        <template
            v-if="!compact && (status === 'active' || status === 'retired')"
        >
            <p v-if="age.stage === 'senior'" class="field-hint">
                {{
                    t(
                        'Available events depend on their age and qualification rules. You decide when to end your dog’s career.',
                    )
                }}
            </p>
            <p v-if="age.stage === 'retired'" class="field-hint">
                {{ t('Care continues and your dog keeps a slot.') }}
            </p>
            <p
                v-if="age.stage === 'senior' || age.stage === 'retired'"
                class="field-hint"
            >
                {{
                    t(
                        'Physical condition changes with age. Lifespan depends on breed and size: 12–16 game years.',
                    )
                }}
            </p>
        </template>
    </div>
</template>
