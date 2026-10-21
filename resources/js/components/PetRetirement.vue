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
const { t } = useI18n();
const open = ref(false);
const form = useForm({});
const error = computed(() => Object.values(form.errors).join(' '));

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
            <Leaf :size="17" aria-hidden="true" />{{ t('End career') }}
        </Button>
        <p>
            {{
                t(
                    'Retirement is your choice. Age alone does not end your dog’s career.',
                )
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
                        t('End {name}’s career?', { name: pet.name })
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'Retirement ends competitions, exhibitions, breeding and paid work. Your dog stays home, keeps a slot and can still receive care and veterinary help.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <p class="field-hint">
                    {{
                        t('Care and any activity already in progress continue.')
                    }}
                </p>
                <InputError :message="error" role="alert" />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="form.processing"
                        @click="open = false"
                        >{{ t('Continue career') }}</Button
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
