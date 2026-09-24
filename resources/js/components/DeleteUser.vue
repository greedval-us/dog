<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
const passwordInput = useTemplateRef('passwordInput');
const { t } = useI18n();
</script>
<template>
    <SurfaceCard
        :title="t('Account deletion')"
        :description="t('Manage your DogLive data.')"
        class="danger-card"
    >
        <p class="danger-message">
            {{
                t(
                    'Deleting your account is permanent. All associated data will be deleted.',
                )
            }}
        </p>
        <Dialog
            ><DialogTrigger as-child
                ><Button variant="destructive" data-test="delete-user-button">{{
                    t('Delete account')
                }}</Button></DialogTrigger
            >
            <DialogContent
                ><Form
                    v-bind="ProfileController.destroy.form()"
                    reset-on-success
                    @error="() => passwordInput?.focus()"
                    :options="{ preserveScroll: true }"
                    class="form-stack"
                    v-slot="{ errors, processing, reset, clearErrors }"
                >
                    <DialogHeader
                        ><DialogTitle>{{
                            t('Delete your account?')
                        }}</DialogTitle
                        ><DialogDescription>{{
                            t(
                                'Your account and its data cannot be recovered after deletion. Enter your password to confirm.',
                            )
                        }}</DialogDescription></DialogHeader
                    >
                    <FormField
                        id="delete-password"
                        :label="t('Current password')"
                        :error="errors.password"
                        v-slot="{ field }"
                        ><PasswordInput
                            v-bind="field"
                            name="password"
                            ref="passwordInput"
                            required
                            autocomplete="current-password"
                            :placeholder="t('Your password')"
                    /></FormField>
                    <DialogFooter
                        ><DialogClose as-child
                            ><Button
                                type="button"
                                variant="secondary"
                                @click="
                                    () => {
                                        clearErrors();
                                        reset();
                                    }
                                "
                                >{{ t('Cancel') }}</Button
                            ></DialogClose
                        ><Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-delete-user-button"
                            >{{ t('Delete permanently') }}</Button
                        ></DialogFooter
                    >
                </Form></DialogContent
            >
        </Dialog>
    </SurfaceCard>
</template>
