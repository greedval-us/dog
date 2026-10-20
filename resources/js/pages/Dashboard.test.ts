import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import Dashboard from './Dashboard.vue';
import type { PetAppearance } from '@/types/appearance';
import type { PlayerPet } from '@/types/pet';
import type { PetCare } from '@/types/pet-care';
import type { PetSlot } from '@/types/pet-slot';
import type { PetOwnership } from '@/types/pet-market';

const polling = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/vue3', async () => {
    const inertia =
        await vi.importActual<typeof import('@inertiajs/vue3')>(
            '@inertiajs/vue3',
        );
    return {
        ...inertia,
        usePoll: (...args: Parameters<typeof inertia.usePoll>) => {
            polling(...args);
            return inertia.usePoll(...args);
        },
    };
});

function petFixture(): PlayerPet {
    return {
        id: 1,
        name: 'Rey',
        breed: 'Shepherd',
        sex: 'male',
        coatColor: 'black',
        size: 'large',
        generation: 1,
        description: null,
        bornAt: '2026-08-01',
        age: { months: 18, years: 1, remainingMonths: 6, stage: 'adult' },
        isPurebred: true,
        isFavorite: true,
        hasPedigree: false,
        lifecycle: {
            status: 'active',
            archivedAt: null,
            canRetire: false,
            retirementEligibleAt: '2026-11-01T00:00:00Z',
            automaticRetirementAt: '2027-02-01T00:00:00Z',
        },
        traits: [],
        states: {
            health: 100,
            energy: 100,
            satiety: 100,
            hydration: 100,
            mood: 100,
            cleanliness: 100,
            bond: 100,
        },
        energy: { value: 100, maximum: 100 },
        stats: {
            endurance: { value: 20, potential: 100 },
            speed: { value: 20, potential: 100 },
            strength: { value: 20, potential: 100 },
            agility: { value: 20, potential: 100 },
            obedience: { value: 20, potential: 100 },
            intelligence: { value: 20, potential: 100 },
        },
    };
}
function careFixture(): PetCare {
    return {
        token: 'next-action',
        modifierKeys: [],
        buffs: [],
        debuffs: [],
        recentIncidents: [],
        serverNow: '2026-10-03T10:00:00Z',
        blocked: false,
        busy: false,
        working: false,
        cooldowns: {},
        options: [],
        active: null,
    };
}
async function renderDashboard(
    locale: string,
    state: {
        pet?: PlayerPet;
        slots?: PetSlot[];
        care?: PetCare;
        ownership?: PetOwnership;
        appearance?: PetAppearance;
        rescued?: string[];
    },
) {
    const props = {
        pet: petFixture(),
        slots: [],
        canClaimStarterPet: false,
        ...state,
    };
    const panel = defineComponent({ setup: () => () => h(Dashboard, props) });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'Dashboard',
                        url: '/dashboard',
                        version: 'test',
                        rescuedProps: state.rescued ?? [],
                        flash: {},
                        rememberedState: {},
                        props: {
                            locale,
                            errors: {},
                            auth: { user: { coins: 0, gems: 0 } },
                            ...props,
                        },
                    },
                }),
        }),
    );
}

describe('independent dashboard data', () => {
    it.each(['en', 'ru'])(
        'keeps care, needs and details available while appearance is loading in %s',
        async (locale) => {
            const html = await renderDashboard(locale, { care: careFixture() });

            expect(html).toContain('id="pet-care"');
            expect(html).toContain('pet-condition');
            expect(html).toContain('pet-details');
            expect(html).toContain(locale === 'ru' ? 'Титулы' : 'Titles');
            expect(html).not.toContain('pet-career-title-list');
            expect(html).not.toContain('pet-career-result-list');
            expect(html).toContain('dashboard-skeleton-portrait');
            expect(html).toContain('Rey');
        },
    );

    it.each(['en', 'ru'])(
        'isolates an appearance failure to its retry panel in %s',
        async (locale) => {
            const html = await renderDashboard(locale, {
                care: careFixture(),
                rescued: ['appearance'],
            });

            expect(html).toContain('id="pet-care"');
            expect(html).toContain('pet-condition');
            expect(html).toContain('pet-details');
            expect(html).toContain('role="alert"');
            expect(html).toContain(locale === 'ru' ? 'Повторить' : 'Retry');
        },
    );

    it('keeps the profile and appearance visible when care fails', async () => {
        const html = await renderDashboard('en', {
            appearance: { assets: [], portraitId: 5, backgroundId: null },
            rescued: ['care'],
        });

        expect(html).toContain('Rey');
        expect(html).toContain('/assets/5/image');
        expect(html).toContain('pet-condition');
        expect(html).toContain('pet-details');
        expect(html).not.toContain('id="pet-care"');
        expect(html).toContain('role="alert"');
    });
});

