<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import DogLiveBrand from '@/components/DogLiveBrand.vue';
import DogLiveFooter from '@/components/DogLiveFooter.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

defineProps<{ title?: string; description?: string }>();
const { t } = useI18n();
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
                    src="/images/doglive-rey.png"
                    :alt="t('A shepherd dog in a sunny mountain meadow')"
                    width="1536"
                    height="1024"
                />
                <span class="auth-story-caption">{{
                    t('More care. More happy moments.')
                }}</span>
            </aside>
            <section class="auth-panel">
                <header class="auth-heading">
                    <span class="section-kicker">{{
                        t('Welcome to DogLive')
                    }}</span>
                    <h1>{{ t(title ?? '') }}</h1>
                    <p>{{ t(description ?? '') }}</p>
                </header>
                <slot />
            </section>
        </main>
        <DogLiveFooter />
    </div>
</template>
