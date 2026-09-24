<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head } from '@inertiajs/vue3';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { login } from '@/routes';
import { email } from '@/routes/password';
defineOptions({
    layout: {
        title: 'Back to your friends',
        description: 'We will email you a link to reset your password.',
    },
});
defineProps<{ status?: string }>();
const { t } = useI18n();
</script>
<template>
    <div class="auth-content">
        <Head :title="t('Password recovery')" />
        <p v-if="status" class="form-notice" role="status">{{ status }}</p>
        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="form-stack"
            ><FormField
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
                    v-focus
                    placeholder="you@example.com" /></FormField
            ><FormActions
                :processing="processing"
                :label="t('Send reset link')"
                test-id="email-password-reset-link-button"
            />
            <p class="auth-footer">
                <TextLink :href="login()">{{ t('Back to login') }}</TextLink>
            </p></Form
        >
    </div>
</template>
