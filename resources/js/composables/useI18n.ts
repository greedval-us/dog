import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import en from '@/locales/en.json';
import ru from '@/locales/ru.json';

const dictionaries: Record<string, Record<string, string>> = { en, ru };

export function useI18n() {
    const page = usePage();
    const locale = computed(() => page.props.locale);

    const t = (
        key: string,
        replacements: Record<string, string | number> = {},
    ): string => {
        const message =
            dictionaries[locale.value]?.[key] ?? dictionaries.en[key] ?? key;

        return message.replace(/\{(\w+)\}/g, (token, name: string) =>
            Object.hasOwn(replacements, name)
                ? String(replacements[name])
                : token,
        );
    };

    return { locale, t };
}
