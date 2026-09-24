<script setup lang="ts">
import DogLiveBrand from '@/components/DogLiveBrand.vue';
import DogLiveFooter from '@/components/DogLiveFooter.vue';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div class="public-shell">
        <a class="skip-link" href="#main-content">Перейти к содержимому</a>
        <header class="public-header"><DogLiveBrand /></header>
        <main id="main-content" class="auth-layout" tabindex="-1">
            <aside class="auth-story">
                <div class="auth-story-copy">
                    <span class="section-kicker">Твой маленький мир</span>
                    <h2>Дружба,<br />которая растёт<br />с каждым днём.</h2>
                    <p>Здесь начинается твоя история в DogLive.</p>
                </div>
                <img
                    src="/images/doglive-rey.png"
                    alt="Овчарка на солнечном горном лугу"
                    width="1536"
                    height="1024"
                />
                <span class="auth-story-caption"
                    >Больше заботы. Больше счастливых моментов.</span
                >
            </aside>
            <section class="auth-panel">
                <header class="auth-heading">
                    <span class="section-kicker"
                        >Добро пожаловать в DogLive</span
                    >
                    <h1>{{ title }}</h1>
                    <p>{{ description }}</p>
                </header>
                <slot />
            </section>
        </main>
        <DogLiveFooter />
    </div>
</template>
