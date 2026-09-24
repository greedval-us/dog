<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
defineProps<{ passwordRules: string }>();
const { t } = useI18n();
</script>
<template>
    <div class="settings-stack">
        <Head :title="t('Security')" /><SurfaceCard
            :title="t('Change password')"
            :description="
                t('Choose a long, unique password to protect your account.')
            "
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
                    :label="t('Current password')"
                    :error="errors.current_password"
                    v-slot="{ field }"
                    ><PasswordInput
                        v-bind="field"
                        name="current_password"
                        required
                        autocomplete="current-password"
                        :placeholder="t('Current password')"
                /></FormField>
                <FormField
                    id="password"
                    :label="t('New password')"
                    :error="errors.password"
                    v-slot="{ field }"
                    ><PasswordInput
                        v-bind="field"
                        name="password"
                        required
                        autocomplete="new-password"
                        :placeholder="t('Choose a new password')"
                        :passwordrules="passwordRules"
                /></FormField>
                <FormField
                    id="password_confirmation"
                    :label="t('Confirm new password')"
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
                    :saved="recentlySuccessful"
                    :label="t('Update password')"
                    test-id="update-password-button" /></Form
        ></SurfaceCard>
    </div>
</template>
