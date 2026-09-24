<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Heart, PawPrint, Sprout } from '@lucide/vue';
import DogLiveBrand from '@/components/DogLiveBrand.vue';
import DogLiveFooter from '@/components/DogLiveFooter.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
const page = usePage();
const features = [
    {
        icon: Heart,
        title: 'Забота каждый день',
        text: 'Маленькие привычки, из которых складывается большая дружба.',
    },
    {
        icon: PawPrint,
        title: 'У каждого свой характер',
        text: 'Мир собак, в котором интересно узнавать своего друга.',
    },
    {
        icon: Sprout,
        title: 'Расти вместе',
        text: 'Новые впечатления и счастливые моменты рядом.',
    },
];
</script>

<template>
    <div class="public-shell">
        <Head title="Больше, чем игра" />
        <a class="skip-link" href="#main-content">Перейти к содержимому</a>
        <header class="public-header">
            <DogLiveBrand />
            <nav class="public-navigation" aria-label="Аккаунт">
                <Button v-if="page.props.auth.user" as-child
                    ><Link :href="dashboard()"
                        >Моя собака <ArrowRight /></Link></Button
                ><template v-else
                    ><Button variant="ghost" as-child
                        ><Link :href="login()">Войти</Link></Button
                    ><Button as-child
                        ><Link :href="register()">Регистрация</Link></Button
                    ></template
                >
            </nav>
        </header>
        <main id="main-content" class="welcome-content" tabindex="-1">
            <section class="welcome-hero">
                <div class="welcome-copy">
                    <span class="section-kicker"
                        ><PawPrint :size="16" /> Добро пожаловать в
                        DogLive</span
                    >
                    <h1>Большая дружба.<br /><em>Маленькие лапы.</em></h1>
                    <p>
                        Уютный мир о собаках, заботе и дружбе. Создай профиль и
                        стань частью истории, которая только начинается.
                    </p>
                    <Button size="lg" as-child
                        ><Link
                            :href="
                                page.props.auth.user ? dashboard() : register()
                            "
                            >{{
                                page.props.auth.user
                                    ? 'В свой мир'
                                    : 'Начать свою историю'
                            }}
                            <ArrowRight /></Link></Button
                    ><span class="welcome-note"
                        >Проект развивается. Первые игровые возможности
                        впереди.</span
                    >
                </div>
                <div class="welcome-image">
                    <img
                        src="/images/doglive-rey.png"
                        alt="Дружелюбная овчарка среди гор и цветов"
                        width="1536"
                        height="1024"
                        fetchpriority="high"
                    /><span
                        ><Heart :size="16" /> Счастье — в четырёх лапах</span
                    >
                </div>
            </section>
            <section class="welcome-features" aria-label="Мир DogLive">
                <article v-for="feature in features" :key="feature.title">
                    <span class="feature-icon"
                        ><component :is="feature.icon"
                    /></span>
                    <h2>{{ feature.title }}</h2>
                    <p>{{ feature.text }}</p>
                </article>
            </section>
        </main>
        <DogLiveFooter />
    </div>
</template>
