<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Camera, ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import PlayerCard from '@/components/PlayerCard.vue';
import PlayerAvatarForm from '@/components/PlayerAvatarForm.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { show as playerProfile } from '@/routes/players';
import type { AvatarLimits, PlayerProfile } from '@/types/player';

defineProps<{ player: PlayerProfile; avatarLimits: AvatarLimits }>();
const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useI18n();
</script>
<template>
    <div class="settings-stack">
        <Head :title="t('Profile')" />
        <PlayerCard :player="player">
            <template #actions>
                <p class="field-hint">
                    {{
                        t(
                            'Other players can see your name, bio and game statistics.',
                        )
                    }}
                </p>
                <Button as-child variant="secondary"
                    ><Link :href="playerProfile(player.username)"
                        >{{ t('View player card') }}<ArrowUpRight /></Link
                ></Button>
            </template>
        </PlayerCard>
        <SurfaceCard
            :title="t('Edit profile')"
            :description="t('Introduce yourself to other DogLive players.')"
        >
            <div class="form-stack">
                <details class="player-avatar-editor">
                    <summary>
                        <Camera :size="19" aria-hidden="true" />
                        <span>{{
                            player.avatarVersion
                                ? t('Change avatar')
                                : t('Add avatar')
                        }}</span>
                        <ChevronDown
                            class="player-avatar-chevron"
                            :size="18"
                            aria-hidden="true"
                        />
                    </summary>
                    <PlayerAvatarForm
                        :limits="avatarLimits"
                        :has-avatar="player.avatarVersion !== null"
                    />
                </details>
                <Form
                    v-bind="ProfileController.update.form()"
                    class="form-stack"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <FormField
                        id="name"
                        :label="t('Name')"
                        :error="errors.name"
                        :hint="
                            t(
                                'Your display name. Your unique username stays the same.',
                            )
                        "
                        v-slot="{ field }"
                        ><Input
                            v-bind="field"
                            name="name"
                            :default-value="user.name"
                            required
                            :maxlength="255"
                            autocomplete="name"
                            :placeholder="t('Your name')"
                    /></FormField>
                    <FormField
                        id="bio"
                        :label="t('About me')"
                        :error="errors.bio"
                        :hint="
                            t(
                                'Up to 1,000 characters. Visible to other players.',
                            )
                        "
                        v-slot="{ field }"
                    >
                        <textarea
                            v-bind="field"
                            name="bio"
                            class="ui-input ui-textarea"
                            :value="player.bio ?? ''"
                            :maxlength="1000"
                            rows="5"
                            :placeholder="
                                t('Tell us about yourself and your dogs.')
                            "
                        ></textarea>
                    </FormField>
                    <FormField
                        id="email"
                        :label="t('Email address')"
                        :error="errors.email"
                        :hint="t('Your email is only visible to you.')"
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
                </Form>
            </div>
        </SurfaceCard>
        <DeleteUser />
    </div>
</template>
