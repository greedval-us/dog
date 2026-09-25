<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import PlayerCard from '@/components/PlayerCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { edit } from '@/routes/profile';
import type { AvatarLimits, PlayerProfile } from '@/types/player';

defineProps<{
    player: PlayerProfile;
    isOwner: boolean;
    avatarLimits: AvatarLimits | null;
}>();
const { t } = useI18n();
</script>

<template>
    <div class="player-profile-page">
        <Head
            :title="
                t('Player card — {username}', { username: player.username })
            "
        />
        <Heading
            :title="t('Player card')"
            :description="t('A little about the person behind the care.')"
        />
        <PlayerCard
            :player="player"
            :avatar-limits="isOwner ? avatarLimits : null"
        >
            <template v-if="isOwner" #actions>
                <Button as-child variant="secondary"
                    ><Link :href="edit()"
                        ><Pencil />{{ t('Edit profile') }}</Link
                    ></Button
                >
            </template>
        </PlayerCard>
    </div>
</template>
