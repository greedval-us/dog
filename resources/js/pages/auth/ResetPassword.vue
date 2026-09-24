<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/password';
defineOptions({
    layout: {
        title: 'New password',
        description: 'Choose a strong password for your account.',
    },
});
const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();
const inputEmail = ref(props.email);
const { t } = useI18n();
</script>
<template>
    <div class="auth-content">
        <Head :title="t('New password')" /><Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
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
                    autocomplete="email"
                    v-model="inputEmail"
                    readonly
            /></FormField>
            <FormField
                id="password"
                :label="t('New password')"
                :error="errors.password"
                v-slot="{ field }"
                ><PasswordInput
                    v-bind="field"
                    name="password"
                    autocomplete="new-password"
                    autofocus
                    required
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
                    autocomplete="new-password"
                    required
                    :placeholder="t('Enter the same password again')"
                    :passwordrules="passwordRules"
            /></FormField>
            <FormActions
                :processing="processing"
                :label="t('Save new password')"
                test-id="reset-password-button"
        /></Form>
    </div>
</template>
