<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { login } from '@/routes';
import { store } from '@/routes/register';
defineProps<{ passwordRules: string }>();
defineOptions({
    layout: {
        title: 'Начнём нашу историю',
        description: 'Создай аккаунт и обустрой своё место в DogLive.',
    },
});
</script>
<template>
    <div class="auth-content">
        <Head title="Регистрация" />
        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="form-stack"
        >
            <FormField
                id="name"
                label="Как тебя зовут?"
                :error="errors.name"
                v-slot="{ field }"
                ><Input
                    v-bind="field"
                    name="name"
                    required
                    v-focus
                    autocomplete="name"
                    placeholder="Твоё имя"
            /></FormField>
            <FormField
                id="username"
                label="Ник игрока"
                :error="errors.username"
                hint="До 32 символов: маленькие латинские буквы, цифры и знак _."
                v-slot="{ field }"
                ><Input
                    v-bind="field"
                    name="username"
                    required
                    maxlength="32"
                    pattern="[a-z0-9_]+"
                    autocomplete="username"
                    autocapitalize="none"
                    placeholder="alex_greed"
            /></FormField>
            <FormField
                id="email"
                label="Электронная почта"
                :error="errors.email"
                v-slot="{ field }"
                ><Input
                    v-bind="field"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="you@example.com"
            /></FormField>
            <FormField
                id="password"
                label="Пароль"
                :error="errors.password"
                v-slot="{ field }"
                ><PasswordInput
                    v-bind="field"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Придумай пароль"
                    :passwordrules="passwordRules"
            /></FormField>
            <FormField
                id="password_confirmation"
                label="Повтори пароль"
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
                label="Создать аккаунт"
                test-id="register-user-button"
            />
            <p class="auth-footer">
                Уже с нами? <TextLink :href="login()">Войти</TextLink>
            </p>
        </Form>
    </div>
</template>
