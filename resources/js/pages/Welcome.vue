<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Heart, PawPrint, Sprout } from '@lucide/vue';
import DogLiveBrand from '@/components/DogLiveBrand.vue';
import DogLiveFooter from '@/components/DogLiveFooter.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import SystemNotifications from '@/components/SystemNotifications.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
const page = usePage();
const features = [
    {
        icon: Heart,
        title: 'Everyday care',
        text: 'Little habits that build a great friendship.',
    },
    {
        icon: PawPrint,
        title: 'Every dog has a personality',
        text: 'A world of dogs where getting to know your friend is an adventure.',
    },
    {
        icon: Sprout,
        title: 'Grow together',
        text: 'New experiences and happy moments together.',
    },
];
const { t } = useI18n();
</script>

<template>
    <div class="public-shell">
        <Head :title="t('More than a game')" />
        <a class="skip-link" href="#main-content">{{ t('Skip to content') }}</a>
        <header class="public-header">
            <DogLiveBrand />
            <nav class="public-navigation" :aria-label="t('Account')">
                <LanguageSwitcher />
                <SystemNotifications v-if="page.props.auth.user" />
                <Button v-if="page.props.auth.user" as-child
                    ><Link :href="dashboard()"
                        >{{ t('My dog') }} <ArrowRight /></Link></Button
                ><template v-else
                    ><Button variant="ghost" as-child
                        ><Link :href="login()">{{ t('Log in') }}</Link></Button
                    ><Button as-child
                        ><Link :href="register()">{{
                            t('Registration')
                        }}</Link></Button
                    ></template
                >
            </nav>
        </header>
        <main id="main-content" class="welcome-content" tabindex="-1">
            <section class="welcome-hero">
                <div class="welcome-copy">
                    <span class="section-kicker"
                        ><PawPrint :size="16" />
                        {{ t('Welcome to DogLive') }}</span
                    >
                    <h1>
                        {{ t('Big friendship.') }}<br /><em>{{
                            t('Little paws.')
                        }}</em>
                    </h1>
                    <p>
                        {{
                            t(
                                'A cozy world of dogs, care and friendship. Create a profile and join a story that is just beginning.',
                            )
                        }}
                    </p>
                    <Button size="lg" as-child
                        ><Link
                            :href="
                                page.props.auth.user ? dashboard() : register()
                            "
                            >{{
                                page.props.auth.user
                                    ? t('Enter your world')
                                    : t('Start your story')
                            }}
                            <ArrowRight /></Link></Button
                    ><span class="welcome-note">{{
                        t(
                            'DogLive is growing. The first gameplay features are on their way.',
                        )
                    }}</span>
                </div>
                <div class="welcome-image">
                    <img
                        src="/images/doglive-rey.png"
                        :alt="
                            t(
                                'A friendly shepherd dog among mountains and flowers',
                            )
                        "
                        width="1536"
                        height="1024"
                        fetchpriority="high"
                    /><span
                        ><Heart :size="16" />
                        {{ t('Happiness has four paws') }}</span
                    >
                </div>
            </section>
            <section
                class="welcome-features"
                :aria-label="t('The world of DogLive')"
            >
                <article v-for="feature in features" :key="feature.title">
                    <span class="feature-icon"
                        ><component :is="feature.icon"
                    /></span>
                    <h2>{{ t(feature.title) }}</h2>
                    <p>{{ t(feature.text) }}</p>
                </article>
            </section>
        </main>
        <DogLiveFooter />
    </div>
</template>
