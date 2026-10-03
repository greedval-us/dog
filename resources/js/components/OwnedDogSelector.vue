<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { useI18n } from '@/composables/useI18n';

const props = withDefaults(
    defineProps<{
        id: string;
        dogs: {
            id: number;
            name: string;
            busy?: boolean;
            retired?: boolean;
        }[];
        selectedPetId: number | null;
        selectClass: string;
        disabled?: boolean;
        showStatus?: boolean;
    }>(),
    { disabled: false, showStatus: false },
);
const emit = defineEmits<{ select: [petId: number] }>();
const { t } = useI18n();

function selectDog(event: Event) {
    if (props.disabled) return;
    emit('select', Number((event.target as HTMLSelectElement).value));
}
</script>

<template>
    <FormField :id="id" :label="t('Choose a dog')" v-slot="{ field }">
        <select
            v-bind="field"
            :class="selectClass"
            :value="selectedPetId"
            :disabled="disabled"
            @change="selectDog"
        >
            <option v-for="dog in dogs" :key="dog.id" :value="dog.id">
                {{ dog.name
                }}{{
                    showStatus && dog.retired
                        ? ' · ' + t('Retired')
                        : showStatus && dog.busy
                          ? ' · ' + t('Busy')
                          : ''
                }}
            </option>
        </select>
    </FormField>
</template>
