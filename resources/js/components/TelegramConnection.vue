<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Check, MessageCircle, Unlink } from '@lucide/vue';
import { computed, shallowRef } from 'vue';
import InputError from '@/components/InputError.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { useServerClock } from '@/composables/useServerClock';
import { backgroundRequestHeaders } from '@/lib/backgroundRequests';
import { link, unlink } from '@/routes/telegram';
import type { TelegramConnectionData } from '@/types/telegram';

const props = defineProps<{ telegram: TelegramConnectionData }>();
const { t } = useI18n();
const page = usePage();
const connection = useForm({});
const removal = useForm({});
const checking = shallowRef(false);
const confirmUnlink = shallowRef(false);
const { now } = useServerClock(() => props.telegram.serverNow);
const processing = computed(
    () => connection.processing || removal.processing || checking.value,
);
const available = computed(
    () => props.telegram.available && props.telegram.status !== 'unavailable',
);
const connected = computed(
    () =>
        props.telegram.status === 'connected' &&
        props.telegram.account !== null,
);
const remainingSeconds = computed(() => {
    const expiresAt = Date.parse(props.telegram.link?.expiresAt ?? '');
    return Number.isFinite(expiresAt) && now.value > 0
        ? Math.max(0, Math.ceil((expiresAt - now.value) / 1000))
        : 0;
});
const activeLink = computed(() =>
    props.telegram.status === 'pending' && remainingSeconds.value > 0
        ? props.telegram.link
        : null,
);
const expired = computed(
    () =>
        props.telegram.status === 'expired' ||
        (props.telegram.status === 'pending' && !activeLink.value),
);
const countdown = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = String(remainingSeconds.value % 60).padStart(2, '0');
    return `${minutes}:${seconds}`;
});
const error = computed(
    () =>
        Object.values(connection.errors).join(' ') ||
        Object.values(removal.errors).join(' ') ||
        page.props.errors.telegram ||
        '',
);

function createLink(): void {
    if (processing.value || !available.value || connected.value) return;
    connection.clearErrors();
    removal.clearErrors();
    connection.submit(link(), { preserveScroll: true });
}

function checkConnection(): void {
    if (processing.value || !available.value || connected.value) return;
    checking.value = true;
    router.reload({
        only: ['telegram'],
        headers: backgroundRequestHeaders,
        onFinish: () => {
            checking.value = false;
        },
    });
}

function disconnect(): void {
    if (processing.value || !connected.value || !confirmUnlink.value) return;
    removal.clearErrors();
    connection.clearErrors();
    removal.submit(unlink(), {
        preserveScroll: true,
        onSuccess: () => {
            confirmUnlink.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="confirmUnlink">
        <SurfaceCard
            :title="t('Telegram')"
            :description="t('Connect Telegram to play DogLive in the bot.')"
        >
            <div class="form-stack">
                <template v-if="connected && telegram.account">
                    <div class="grid min-w-0 gap-2">
                        <p
                            class="form-success flex items-center gap-2"
                            role="status"
                        >
                            <Check :size="17" aria-hidden="true" />
                            {{ t('Telegram connected') }}
                        </p>
                        <p class="min-w-0 wrap-anywhere">
                            <strong>{{ telegram.account.firstName }}</strong>
                            <span
                                v-if="telegram.account.username"
                                class="field-hint"
                            >
                                @{{ telegram.account.username }}
                            </span>
                        </p>
                    </div>
                    <p v-if="!available" class="field-hint">
                        {{
                            t('Telegram connection is temporarily unavailable.')
                        }}
                    </p>
                    <div class="form-actions">
                        <Button v-if="telegram.botUrl && !processing" as-child>
                            <a
                                :href="telegram.botUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ t('Open Telegram bot') }}
                                <ArrowUpRight :size="17" aria-hidden="true" />
                            </a>
                        </Button>
                        <DialogTrigger as-child>
                            <Button
                                type="button"
                                variant="secondary"
                                :disabled="processing"
                            >
                                <Unlink :size="17" aria-hidden="true" />
                                {{ t('Disconnect Telegram') }}
                            </Button>
                        </DialogTrigger>
                    </div>
                </template>
                <p v-else-if="!available" class="field-hint">
                    {{ t('Telegram connection is temporarily unavailable.') }}
                </p>
                <template v-else>
                    <template v-if="activeLink">
                        <p class="field-hint">
                            {{
                                t(
                                    'Open the bot and press Start, then check the connection here.',
                                )
                            }}
                        </p>
                        <p class="field-hint">
                            {{
                                t('Link expires in {time}', { time: countdown })
                            }}
                        </p>
                    </template>
                    <p v-else-if="expired" class="field-hint" role="status">
                        {{
                            t(
                                'This connection link has expired. Create a new one.',
                            )
                        }}
                    </p>
                    <p v-else class="field-hint">
                        {{
                            t('Telegram is not connected to your account yet.')
                        }}
                    </p>
                    <div class="form-actions">
                        <Button v-if="activeLink && !processing" as-child>
                            <a
                                :href="activeLink.url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ t('Open Telegram bot') }}
                                <ArrowUpRight :size="17" aria-hidden="true" />
                            </a>
                        </Button>
                        <Button
                            v-if="telegram.status === 'pending' || expired"
                            type="button"
                            variant="secondary"
                            :disabled="processing"
                            :aria-busy="checking"
                            @click="checkConnection"
                        >
                            {{
                                checking
                                    ? t('Checking…')
                                    : t('Check connection')
                            }}
                        </Button>
                        <Button
                            type="button"
                            :variant="activeLink ? 'secondary' : 'default'"
                            :disabled="processing"
                            :aria-busy="connection.processing"
                            @click="createLink"
                        >
                            <MessageCircle :size="17" aria-hidden="true" />
                            {{
                                connection.processing
                                    ? t('Creating link…')
                                    : activeLink || expired
                                      ? t('Create new link')
                                      : t('Connect Telegram')
                            }}
                        </Button>
                    </div>
                </template>
                <InputError :message="error" role="alert" />
            </div>
            <DialogContent
                :show-close-button="!removal.processing"
                @escape-key-down="removal.processing && $event.preventDefault()"
                @interact-outside="
                    removal.processing && $event.preventDefault()
                "
            >
                <DialogHeader>
                    <DialogTitle>{{ t('Disconnect Telegram?') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'You will no longer be able to manage your pets in the bot. You can connect Telegram again at any time.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <InputError :message="error" role="alert" />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="removal.processing"
                        @click="confirmUnlink = false"
                    >
                        {{ t('Cancel') }}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="removal.processing"
                        :aria-busy="removal.processing"
                        @click="disconnect"
                    >
                        {{
                            removal.processing
                                ? t('Disconnecting…')
                                : t('Disconnect Telegram')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </SurfaceCard>
    </Dialog>
</template>
