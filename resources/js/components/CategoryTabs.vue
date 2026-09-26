<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';

defineProps<{
    categories: { id: number; name: string }[];
    selectedCategory: number | null;
    categoryLink: (category: number | null) => string;
    label: string;
    allLabel: string;
    disabled: boolean;
}>();
const emit = defineEmits<{ start: []; finish: [] }>();
</script>

<template>
    <nav class="shop-categories" :aria-label="label" :inert="disabled">
        <Link
            :href="categoryLink(null)"
            class="shop-category"
            :class="{ 'is-active': selectedCategory === null }"
            :aria-current="selectedCategory === null ? 'page' : undefined"
            preserve-scroll
            @start="emit('start')"
            @finish="emit('finish')"
        >
            <SlidersHorizontal :size="16" aria-hidden="true" />{{ allLabel }}
        </Link>
        <Link
            v-for="category in categories"
            :key="category.id"
            :href="categoryLink(category.id)"
            class="shop-category"
            :class="{ 'is-active': selectedCategory === category.id }"
            :aria-current="
                selectedCategory === category.id ? 'page' : undefined
            "
            preserve-scroll
            @start="emit('start')"
            @finish="emit('finish')"
            >{{ category.name }}</Link
        >
    </nav>
</template>
