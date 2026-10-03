import { Brush, CircleDot, Footprints, Moon, Soup } from '@lucide/vue';
import type { CareCategory } from '@/types/pet-care';

export const careActions = [
    { group: 'feed', label: 'Feed', icon: Soup, tone: 'sage' },
    { group: 'walk', label: 'Walk', icon: Footprints, tone: 'blue' },
    { group: 'play', label: 'Play', icon: CircleDot, tone: 'amber' },
    { group: 'groom', label: 'Groom', icon: Brush, tone: 'blue' },
    { group: 'sleep', label: 'Sleep', icon: Moon, tone: 'violet' },
] as const;

export const careCategoryLabels: Record<CareCategory, string> = {
    clothing: 'Clothing',
    sports: 'Sports equipment',
    food: 'Food',
    collars: 'Collar',
    leashes: 'Leash',
    toys: 'Toy',
    care: 'Care product',
};

export function careDuration(
    seconds: number,
    t: (key: string, replacements?: Record<string, string | number>) => string,
): string {
    if (seconds < 60) return t('{count} sec', { count: seconds });
    const minutes = Math.floor(seconds / 60);
    return seconds % 60 === 0
        ? t('{count} min', { count: minutes })
        : `${t('{count} min', { count: minutes })} ${t('{count} sec', { count: seconds % 60 })}`;
}
