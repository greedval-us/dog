<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
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
        title: 'Welcome back',
        description: 'Log in to your little world of DogLive.',
    },
});
defineProps<{ status?: string; canResetPassword: boolean }>();
const { t } = useI18n();
</script>
<template>
    <div class="auth-content">
        <Head :title="t('Login')" />
        <p v-if="status" class="form-notice" role="status">{{ status }}</p>
        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="form-stack"
        >
            <FormField
                id="email"
                :label="t('Email address')"
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
            <FormField
                id="password"
                :label="t('Password')"
                :error="errors.password"
                ><template #aside
                    ><TextLink v-if="canResetPassword" :href="request()">{{
                        t('Forgot your password?')
                    }}</TextLink></template
                ><template #default="{ field }"
                    ><PasswordInput
                        v-bind="field"
                        name="password"
                        required
                        autocomplete="current-password"
                        :placeholder="t('Your password')" /></template
            ></FormField>
            <Label for="remember" class="remember-field"
                ><Checkbox
                    id="remember"
                    name="remember"
                    :aria-label="t('Remember me')"
                /><span>{{ t('Remember me') }}</span></Label
            >
            <FormActions
                :processing="processing"
                :label="t('Log in')"
                test-id="login-button"
            />
            <p class="auth-footer">
                {{ t('Do not have an account yet?') }}
                <TextLink :href="register()">{{ t('Sign up') }}</TextLink>
            </p>
        </Form>
    </div>
</template>
