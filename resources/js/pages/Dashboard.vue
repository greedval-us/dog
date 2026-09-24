<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/profile';
const { t } = useI18n();
</script>

<template>
    <div class="my-dog-page">
        <Head :title="t('My dog')" />
        <Heading
            :title="t('My dog')"
            :description="t('A place for your future best friend.')"
        />
        <EmptyState
            :title="t('You do not have a dog yet')"
            :description="
                t(
                    'When you get a dog, this is where you will find their photos, personality and everything you need to care for them.',
                )
            "
        >
            <p class="empty-state-note">
                {{
                    t(
                        'The option to get your first dog is still in development.',
                    )
                }}
            </p>
            <Button as-child variant="secondary"
                ><Link :href="edit()"
                    >{{ t('Set up your profile') }} <ArrowRight /></Link
            ></Button>
        </EmptyState>
    </div>
</template>
