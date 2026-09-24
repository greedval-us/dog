<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { update } from '@/routes/locale';

const page = usePage();
const { locale, t } = useI18n();
const form = useForm({ locale: locale.value });

watch(
    locale,
    (value) => {
        if (typeof document !== 'undefined') {
            document.documentElement.lang = value;
        }
    },
    { immediate: true },
);

function changeLanguage(value: 'ru' | 'en') {
    if (value === locale.value || form.processing) {
        return;
    }

    form.locale = value;
    form.post(update().url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => router.flushAll(),
    });
}
</script>

<template>
    <div class="language-control">
        <div
            class="language-switcher"
            role="group"
            :aria-label="t('Interface language')"
            :aria-busy="form.processing"
        >
            <Button
                v-for="(label, value) in page.props.locales"
                :key="value"
                type="button"
                variant="plain"
                :lang="value"
                :title="label"
                :aria-label="label"
                :aria-pressed="locale === value"
                :disabled="form.processing"
                @click="changeLanguage(value)"
                >{{ value.toUpperCase() }}</Button
            >
        </div>
        <p v-if="form.hasErrors" class="field-error" role="alert">
            {{ t('Could not change the language. Please try again.') }}
        </p>
    </div>
</template>
