import { useI18n } from '@/composables/useI18n';

export function useBreedingMessages() {
    const { t, locale } = useI18n();
    const reason = (value: string | null): string | null => {
        if (!value) return null;
        const key = value.startsWith('breeding.errors.')
            ? value
            : 'breeding.errors.' + value;
        const translated = t(key);
        return translated === key
            ? t('Breeding is unavailable right now.')
            : translated;
    };
    const date = (value: string): string =>
        new Intl.DateTimeFormat(locale.value, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    return { reason, date };
}
