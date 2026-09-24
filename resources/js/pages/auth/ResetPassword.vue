<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/password';
defineOptions({
    layout: {
        title: 'Новый пароль',
        description: 'Придумай надёжный пароль для своего аккаунта.',
    },
});
const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();
const inputEmail = ref(props.email);
</script>
<template>
    <div class="auth-content">
        <Head title="Новый пароль" /><Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="form-stack"
        >
            <FormField
                id="email"
                label="Электронная почта"
                :error="errors.email"
                v-slot="{ field }"
                ><Input
                    v-bind="field"
                    type="email"
                    name="email"
                    autocomplete="email"
                    v-model="inputEmail"
                    readonly
            /></FormField>
            <FormField
                id="password"
                label="Новый пароль"
                :error="errors.password"
                v-slot="{ field }"
                ><PasswordInput
                    v-bind="field"
                    name="password"
                    autocomplete="new-password"
                    autofocus
                    required
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
                    autocomplete="new-password"
                    required
                    placeholder="Ещё раз тот же пароль"
                    :passwordrules="passwordRules"
            /></FormField>
            <FormActions
                :processing="processing"
                label="Сохранить новый пароль"
                test-id="reset-password-button"
        /></Form>
    </div>
</template>
