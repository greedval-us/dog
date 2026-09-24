<script setup lang="ts">
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
</script>
<template>
    <SurfaceCard
        title="Удаление аккаунта"
        description="Управление твоими данными в DogLive."
        class="danger-card"
    >
        <p class="danger-message">
            Удаление аккаунта необратимо. Все связанные с ним данные будут
            удалены.
        </p>
        <Dialog
            ><DialogTrigger as-child
                ><Button variant="destructive" data-test="delete-user-button"
                    >Удалить аккаунт</Button
                ></DialogTrigger
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
                        ><DialogTitle>Удалить аккаунт?</DialogTitle
                        ><DialogDescription
                            >После удаления восстановить аккаунт и его данные не
                            получится. Введи пароль, чтобы подтвердить
                            действие.</DialogDescription
                        ></DialogHeader
                    >
                    <FormField
                        id="delete-password"
                        label="Текущий пароль"
                        :error="errors.password"
                        v-slot="{ field }"
                        ><PasswordInput
                            v-bind="field"
                            name="password"
                            ref="passwordInput"
                            required
                            autocomplete="current-password"
                            placeholder="Твой пароль"
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
                                >Отмена</Button
                            ></DialogClose
                        ><Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-delete-user-button"
                            >Удалить навсегда</Button
                        ></DialogFooter
                    >
                </Form></DialogContent
            >
        </Dialog>
    </SurfaceCard>
</template>
