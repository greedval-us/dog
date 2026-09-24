<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
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
        title: 'Let us start our story',
        description: 'Create an account and make yourself at home in DogLive.',
    },
});
const { t } = useI18n();
</script>
<template>
    <div class="auth-content">
        <Head :title="t('Registration')" />
        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="form-stack"
        >
            <FormField
                id="name"
                :label="t('What is your name?')"
                :error="errors.name"
                v-slot="{ field }"
                ><Input
                    v-bind="field"
                    name="name"
                    required
                    v-focus
                    autocomplete="name"
                    :placeholder="t('Your name')"
            /></FormField>
            <FormField
                id="username"
                :label="t('Player username')"
                :error="errors.username"
                :hint="
                    t(
                        'Up to 32 characters: lowercase Latin letters, numbers and underscores.',
                    )
                "
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
                :label="t('Email address')"
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
                :label="t('Password')"
                :error="errors.password"
                v-slot="{ field }"
                ><PasswordInput
                    v-bind="field"
                    name="password"
                    required
                    autocomplete="new-password"
                    :placeholder="t('Choose a password')"
                    :passwordrules="passwordRules"
            /></FormField>
            <FormField
                id="password_confirmation"
                :label="t('Confirm password')"
                :error="errors.password_confirmation"
                v-slot="{ field }"
                ><PasswordInput
                    v-bind="field"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    :placeholder="t('Enter the same password again')"
                    :passwordrules="passwordRules"
            /></FormField>
            <FormActions
                :processing="processing"
                :label="t('Create account')"
                test-id="register-user-button"
            />
            <p class="auth-footer">
                {{ t('Already part of DogLive?') }}
                <TextLink :href="login()">{{ t('Log in') }}</TextLink>
            </p>
        </Form>
    </div>
</template>
