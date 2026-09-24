<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
</script>
<template>
    <div class="settings-stack">
        <Head title="Оформление" /><SurfaceCard
            title="Твой DogLive"
            description="Выбери настроение интерфейса. Настройка сохраняется на этом устройстве."
            ><AppearanceTabs
        /></SurfaceCard>
    </div>
</template>
