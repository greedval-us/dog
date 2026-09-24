<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Input } from '@/components/ui/input';
const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useI18n();
</script>
<template>
    <div class="settings-stack">
        <Head :title="t('Profile')" />
        <SurfaceCard
            :title="t('Your profile')"
            :description="t('The name and email address we know you by.')"
        >
            <div class="profile-summary">
                <span class="profile-avatar">{{
                    user.username?.charAt(0).toUpperCase()
                }}</span>
                <div>
                    <strong>{{ user.username }}</strong
                    ><span>{{ t('Player username') }}</span>
                </div>
            </div>
            <Form
                v-bind="ProfileController.update.form()"
                class="form-stack"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <FormField
                    id="name"
                    :label="t('Name')"
                    :error="errors.name"
                    v-slot="{ field }"
                    ><Input
                        v-bind="field"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        :placeholder="t('Your name')"
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
                        :default-value="user.email"
                        required
                        autocomplete="email"
                        placeholder="you@example.com"
                /></FormField>
                <FormActions
                    :processing="processing"
                    :saved="recentlySuccessful"
                    test-id="update-profile-button"
                />
            </Form> </SurfaceCard
        ><DeleteUser />
    </div>
</template>
