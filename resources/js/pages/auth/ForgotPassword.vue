<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { login } from '@/routes';
import { email } from '@/routes/password';
defineOptions({
    layout: {
        title: 'Вернёмся к друзьям',
        description: 'Пришлём ссылку для восстановления пароля на твою почту.',
    },
});
defineProps<{ status?: string }>();
</script>
<template>
    <div class="auth-content">
        <Head title="Восстановление пароля" />
        <p v-if="status" class="form-notice" role="status">{{ status }}</p>
        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="form-stack"
            ><FormField
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
                    v-focus
                    placeholder="you@example.com" /></FormField
            ><FormActions
                :processing="processing"
                label="Отправить ссылку"
                test-id="email-password-reset-link-button"
            />
            <p class="auth-footer">
                <TextLink :href="login()">Вернуться ко входу</TextLink>
            </p></Form
        >
    </div>
</template>