it.each(['en', 'ru'])(
    'shows game age and keeps care available for a living retired companion in %s',
    async (locale) => {
        const pet = petFixture();
        pet.age = {
            months: 125,
            years: 10,
            remainingMonths: 5,
            stage: 'retired',
        };
        pet.lifecycle.status = 'retired';
        const html = await renderDashboard(locale, {
            pet,
            care: careFixture(),
        });

        expect(html).toContain(
            locale === 'ru' ? '10 лет 5 месяцев' : '10 years 5 months',
        );
        expect(html).toContain(
            locale === 'ru'
                ? 'Уход продолжается, и собака занимает ячейку.'
                : 'Care continues and your dog keeps a slot.',
        );
        expect(html).toContain('id="pet-care"');
        expect(html).not.toContain('Send to retirement');
    },
);

it('keeps every restored companion accessible when living dogs exceed the old slot limit', async () => {
    const slots: PetSlot[] = Array.from({ length: 12 }, (_, index) => ({
        number: index + 1,
        unlocked: true,
        purchasable: false,
        coins: 0,
        gems: 0,
        pet: { id: index + 1, name: `Companion ${index + 1}` },
    }));
    const html = await renderDashboard('en', { slots, care: careFixture() });

    expect(html).toContain('Dogs in your care: 12');
    expect(html).toContain('Companion 12');
    expect(html).toContain('href="/dashboard?pet=12"');
    expect(html).not.toContain('12 of 9 unlocked');
});

it.each(['en', 'ru'])(
    'lets an older active dog keep its career without an automatic retirement date in %s',
    async (locale) => {
        const pet = petFixture();
        pet.age = {
            months: 125,
            years: 10,
            remainingMonths: 5,
            stage: 'senior',
        };
        pet.lifecycle.canRetire = true;
        pet.lifecycle.automaticRetirementAt = null;
        const html = await renderDashboard(locale, {
            pet,
            care: careFixture(),
        });

        expect(html).toContain(
            locale === 'ru' ? 'Завершить карьеру' : 'End career',
        );
        expect(html).not.toContain('Automatic retirement:');
        expect(html).not.toContain('Автоматический выход на пенсию:');
        expect(html).not.toContain('Sports career has ended.');
        expect(html).not.toContain('Спортивная карьера завершена.');
    },
);

it('enables home options after polling refreshes a dog that finished care', async () => {
    polling.mockClear();
    const busyReason = 'Complete care before moving this dog.';
    const state = {
        care: { ...careFixture(), busy: true },
        ownership: { listing: null, reason: busyReason } as PetOwnership,
    };
    const initial = await renderDashboard('en', state);
    expect(initial).toContain(busyReason);

    const options = polling.mock.calls.find(
        ([interval]) => interval === 60_000,
    )?.[1];
    expect(options.headers).toEqual({ 'X-DogLive-Background': '1' });
    const refreshed = { ...state };
    if (options.only.includes('care'))
        refreshed.care = { ...careFixture(), busy: false };
    if (options.only.includes('ownership'))
        refreshed.ownership = { listing: null, reason: null };
    const html = await renderDashboard('en', refreshed);

    expect(html).not.toContain(busyReason);
    const saleButton = html
        .match(/<button\b[^>]*>[\s\S]*?<\/button>/g)
        ?.find((button) => button.includes('List for sale'));
    expect(saleButton).toBeDefined();
    expect(saleButton).not.toContain(' disabled');
});
