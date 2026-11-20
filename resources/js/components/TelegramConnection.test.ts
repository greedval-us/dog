import type { ErrorBag, Errors } from '@inertiajs/core';
import { App, router } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent, ShallowRef } from 'vue';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import TelegramConnection from './TelegramConnection.vue';
import type { TelegramConnectionData } from '@/types/telegram';

afterEach(() => {
    vi.restoreAllMocks();
});

function connection(
    overrides: Partial<TelegramConnectionData> = {},
): TelegramConnectionData {
    return {
        available: true,
        status: 'disconnected',
        account: null,
        link: null,
        botUrl: 'https://t.me/doglive_bot',
        serverNow: '2026-10-31T12:00:00Z',
        ...overrides,
    };
}

async function renderConnection(
    telegram: TelegramConnectionData,
    locale = 'en',
    errors: Errors = {},
) {
    const panel = defineComponent({
        setup: () => () => h(TelegramConnection, { telegram }),
    });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'settings/Profile',
                        url: '/settings/profile',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: { locale, errors: errors as Errors & ErrorBag },
                    },
                }),
        }),
    );
}

it('offers an explicit connection action without generating a link on render', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});

    const html = await renderConnection(connection());

    expect(html).toContain('Connect Telegram');
    expect(html).not.toContain('href="https://t.me/');
    expect(post).not.toHaveBeenCalled();
});

it.each([
    ['en', 'Telegram connection is temporarily unavailable.'],
    ['ru', 'Подключение Telegram временно недоступно.'],
])('explains unavailable connections in %s', async (locale, message) => {
    const html = await renderConnection(
        connection({ available: false, status: 'unavailable' }),
        locale,
    );

    expect(html).toContain(message);
    expect(html).not.toContain('Connect Telegram</button>');
    expect(html).not.toContain('href="https://t.me/');
});

it('opens an unexpired connection link outside Inertia using server time', async () => {
    vi.spyOn(Date, 'now').mockReturnValue(Date.parse('2099-01-01T00:00:00Z'));

    const html = await renderConnection(
        connection({
            status: 'pending',
            link: {
                url: 'https://t.me/doglive_bot?start=connect_example',
                expiresAt: '2026-10-31T12:05:00Z',
            },
        }),
    );

    expect(html).toContain(
        'href="https://t.me/doglive_bot?start=connect_example"',
    );
    expect(html).toContain('target="_blank"');
    expect(html).toContain('rel="noopener noreferrer"');
    expect(html).toContain('5:00');
    expect(html).toContain('Check connection');
});

it.each([
    ['pending', '2026-10-31T12:00:00Z'],
    ['pending', '2026-10-31T11:59:59Z'],
    ['expired', '2026-10-31T12:05:00Z'],
] as const)(
    'hides the %s connection link expiring at %s',
    async (status, expiresAt) => {
        const html = await renderConnection(
            connection({
                status,
                link: {
                    url: 'https://t.me/doglive_bot?start=connect_example',
                    expiresAt,
                },
            }),
        );

        expect(html).not.toContain('connect_example');
        expect(html).toContain(
            'This connection link has expired. Create a new one.',
        );
        expect(html).toContain('Create new link');
    },
);

it.each([null, 'dog_owner'])(
    'shows the connected account with optional username %s',
    async (username) => {
        const html = await renderConnection(
            connection({
                status: 'connected',
                account: {
                    firstName: '<Alex>',
                    username,
                    connectedAt: '2026-10-30T12:00:00Z',
                },
            }),
        );

        expect(html).toContain('Telegram connected');
        expect(html).toContain('&lt;Alex&gt;');
        expect(html).toContain('href="https://t.me/doglive_bot"');
        expect(html).toContain('Disconnect Telegram');
        const dialogOpener = html.match(
            /<button\b[^>]*aria-haspopup="dialog"[^>]*>/,
        )?.[0];
        expect(dialogOpener).toContain('aria-expanded="false"');
        expect(html.includes('@dog_owner')).toBe(username !== null);
        expect(html).not.toContain('Connect Telegram</button>');
    },
);

it('shows the server connection error', async () => {
    const html = await renderConnection(connection(), 'en', {
        telegram: 'Please try again later.',
    });

    expect(html).toContain('Please try again later.');
    expect(html).toContain('role="alert"');
});

it('checks only the connection without marking the player active', async () => {
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
    let checkConnection!: () => void;
    const setup = TelegramConnection.setup!;
    vi.spyOn(TelegramConnection, 'setup').mockImplementation(
        (props, context) => {
            const state = setup(props, context);
            checkConnection = (
                state as unknown as { checkConnection: () => void }
            ).checkConnection;
            return state;
        },
    );
    await renderConnection(connection({ status: 'pending' }));

    checkConnection();
    checkConnection();

    expect(reload).toHaveBeenCalledExactlyOnceWith(
        expect.objectContaining({
            only: ['telegram'],
            headers: { 'X-DogLive-Background': '1' },
        }),
    );
});

it('creates a connection link only after the player requests one', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    let createLink!: () => void;
    const setup = TelegramConnection.setup!;
    vi.spyOn(TelegramConnection, 'setup').mockImplementation(
        (props, context) => {
            const state = setup(props, context);
            createLink = (state as unknown as { createLink: () => void })
                .createLink;
            return state;
        },
    );
    await renderConnection(connection());

    createLink();

    expect(post).toHaveBeenCalledExactlyOnceWith(
        '/settings/telegram/link',
        {},
        expect.objectContaining({ preserveScroll: true }),
    );
});

it('requires confirmation before disconnecting Telegram', async () => {
    const remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
    let actions!: {
        disconnect: () => void;
        confirmUnlink: ShallowRef<boolean>;
    };
    const setup = TelegramConnection.setup!;
    vi.spyOn(TelegramConnection, 'setup').mockImplementation(
        (props, context) => {
            const state = setup(props, context);
            actions = state as unknown as typeof actions;
            return state;
        },
    );
    await renderConnection(
        connection({
            status: 'connected',
            account: {
                firstName: 'Alex',
                username: null,
                connectedAt: '2026-10-30T12:00:00Z',
            },
        }),
    );

    actions.disconnect();
    expect(remove).not.toHaveBeenCalled();
    actions.confirmUnlink.value = true;
    actions.disconnect();

    expect(remove).toHaveBeenCalledExactlyOnceWith(
        '/settings/telegram',
        expect.objectContaining({ preserveScroll: true }),
    );
});
