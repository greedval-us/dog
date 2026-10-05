<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import DogLiveBrand from '@/components/DogLiveBrand.vue';
import DogLiveFooter from '@/components/DogLiveFooter.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { LogIn, UserPlus } from '@lucide/vue';
import { login, register } from '@/routes';

defineProps<{ title?: string; description?: string }>();
const { t } = useI18n();
const page = usePage();
const showAccountTabs = computed(() =>
    ['auth/Login', 'auth/Register'].includes(page.component),
);
</script>

<template>
    <div class="public-shell">
        <a class="skip-link" href="#main-content">{{ t('Skip to content') }}</a>
        <header class="public-header">
            <DogLiveBrand /><LanguageSwitcher />
        </header>
        <main id="main-content" class="auth-layout" tabindex="-1">
            <aside class="auth-story">
                <div class="auth-story-copy">
                    <span class="section-kicker">{{
                        t('Your little world')
                    }}</span>
                    <h2>
                        {{ t('Friendship') }}<br />{{ t('that grows') }}<br />{{
                            t('every day.')
                        }}
                    </h2>
                    <p>{{ t('Your DogLive story starts here.') }}</p>
                </div>
                <img
                    src="/images/doglive-rey.webp"
                    :alt="t('A shepherd dog in a sunny mountain meadow')"
                    width="1536"
                    height="1024"
                    decoding="async"
                />
                <span class="auth-story-caption">{{
                    t('More care. More happy moments.')
                }}</span>
            </aside>
            <section class="auth-panel">
                <nav
                    v-if="showAccountTabs"
                    class="auth-tabs"
                    :aria-label="t('Account')"
                >
                    <Link
                        :href="login()"
                        :aria-current="
                            page.component === 'auth/Login' ? 'page' : undefined
                        "
                        ><LogIn :size="17" aria-hidden="true" />{{
                            t('Log in')
                        }}</Link
                    >
                    <Link
                        :href="register()"
                        :aria-current="
                            page.component === 'auth/Register'
                                ? 'page'
                                : undefined
                        "
                        ><UserPlus :size="17" aria-hidden="true" />{{
                            t('Registration')
                        }}</Link
                    >
                </nav>
                <header class="auth-heading">
                    <h1>{{ t(title ?? '') }}</h1>
                    <p>{{ t(description ?? '') }}</p>
                </header>
                <slot />
            </section>
        </main>
        <DogLiveFooter />
    </div>
</template>
