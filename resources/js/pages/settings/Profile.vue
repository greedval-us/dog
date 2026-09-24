<script setup lang="ts">
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
</script>
<template>
    <div class="settings-stack">
        <Head title="Профиль" />
        <SurfaceCard
            title="Твой профиль"
            description="Имя и почта, по которым мы тебя узнаем."
        >
            <div class="profile-summary">
                <span class="profile-avatar">{{
                    user.username?.charAt(0).toUpperCase()
                }}</span>
                <div>
                    <strong>{{ user.username }}</strong
                    ><span>Ник игрока</span>
                </div>
            </div>
            <Form
                v-bind="ProfileController.update.form()"
                class="form-stack"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <FormField
                    id="name"
                    label="Имя"
                    :error="errors.name"
                    v-slot="{ field }"
                    ><Input
                        v-bind="field"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        placeholder="Твоё имя"
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
