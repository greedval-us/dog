<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Leaf } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { retire } from '@/routes/pets';
import type { PlayerPet } from '@/types/pet';

const props = defineProps<{ pet: PlayerPet }>();
const { t, locale } = useI18n();
const open = ref(false);
const form = useForm({});
const error = computed(() => Object.values(form.errors).join(' '));
const automaticRetirementDate = computed(() =>
    new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(props.pet.lifecycle.automaticRetirementAt)),
);

function retirePet(): void {
    if (form.processing || !props.pet.lifecycle.canRetire) return;
    form.clearErrors();
    form.submit(retire(props.pet.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <div class="pet-retirement">
        <Button
            variant="secondary"
            @click="
                open = true;
                form.clearErrors();
            "
        >
            <Leaf :size="17" aria-hidden="true" />{{ t('Send to retirement') }}
        </Button>
        <p>
            {{
                t('Automatic retirement: {date}', {
                    date: automaticRetirementDate,
                })
            }}
        </p>
        <Dialog v-model:open="open">
            <DialogContent
                :show-close-button="!form.processing"
                @escape-key-down="form.processing && $event.preventDefault()"
                @interact-outside="form.processing && $event.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        t('Retire {name}?', { name: pet.name })
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'Your dog will leave active play and free a slot. The card and all characteristics will be preserved in the Pet memorial hall. Retirement is permanent.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <p class="field-hint">
                    {{
                        t(
                            'Any activity in progress will end without a reward or experience.',
                        )
                    }}
                </p>
                <InputError :message="error" role="alert" />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="form.processing"
                        @click="open = false"
                        >{{ t('Keep playing together') }}</Button
                    >
                    <Button
                        type="button"
                        :disabled="form.processing"
                        :aria-busy="form.processing"
                        @click="retirePet"
                        ><Leaf :size="17" aria-hidden="true" />{{
                            form.processing
                                ? t('Saving…')
                                : t('Confirm retirement')
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
