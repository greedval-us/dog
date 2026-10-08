<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowRight, Heart, PawPrint, Sprout } from '@lucide/vue';
import DogLiveBrand from '@/components/DogLiveBrand.vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
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
const firstSteps = [
    {
        title: 'Create your account',
        text: 'Make yourself at home in DogLive.',
    },
    {
        title: 'Choose your first dog',
        text: 'Visit the kennel, choose a breed and take your first dog home for free.',
    },
    {
        title: 'Find your everyday rhythm',
        text: 'Care, play and train. Get to know your dog a little better every day.',
    },
];
const { t } = useI18n();
</script>

<template>
    <div class="public-shell welcome-shell">
        <Head :title="t('More than a game')" />
        <a class="skip-link" href="#main-content">{{ t('Skip to content') }}</a>
        <header class="public-header">
            <DogLiveBrand />
            <nav
                class="welcome-navigation"
                :aria-label="t('The world of DogLive')"
            >
                <a href="#doglive-world">{{ t('Explore DogLive') }}</a>
                <a href="#first-steps">{{ t('First steps') }}</a>
            </nav>
            <LanguageSwitcher />
            <nav class="public-navigation" :aria-label="t('Account')">
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
                        ><PawPrint :size="16" aria-hidden="true" />
                        {{ t('Welcome to DogLive') }}</span
                    >
                    <h1>
                        <span>{{ t('Big friendship.') }}</span>
                        <span>{{ t('Little paws.') }}</span>
                    </h1>
                    <p>
                        {{
                            t(
                                'Care for your dog, learn new skills and discover the world together.',
                            )
                        }}
                    </p>
                    <div class="welcome-actions">
                        <Button size="lg" as-child
                            ><Link
                                :href="
                                    page.props.auth.user
                                        ? dashboard()
                                        : register()
                                "
                                >{{
                                    page.props.auth.user
                                        ? t('Enter your world')
                                        : t('Start your story')
                                }}
                                <ArrowRight aria-hidden="true" /></Link
                        ></Button>
                        <a class="welcome-explore" href="#doglive-world">
                            {{ t('Explore DogLive') }}
                            <ArrowDown :size="17" aria-hidden="true" />
                        </a>
                    </div>
                    <span class="welcome-note"
                        ><PawPrint :size="15" aria-hidden="true" />{{
                            page.props.auth.user
                                ? t('Together every day')
                                : t('Your first dog is waiting for you.')
                        }}</span
                    >
                </div>
                <figure class="welcome-image">
                    <img
                        src="/images/doglive-rey.webp"
                        :alt="
                            t(
                                'A friendly shepherd dog among mountains and flowers',
                            )
                        "
                        width="1536"
                        height="1024"
                        decoding="async"
                        fetchpriority="high"
                    />
                    <figcaption>
                        <span class="welcome-caption-icon"
                            ><Heart :size="22" aria-hidden="true"
                        /></span>
                        <div>
                            <strong>{{ t('A friend for every day') }}</strong>
                            <span>{{ t('Happiness has four paws') }}</span>
                        </div>
                    </figcaption>
                </figure>
            </section>
            <section
                id="doglive-world"
                class="welcome-world"
                aria-labelledby="world-heading"
            >
                <header class="welcome-section-heading">
                    <h2 id="world-heading">
                        {{ t('A little world. A lifelong friendship.') }}
                    </h2>
                    <p>{{ t('Every day brings something to do together.') }}</p>
                </header>
                <div class="welcome-features">
                    <article v-for="feature in features" :key="feature.title">
                        <span class="feature-icon"
                            ><component :is="feature.icon" aria-hidden="true"
                        /></span>
                        <div>
                            <h3>{{ t(feature.title) }}</h3>
                            <p>{{ t(feature.text) }}</p>
                        </div>
                    </article>
                </div>
            </section>
            <section
                id="first-steps"
                class="welcome-start"
                aria-labelledby="first-steps-heading"
            >
                <div class="welcome-start-heading">
                    <span class="welcome-start-icon"
                        ><PawPrint :size="26" aria-hidden="true"
                    /></span>
                    <header class="welcome-section-heading">
                        <h2 id="first-steps-heading">
                            {{ t('Your story starts with a friend') }}
                        </h2>
                        <p>{{ t('Just a few steps to your first dog.') }}</p>
                    </header>
                    <Button as-child>
                        <Link
                            :href="
                                page.props.auth.user ? dashboard() : register()
                            "
                        >
                            {{
                                page.props.auth.user
                                    ? t('Enter your world')
                                    : t('Start your story')
                            }}
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
                <ol class="welcome-steps">
                    <li v-for="(step, index) in firstSteps" :key="step.title">
                        <span class="welcome-step-number" aria-hidden="true">{{
                            index + 1
                        }}</span>
                        <div>
                            <h3>{{ t(step.title) }}</h3>
                            <p>{{ t(step.text) }}</p>
                        </div>
                    </li>
                </ol>
            </section>
        </main>
        <DogLiveFooter
            ><template #controls><AppearanceTabs compact /></template
        ></DogLiveFooter>
    </div>
</template>
