<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
defineOptions({
    layout: {
        title: 'С возвращением',
        description: 'Войди в свой маленький мир DogLive.',
    },
});
defineProps<{ status?: string; canResetPassword: boolean }>();
</script>
<template>
    <div class="auth-content">
        <Head title="Вход" />
        <p v-if="status" class="form-notice" role="status">{{ status }}</p>
        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
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
                    required
                    v-focus
                    autocomplete="email"
                    placeholder="you@example.com"
            /></FormField>
            <FormField id="password" label="Пароль" :error="errors.password"
                ><template #aside
                    ><TextLink v-if="canResetPassword" :href="request()"
                        >Забыли пароль?</TextLink
                    ></template
                ><template #default="{ field }"
                    ><PasswordInput
                        v-bind="field"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Твой пароль" /></template
            ></FormField>
            <Label for="remember" class="remember-field"
                ><Checkbox id="remember" name="remember" /><span
                    >Запомнить меня</span
                ></Label
            >
            <FormActions
                :processing="processing"
                label="Войти"
                test-id="login-button"
            />
            <p class="auth-footer">
                Ещё нет аккаунта?
                <TextLink :href="register()">Зарегистрироваться</TextLink>
            </p>
        </Form>
    </div>
</template>
