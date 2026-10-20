const monthsPerYear = 12;

export function formatAgeMonths(months: number, locale: string): string {
    const age = Math.max(0, Math.floor(months));
    const years = Math.floor(age / monthsPerYear);
    const remainder = age % monthsPerYear;
    const unit = (value: number, name: 'year' | 'month') =>
        new Intl.NumberFormat(locale, {
            style: 'unit',
            unit: name,
            unitDisplay: 'long',
        }).format(value);

    return [
        years > 0 ? unit(years, 'year') : '',
        remainder > 0 || years === 0 ? unit(remainder, 'month') : '',
    ]
        .filter(Boolean)
        .join(' ');
}
