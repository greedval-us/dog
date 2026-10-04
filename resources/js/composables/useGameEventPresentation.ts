import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { CompetitionGear } from '@/types/competition-gear';
import type { EventDiscipline } from '@/types/game-event';

export function useGameEventPresentation() {
    const { locale, t, number } = useI18n();
    const dateFormatter = computed(
        () =>
            new Intl.DateTimeFormat(locale.value, {
                day: 'numeric',
                month: 'long',
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'Europe/Moscow',
            }),
    );
    const dayFormatter = computed(
        () =>
            new Intl.DateTimeFormat(locale.value, {
                day: 'numeric',
                month: 'long',
                weekday: 'long',
                timeZone: 'Europe/Moscow',
            }),
    );
    const decimalFormatter = computed(
        () => new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }),
    );

    return {
        date: (value: string) => dateFormatter.value.format(new Date(value)),
        calendarDay: (value: string) =>
            dayFormatter.value.format(new Date(value)),
        decimal: (value: number) => decimalFormatter.value.format(value),
        stageLabel: (key: string) => t(`events.stage.${key}`),
        optionLabel: (
            discipline: EventDiscipline,
            key: string,
            choice: string,
        ) => t(`events.option.${discipline}.${key}.${choice}`),
        phaseLabel: (phase: string) =>
            t(
                phase === 'preparation'
                    ? 'Preparation only'
                    : 'During the performance',
            ),
        gearDescription: (gear: CompetitionGear) =>
            typeof gear.description === 'string'
                ? gear.description
                : (gear.description?.[locale.value] ?? gear.description?.en),
        modifier: (value: number) =>
            `${value > 0 ? '+' : ''}${number(value * 100)}%`,
        modifierEffect: (key: string, value: number) => {
            const signed = (value: number) =>
                `${value > 0 ? '+' : ''}${decimalFormatter.value.format(value)}`;
            if (key === 'precision')
                return t('Base error risk: {value} pp', {
                    value: signed(-value * 100),
                });
            if (key === 'focus')
                return t('Focus: {value} points', {
                    value: signed(value * 100),
                });
            return `${t(`events.modifier.${key}`)} ${signed(value * 100)}%`;
        },
    };
}
