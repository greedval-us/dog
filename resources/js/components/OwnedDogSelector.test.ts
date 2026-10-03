import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import OwnedDogSelector from './OwnedDogSelector.vue';

type SelectorProps = InstanceType<typeof OwnedDogSelector>['$props'];

async function renderSelector(
    locale: string,
    attributes: Partial<SelectorProps> = {},
) {
    const panel = defineComponent({
        setup: () => () =>
            h(OwnedDogSelector, {
                id: 'owned-dog',
                dogs: [
                    { id: 1, name: 'Rey' },
                    { id: 2, name: 'Luna', busy: true },
                    { id: 3, name: 'Max', busy: true, retired: true },
                ],
                selectedPetId: 2,
                selectClass: 'vet-select',
                ...attributes,
            }),
    });

    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'OwnedDogSelector',
                        url: '/veterinarian',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: { locale, errors: {} },
                    },
                }),
        }),
    );
}

it.each(['en', 'ru'])(
    'labels the selected dog field and disables changes while pending in %s',
    async (locale) => {
        const html = await renderSelector(locale, { disabled: true });

        expect(html).toContain(
            locale === 'ru' ? 'Выбери собаку' : 'Choose a dog',
        );
        expect(html).toContain('for="owned-dog"');
        expect(html).toContain('id="owned-dog"');
        expect(html).toContain('class="vet-select"');
        expect(html).toMatch(/<select[^>]*value="2"[^>]*disabled/);
    },
);

it.each(['en', 'ru'])(
    'shows work availability with retirement taking priority over busy in %s',
    async (locale) => {
        const html = await renderSelector(locale, {
            showStatus: true,
            selectClass: 'dog-work-select',
        });

        expect(html).toContain(
            locale === 'ru' ? 'Luna · Занята' : 'Luna · Busy',
        );
        expect(html).toContain(
            locale === 'ru' ? 'Max · На пенсии' : 'Max · Retired',
        );
        expect(html).not.toContain(
            locale === 'ru' ? 'Max · Занята' : 'Max · Busy',
        );
    },
);

it('keeps plain dog names and allows selection when status labels are not requested', async () => {
    const html = await renderSelector('en');

    expect(html).toContain('Luna');
    expect(html).not.toContain('Luna · Busy');
    expect(html).not.toContain('Max · Retired');
    expect(html).not.toMatch(/<select[^>]*disabled/);
});

it('escapes dog names in selector options', async () => {
    const name = '<img src=x onerror=alert(1)>';

    const html = await renderSelector('en', {
        dogs: [{ id: 1, name }],
        selectedPetId: 1,
    });

    expect(html).not.toContain(name);
    expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
});
