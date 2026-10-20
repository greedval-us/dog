<script setup lang="ts">
import { computed } from 'vue';
import FormField from '@/components/FormField.vue';
import { useI18n } from '@/composables/useI18n';
import { formatAgeMonths } from '@/lib/petAge';
import type { EventDog, EventEntryOption } from '@/types/game-event';

const props = defineProps<{
    dog: EventDog;
    options: EventEntryOption[];
    disabled: boolean;
    error?: string;
}>();
const selectedKey = defineModel<string>({ required: true });
const { t, locale, number } = useI18n();
const selected = computed(() =>
    props.options.find((item) => item.key === selectedKey.value),
);
const blocked = computed(() =>
    props.options.filter((item) => item.eligible === false && item.reason),
);
</script>

<template>
    <div class="event-classification">
        <p class="event-note">
            {{
                t('Age at the start: {age}', {
                    age: formatAgeMonths(dog.ageMonths, locale),
                })
            }}
        </p>
        <p v-if="dog.profile?.heightCm != null" class="event-note">
            {{
                t('Height at withers: {height} cm', {
                    height: number(dog.profile.heightCm),
                })
            }}<template v-if="dog.profile.heightCategory">
                · {{ dog.profile.heightCategory }}</template
            >
        </p>
        <FormField
            id="event-entry-option"
            :label="t('Performance class and programme')"
            :error="error"
            v-slot="{ field }"
        >
            <select
                v-bind="field"
                v-model="selectedKey"
                class="events-select"
                :disabled="disabled"
            >
                <option value="" disabled>
                    {{ t('Choose an eligible performance class') }}
                </option>
                <option
                    v-for="item in options"
                    :key="item.key"
                    :value="item.key"
                    :disabled="item.eligible === false"
                >
                    {{ item.label }}
                </option>
            </select>
        </FormField>
        <p v-if="selected?.categoryLabel" class="event-note">
            {{ selected.categoryLabel }}
        </p>
        <p
            v-if="!options.some((item) => item.eligible !== false)"
            class="event-note"
        >
            {{ t('No eligible performance class is available for this dog.') }}
        </p>
        <ul v-if="blocked.length" class="event-classification-reasons">
            <li v-for="item in blocked" :key="item.key">
                <strong>{{ item.label }}</strong> — {{ item.reason }}
            </li>
        </ul>
    </div>
</template>
