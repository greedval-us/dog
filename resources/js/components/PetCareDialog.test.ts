import { App, router } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import PetCareDialog from './PetCareDialog.vue';
import type { PlayerPet } from '@/types/pet';
import type { PetCare } from '@/types/pet-care';

afterEach(() => {
    vi.restoreAllMocks();
});

it('refreshes home options when a player starts care without marking the action as background', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    const pet: PlayerPet = {
        id: 7,
        name: 'Rey',
        breed: 'Shepherd',
        sex: 'male',
        coatColor: 'black',
        size: 'large',
        generation: 1,
        description: null,
        bornAt: '2026-10-02T12:00:00Z',
        age: { months: 18, years: 1, remainingMonths: 6, stage: 'adult' },
        isPurebred: true,
        isFavorite: false,
        hasPedigree: false,
        lifecycle: {
            status: 'active',
            archivedAt: null,
            canRetire: false,
            retirementEligibleAt: '2027-01-06T12:00:00Z',
            automaticRetirementAt: '2027-01-30T12:00:00Z',
        },
        traits: [],
        states: {
            health: 100,
            energy: 50,
            satiety: 100,
            hydration: 100,
            mood: 100,
            cleanliness: 100,
            bond: 100,
        },
        energy: { value: 50, maximum: 100 },
        stats: {
            endurance: { value: 20, potential: 100 },
            speed: { value: 20, potential: 100 },
            strength: { value: 20, potential: 100 },
            agility: { value: 20, potential: 100 },
            obedience: { value: 20, potential: 100 },
            intelligence: { value: 20, potential: 100 },
        },
    };
    const care: PetCare = {
        token: 'care-operation',
        modifierKeys: [],
        buffs: [],
        debuffs: [],
        recentIncidents: [],
        serverNow: '2026-10-20T12:00:00Z',
        blocked: false,
        busy: false,
        working: false,
        cooldowns: {},
        active: null,
        options: [
            {
                id: 'short_sleep',
                group: 'sleep',
                label: 'Short sleep',
                duration: 5,
                cooldown: 5,
                energy: 0,
                baseEnergy: 0,
                grantedEffects: [],
                statusRecovery: {},
                optional: [],
                requirements: [],
                uses: {},
                effects: { energy: 10 },
                reason: null,
                reasonCode: null,
                gainsByQuality: {},
            },
        ],
    };
    let start!: () => void;
    const setup = PetCareDialog.setup!;
    vi.spyOn(PetCareDialog, 'setup').mockImplementation((props, context) => {
        const state = setup(props, context);
        const handlers = state as unknown as {
            start: () => void;
            chooseVariant: (variant: string) => void;
        };
        handlers.chooseVariant('short_sleep');
        start = handlers.start;
        return state;
    });
    const panel = defineComponent({
        setup: () => () =>
            h(PetCareDialog, {
                pet,
                care,
                group: 'sleep',
                variant: 'short_sleep',
                now: Date.parse(care.serverNow),
                busyMessage: '',
                open: false,
            }),
    });
    const app = createSSRApp({
        render: () =>
            h(App, {
                initialComponent: panel as DefineComponent,
                initialPage: {
                    component: 'Dashboard',
                    url: '/dashboard',
                    version: 'test',
                    rescuedProps: [],
                    flash: {},
                    rememberedState: {},
                    props: {
                        locale: 'en',
                        errors: {},
                        auth: { user: { id: 1, coins: 100 } },
                    },
                },
            }),
    });
    await renderToString(app);
    start();

    expect(post).toHaveBeenCalledExactlyOnceWith(
        '/pets/7/care',
        { variant: 'short_sleep', token: 'care-operation', items: {} },
        expect.objectContaining({ only: ['pet', 'care', 'ownership'] }),
    );
    expect(post.mock.calls[0][2]?.headers).toBeUndefined();
});
