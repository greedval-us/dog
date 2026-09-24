<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/profile';
</script>

<template>
    <div class="my-dog-page">
        <Head title="Моя собака" />
        <Heading
            title="Моя собака"
            description="Место для твоего будущего лучшего друга."
        />
        <EmptyState
            title="У тебя пока нет собаки"
            description="Когда у тебя появится питомец, здесь будут его фотографии, характер и всё для заботы о нём."
        >
            <p class="empty-state-note">
                Возможность завести первого питомца ещё в разработке.
            </p>
            <Button as-child variant="secondary"
                ><Link :href="edit()"
                    >Настроить свой профиль <ArrowRight /></Link
            ></Button>
        </EmptyState>
    </div>
</template>
