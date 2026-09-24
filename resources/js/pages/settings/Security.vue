<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
defineProps<{ passwordRules: string }>();
</script>
<template>
    <div class="settings-stack">
        <Head title="Безопасность" /><SurfaceCard
            title="Смена пароля"
            description="Выбери длинный, уникальный пароль, чтобы защитить аккаунт."
        >
            <Form
                v-bind="SecurityController.update.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="form-stack"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <FormField
                    id="current_password"
                    label="Текущий пароль"
                    :error="errors.current_password"
                    v-slot="{ field }"
                    ><PasswordInput
                        v-bind="field"
                        name="current_password"
                        required
                        autocomplete="current-password"
                        placeholder="Текущий пароль"
                /></FormField>
                <FormField
                    id="password"
                    label="Новый пароль"
                    :error="errors.password"
                    v-slot="{ field }"
                    ><PasswordInput
                        v-bind="field"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Придумай новый пароль"
                        :passwordrules="passwordRules"
                /></FormField>
                <FormField
                    id="password_confirmation"
                    label="Повтори новый пароль"
                    :error="errors.password_confirmation"
                    v-slot="{ field }"
                    ><PasswordInput
                        v-bind="field"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Ещё раз тот же пароль"
                        :passwordrules="passwordRules"
                /></FormField>
                <FormActions
                    :processing="processing"
                    :saved="recentlySuccessful"
                    label="Обновить пароль"
                    test-id="update-password-button" /></Form
        ></SurfaceCard>
    </div>
</template>
