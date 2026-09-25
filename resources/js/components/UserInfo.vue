<script setup lang="ts">
import PlayerAvatar from '@/components/PlayerAvatar.vue';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

withDefaults(defineProps<Props>(), {
    showEmail: false,
});
</script>

<template>
    <div class="user-info">
        <PlayerAvatar :username="user.username" :version="user.avatarVersion" />

        <div class="grid flex-1 text-left text-sm leading-tight">
            <span class="truncate font-medium">{{ user.name }}</span>
            <span
                v-if="showEmail"
                class="truncate text-xs text-muted-foreground"
                >{{ user.email }}</span
            >
        </div>
    </div>
</template>
