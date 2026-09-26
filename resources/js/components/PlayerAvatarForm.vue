<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { store, destroy } from '@/routes/players/avatar';
import type { AvatarLimits } from '@/types/player';

const props = defineProps<{ limits: AvatarLimits; hasAvatar: boolean }>();
const { t, number } = useI18n();
const upload = useForm<{ avatar: File | null }>({ avatar: null });
const removal = useForm({});
const fileInput = ref<HTMLInputElement | null>(null);
const processing = computed(() => upload.processing || removal.processing);
const maxMegabytes = computed(() => number(props.limits.max_kilobytes / 1024));

function selectFile(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    upload.clearErrors();
    upload.avatar = null;

    if (file && file.size > props.limits.max_kilobytes * 1024) {
        upload.setError(
            'avatar',
            t('The avatar must not exceed {size} MB.', {
                size: maxMegabytes.value,
            }),
        );
        input.value = '';
        return;
    }

    upload.avatar = file;
}

function resetFile() {
    upload.reset();
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function submit() {
    if (!processing.value) {
        upload.post(store.url(), {
            preserveScroll: true,
            onSuccess: resetFile,
        });
    }
}

function remove() {
    if (!processing.value) {
        removal.delete(destroy.url(), {
            preserveScroll: true,
            onSuccess: () => {
                resetFile();
                upload.clearErrors();
            },
        });
    }
}
</script>

<template>
    <form class="form-stack player-avatar-form" @submit.prevent="submit">
        <FormField
            id="player-avatar-file"
            :label="t('Avatar')"
            :error="upload.errors.avatar"
            :hint="
                t(
                    'JPG, PNG or WebP. Up to {size} MB and {dimension} × {dimension} pixels. Saved at up to {stored} pixels.',
                    {
                        size: maxMegabytes,
                        dimension: limits.max_dimension,
                        stored: limits.stored_dimension,
                    },
                )
            "
            v-slot="{ field }"
        >
            <input
                v-bind="field"
                ref="fileInput"
                class="ui-input avatar-file-input"
                type="file"
                name="avatar"
                accept="image/jpeg,image/png,image/webp"
                required
                :disabled="processing"
                @change="selectFile"
            />
        </FormField>
        <p class="field-hint">
            {{ t('Your avatar is visible only to signed-in players.') }}
        </p>
        <p v-if="upload.progress" class="field-hint" role="status">
            {{
                t('Uploading: {percent}%', {
                    percent: upload.progress.percentage ?? 0,
                })
            }}
        </p>
        <div class="player-avatar-actions">
            <FormActions
                :processing="processing"
                :saved="upload.recentlySuccessful"
                :label="t('Save avatar')"
                test-id="save-avatar-button"
            />
            <Button
                v-if="hasAvatar"
                type="button"
                variant="secondary"
                :disabled="processing"
                @click="remove"
            >
                {{ t('Remove avatar') }}
            </Button>
            <span
                v-if="removal.recentlySuccessful"
                class="form-success"
                role="status"
                >{{ t('Avatar removed') }}</span
            >
        </div>
    </form>
</template>
