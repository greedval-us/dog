import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import Veterinarian from './Veterinarian.vue';
import DogLiveNavigation from '@/components/DogLiveNavigation.vue';
import type { VeterinaryClinic } from '@/types/veterinarian';

function clinicFixture(): VeterinaryClinic {
    return {
        token: '00000000-0000-4000-8000-000000000001',
        selectedPetId: 1,
        dogs: [{ id: 1, name: 'Rey' }],
        health: 70,
        reason: null,
        checkupDays: 7,
        checkupHealth: 10,
        vaccinationDays: 30,
        vaccinationBonus: 10,
        services: [
            {
                code: 'treatment',
                price: 120,
                reason: null,
                lastVisitAt: null,
                availableAt: null,
            },
            {
                code: 'checkup',
                price: 60,
                reason: null,
                lastVisitAt: null,
                availableAt: null,
            },
            {
                code: 'vaccination',
                price: 100,
                reason: null,
                lastVisitAt: null,
                availableAt: null,
            },
        ],
        diseases: [],
        history: [],
    };
}

async function renderClinic(locale: string, clinic: VeterinaryClinic) {
    const panel = defineComponent({
        setup: () => () =>
            h('div', [h(DogLiveNavigation), h(Veterinarian, { clinic })]),
    });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'Veterinarian',
                        url: '/veterinarian',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: {
                            locale,
                            errors: {},
                            auth: { user: { username: 'preview' } },
                        },
                    },
                }),
        }),
    );
}

describe('veterinarian services', () => {
    it('escapes dog names and diagnosis labels', async () => {
        const clinic = clinicFixture();
        const label = '<img src=x onerror=alert(1)>';
        clinic.dogs[0].name = label;
        clinic.diseases = [
            { id: 8, name: label, startedAt: '2026-10-03T10:00:00Z' },
        ];
        const html = await renderClinic('en', clinic);
        expect(html).not.toContain(label);
        expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
    });

    it.each(['ru', 'en'])(
        'shows the menu entry prices and healthy state in %s',
        async (locale) => {
            const html = await renderClinic(locale, clinicFixture());
            expect(html).toContain('href="/veterinarian"');
            expect(html).toContain(
                locale === 'ru'
                    ? 'Болезней, требующих лечения, нет.'
                    : 'No diseases requiring treatment.',
            );
            expect(html).toContain(
                locale === 'ru' ? 'Оплатить 60 монет' : 'Pay 60 coins',
            );
            expect(html).toContain(
                locale === 'ru' ? 'Оплатить 100 монет' : 'Pay 100 coins',
            );
            expect(html).toContain(
                locale === 'ru' ? 'Ветеринар' : 'Veterinarian',
            );
        },
    );

    it.each(['ru', 'en'])(
        'offers treatment for a diagnosis and shows paid history in %s',
        async (locale) => {
            const clinic = clinicFixture();
            clinic.diseases = [
                { id: 8, name: 'Illness', startedAt: '2026-10-03T10:00:00Z' },
            ];
            clinic.history = [
                {
                    id: 3,
                    service: 'checkup',
                    price: 60,
                    diseaseName: null,
                    performedAt: '2026-10-03T10:00:00Z',
                },
            ];
            const html = await renderClinic(locale, clinic);
            expect(html).toContain(
                locale === 'ru'
                    ? 'Вылечить за 120 монет'
                    : 'Treat for 120 coins',
            );
            expect(html).toContain(
                locale === 'ru' ? 'Последние посещения' : 'Recent visits',
            );
            expect(html).toContain('Illness');
        },
    );

    it('disables active preventive services and displays their availability dates', async () => {
        const clinic = clinicFixture();
        for (const service of clinic.services.slice(1)) {
            service.reason =
                service.code === 'checkup'
                    ? 'Your dog has already had its weekly checkup.'
                    : 'Your dog’s vaccination is still active.';
            service.lastVisitAt = '2026-10-03T10:00:00Z';
            service.availableAt = '2026-11-02T10:00:00Z';
        }
        const html = await renderClinic('en', clinic);
        expect(html.match(/<button[^>]*disabled/g)).toHaveLength(2);
        expect(html).toContain('Next checkup from');
        expect(html).toContain('Vaccination active until');
    });

    it('disables unaffordable treatment and explains the missing coins', async () => {
        const clinic = clinicFixture();
        clinic.diseases = [
            { id: 8, name: 'Illness', startedAt: '2026-10-03T10:00:00Z' },
        ];
        for (const service of clinic.services)
            service.reason =
                'You do not have enough coins for this veterinary service.';
        const html = await renderClinic('ru', clinic);
        expect(html.match(/<button[^>]*disabled/g)).toHaveLength(3);
        expect(html).toContain('Не хватает монет');
    });

    it('offers the kennel without showing paid services when there is no dog', async () => {
        const clinic = clinicFixture();
        clinic.selectedPetId = null;
        clinic.dogs = [];
        clinic.health = null;
        const html = await renderClinic('ru', clinic);
        expect(html).toContain('Для посещения ветеринара нужна собака.');
        expect(html).toContain('В питомник');
        expect(html).not.toContain('Оплатить 60 монет');
        expect(html).not.toContain('id="vet-dog"');
    });
});
