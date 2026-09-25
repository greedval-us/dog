<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useI18n } from '@/composables/useI18n';
import { show } from '@/routes/players/avatar';

const props = withDefaults(
    defineProps<{
        username: string;
        version: string | null;
        size?: 'small' | 'large';
    }>(),
    { size: 'small' },
);
const { t } = useI18n();
const source = computed(() =>
    props.version
        ? show.url(props.username, { query: { v: props.version } })
        : undefined,
);
</script>

<template>
    <Avatar :class="size === 'large' ? 'profile-avatar' : 'player-avatar'">
        <AvatarImage
            v-if="source"
            :src="source"
            :alt="t('Avatar of {username}', { username })"
        />
        <AvatarFallback>{{ username.charAt(0).toUpperCase() }}</AvatarFallback>
    </Avatar>
</template>
