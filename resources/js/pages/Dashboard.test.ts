import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import Dashboard from './Dashboard.vue';
import type { PetAppearance } from '@/types/appearance';
import type { PlayerPet } from '@/types/pet';
import type { PetCare } from '@/types/pet-care';

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
        isPurebred: true,
        isFavorite: true,
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
    state: { care?: PetCare; appearance?: PetAppearance; rescued?: string[] },
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
