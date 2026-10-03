import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import PetQuickActions from './PetQuickActions.vue';
import type { PlayerPet } from '@/types/pet';
import type { PetCare } from '@/types/pet-care';

async function renderActions(
    locale: string,
    state: 'active' | 'finished' | 'idle',
) {
    const pet: PlayerPet = {
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
    const care: PetCare = {
        token: 'next-action',
        modifierKeys: [],
        buffs: [],
        debuffs: [],
        recentIncidents: [],
        serverNow: '2026-10-03T10:00:00Z',
        blocked: false,
        busy: state !== 'idle',
        working: false,
        cooldowns: {},
        options: [],
        active:
            state === 'idle'
                ? null
                : {
                      token: 'current-action',
                      label: 'Training',
                      startedAt: '2026-10-03T09:59:00Z',
                      endsAt:
                          state === 'finished'
                              ? '2026-10-03T10:00:00Z'
                              : '2026-10-03T10:02:00Z',
                      effects: {},
                      statGains: null,
                  },
    };
    const panels = defineComponent({
        setup: () => () =>
            h('main', [
                h(PetQuickActions, { pet, care }),
                h(PetQuickActions, { pet, care, trainingOnly: true }),
            ]),
    });
    const app = createSSRApp({
        render: () =>
            h(App, {
                initialComponent: panels as DefineComponent,
                initialPage: {
                    component: 'PetQuickActions',
                    url: '/dashboard',
                    version: 'test',
                    rescuedProps: [],
                    flash: {},
                    rememberedState: {},
                    props: { locale, errors: {}, pet, care },
                },
            }),
    });

    return renderToString(app);
}

describe('shared activity timer', () => {
    it.each(['en', 'ru'])(
        'shows the timer once when care and development are visible in %s',
        async (locale) => {
            const html = await renderActions(locale, 'active');
            const [carePanel, trainingPanel] = html.split('id="pet-training"');

            expect(html.match(/<progress\b/g)).toHaveLength(1);
            expect(carePanel).toContain('2:00');
            expect(trainingPanel).not.toContain('pet-care-progress');
        },
    );

    it.each(['en', 'ru'])(
        'shows completion feedback once when the timer has ended in %s',
        async (locale) => {
            const html = await renderActions(locale, 'finished');

            expect(html.match(/<progress\b/g)).toHaveLength(1);
            expect(html.match(/role="status"/g)).toHaveLength(1);
            expect(html).toContain(
                locale === 'ru' ? 'Завершаем…' : 'Finishing...',
            );
        },
    );

    it.each(['en', 'ru'])(
        'does not show an activity timer when the dog is idle in %s',
        async (locale) => {
            const html = await renderActions(locale, 'idle');

            expect(html).not.toContain('<progress');
            expect(html).not.toContain('pet-care-progress');
        },
    );
});
