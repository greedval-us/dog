<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
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
import { surrender } from '@/routes/pets';

const props = defineProps<{
    petId: number;
    name: string;
    reason: string | null;
}>();
const { t } = useI18n();
const open = shallowRef(false);
const form = useForm({ ownership: '' });
const error = computed(() => Object.values(form.errors).join(' '));
function confirm(): void {
    if (form.processing || props.reason) return;
    form.transform(() => ({})).submit(surrender(props.petId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <div class="pet-surrender-action">
        <Button
            type="button"
            variant="outline"
            :disabled="Boolean(reason)"
            @click="
                open = true;
                form.clearErrors();
            "
            >{{ t('Transfer to a shelter') }}</Button
        >
        <Dialog v-model:open="open">
            <DialogContent
                :show-close-button="!form.processing"
                @escape-key-down="form.processing && $event.preventDefault()"
                @interact-outside="form.processing && $event.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        t('Transfer {name} to a shelter?', { name })
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t(
                            'Your dog leaves your care and frees a slot. You receive no coins. Its history is kept. This decision cannot be undone.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <InputError :message="error" role="alert" />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="form.processing"
                        @click="open = false"
                        >{{ t('Keep my dog') }}</Button
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="form.processing || Boolean(reason)"
                        :aria-busy="form.processing"
                        @click="confirm"
                        >{{
                            form.processing
                                ? t('Saving…')
                                : t('Confirm shelter transfer')
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
