import { spawn } from 'node:child_process';
import { randomBytes, randomUUID } from 'node:crypto';
import { once } from 'node:events';
import { createWriteStream } from 'node:fs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import http from 'node:http';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { performance, monitorEventLoopDelay } from 'node:perf_hooks';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';
import { setTimeout as sleep } from 'node:timers/promises';

const { values } = parseArgs({ options: {
    php: { type: 'string' }, nginx: { type: 'string' },
    users: { type: 'string', default: '1000' }, workers: { type: 'string', default: '8' },
    stages: { type: 'string', default: '100,250,500,1000' },
    seconds: { type: 'string', default: '30' }, hold: { type: 'string', default: '165' },
    period: { type: 'string', default: '15' }, help: { type: 'boolean', default: false },
} });
if (values.help) {
    console.log('Local isolated HTTP load test. No external target is accepted.\n'
        + 'node tests/Fixtures/load-test.mjs --php <php.exe> --nginx <nginx.exe>\n'
        + 'Optional: --users 1000 --workers 8 --stages 100,250,500,1000 --seconds 30 --hold 165 --period 15\n'
        + 'Period is seconds between scheduled player interactions; slow interactions skip missed slots.\n'
        + 'Sessions are prepared before timing. Assets, browser rendering and login bursts are excluded.\n'
        + 'Each run retains its private database, credentials, logs and report under ignored storage/framework/testing.');
    process.exit(0);
}
const options = Object.fromEntries(['users', 'workers', 'seconds', 'hold', 'period'].map(key => [key, Number(values[key])]));
const stages = values.stages.split(',').map(Number);
if (!values.php || !values.nginx || !path.isAbsolute(values.php) || !path.isAbsolute(values.nginx)
    || Object.values(options).some(value => !Number.isInteger(value) || value < 1)
    || options.users > 1000 || options.workers > 16 || options.hold > 600 || options.seconds > 600
    || stages.some(value => !Number.isInteger(value) || value < 1 || value > options.users)) {
    throw new Error('Supply absolute PHP/Nginx executable paths and valid bounded load options. See --help.');
}
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const run = path.join(root, 'storage/framework/testing', `loadtest-${new Date().toISOString().replaceAll(/[:.]/g, '-')}-${randomUUID().slice(0, 8)}`);
const slash = value => value.replaceAll('\\', '/');
await mkdir(run);
for (const directory of ['logs', 'framework/cache/data', 'framework/sessions', 'framework/views', 'nginx-temp', 'temp']) {
    await mkdir(path.join(run, directory), { recursive: true });
}
const databaseSchema = `loadtest_${randomBytes(8).toString('hex')}`;
await writeFile(path.join(run, 'database-schema'), databaseSchema);

async function freePort() {
    const server = net.createServer();
    server.listen(0, '127.0.0.1');
    await once(server, 'listening');
    const port = server.address().port;
    await new Promise(resolve => server.close(resolve));
    return port;
}
const port = await freePort();
const backendPorts = [];
while (backendPorts.length < options.workers) {
    const candidate = await freePort();
    if (candidate !== port && !backendPorts.includes(candidate)) backendPorts.push(candidate);
}
const origin = `http://127.0.0.1:${port}`;
const cachePath = filename => slash(path.relative(root, path.join(run, filename)));
const env = { ...process.env,
    APP_ENV: 'loadtest', APP_DEBUG: 'false', APP_URL: origin,
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`, APP_PREVIOUS_KEYS: '',
    DB_CONNECTION: 'pgsql', DB_DATABASE: 'dog_loadtest', DB_SCHEMA: databaseSchema, DB_URL: '',
    REDIS_PREFIX: `${databaseSchema}-database-`,
    LARAVEL_STORAGE_PATH: slash(run), DOG_LOAD_RUN: run,
    SESSION_DRIVER: 'redis', SESSION_CONNECTION: 'default', SESSION_COOKIE: 'dogload_session',
    SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', SESSION_ENCRYPT: 'false',
    CACHE_STORE: 'redis', CACHE_PREFIX: `${databaseSchema}-cache-`, QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
    LOG_CHANNEL: 'single', LOG_LEVEL: 'error', BCRYPT_ROUNDS: '4',
    APP_CONFIG_CACHE: cachePath('config.php'),
    APP_ROUTES_CACHE: cachePath('routes.php'),
    APP_EVENTS_CACHE: cachePath('events.php'),
    APP_PACKAGES_CACHE: cachePath('packages.php'),
    APP_SERVICES_CACHE: cachePath('services.php'),
    VIEW_COMPILED_PATH: slash(path.join(run, 'framework/views')),
    PHP_FCGI_MAX_REQUESTS: '0', PHP_FCGI_CHILDREN: '0',
};
const children = [];
const agent = new http.Agent({ keepAlive: true, maxSockets: 2048, maxFreeSockets: 128 });
let stopping = false;
async function cleanup() {
    if (stopping) return;
    stopping = true;
    agent.destroy();
    for (const child of children.toReversed()) {
        if (child.exitCode === null && child.signalCode === null) {
            child.kill();
            await Promise.race([once(child, 'exit'), sleep(3000)]);
        }
    }
}
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.once(signal, () => { void cleanup().then(() => process.exit(130)); });
}
function launch(executable, args, name) {
    const log = createWriteStream(path.join(run, `${name}.log`));
    const child = spawn(executable, args, { cwd: root, env, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    children.push(child);
    child.logFinished = once(log, 'finish');
    child.stdout.pipe(log, { end: false });
    child.stderr.pipe(log, { end: false });
    child.once('close', () => log.end());
    child.once('error', error => log.end(String(error)));
    return child;
}
async function command(executable, args, name) {
    const child = launch(executable, args, name);
    const [code] = await once(child, 'close');
    await child.logFinished;
    if (code !== 0) throw new Error(`${name} failed (${code}); inspect ${path.join(run, `${name}.log`)}`);
}
async function waitPort(target, child) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (child.exitCode !== null) throw new Error(`Server exited with ${child.exitCode}`);
        const ready = await new Promise(resolve => {
            const socket = net.connect({ port: target, host: '127.0.0.1' });
            socket.setTimeout(100);
            socket.once('connect', () => { socket.destroy(); resolve(true); });
            socket.once('error', () => { socket.destroy(); resolve(false); });
            socket.once('timeout', () => { socket.destroy(); resolve(false); });
        });
        if (ready) return;
        await sleep(100);
    }
    throw new Error(`Server on port ${target} did not start`);
}
const measurements = [];
let fixture;
let inFlight = 0;
let maxInFlight = 0;
async function request(player, label, endpoint, { method = 'GET', body, partial, component, record = true } = {}) {
    const started = performance.now();
    const payload = body ? JSON.stringify(body) : undefined;
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest',
        'User-Agent': 'DogLive local load test', Cookie: player.cookie, 'X-Inertia': 'true',
        'X-Inertia-Version': fixture.version ?? '' };
    if (partial) Object.assign(headers, { 'X-Inertia-Partial-Component': 'Dashboard', 'X-Inertia-Partial-Data': partial });
    if (payload) Object.assign(headers, { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload),
        'X-CSRF-TOKEN': player.csrf, Origin: origin, Referer: `${origin}/dashboard` });
    inFlight++;
    maxInFlight = Math.max(maxInFlight, inFlight);
    const result = await new Promise(resolve => {
        let finished = false;
        const finish = value => { if (!finished) { finished = true; clearTimeout(deadline); resolve(value); } };
        const outgoing = http.request(`${origin}${endpoint}`, { method, headers, agent }, incoming => {
            const chunks = [];
            incoming.on('data', chunk => chunks.push(chunk));
            incoming.on('error', error => finish({ status: 0, error: error.code ?? error.message }));
            incoming.on('end', () => {
                const text = Buffer.concat(chunks).toString();
                let data;
                try { data = JSON.parse(text); } catch { /* Redirects are HTML. */ }
                const status = incoming.statusCode;
                let error = null;
                if (incoming.headers['x-dog-load-test'] !== 'isolated') error = 'missing-isolation-marker';
                else if (method === 'POST') {
                    if (![302, 303].includes(status) || !incoming.headers.location?.includes('/dashboard')) {
                        error = `HTTP-${status}: ${data?.message ?? 'unexpected mutation response'}`;
                    }
                } else if (status !== 200) error = `HTTP-${status}`;
                else if (!data) error = 'invalid-json';
                else if (component && data.component !== component) error = 'wrong-component';
                else if (!partial && component && data.props?.auth?.user?.id !== player.id) error = 'wrong-authenticated-user';
                else if (partial?.includes('care') && !Array.isArray(data.props?.care?.options)) error = 'care-deferred-failure';
                else if (partial === 'appearance' && !data.props?.appearance) error = 'appearance-deferred-failure';
                for (const cookie of incoming.headers['set-cookie'] ?? []) {
                    if (cookie.startsWith('dogload_session=')) player.cookie = cookie.split(';')[0];
                }
                finish({ status, error, data, bytes: Buffer.byteLength(text),
                    queries: Number(incoming.headers['x-load-queries'] ?? 0),
                    sqlMs: Number(incoming.headers['x-load-sql-ms'] ?? 0),
                    appMs: Number(incoming.headers['x-load-app-ms'] ?? 0) });
            });
        });
        const deadline = setTimeout(() => outgoing.destroy(new Error('request-deadline-10s')), 10000);
        outgoing.on('error', error => finish({ status: 0, error: error.code ?? error.message }));
        outgoing.end(payload);
    });
    inFlight--;
    const measurement = { label, milliseconds: performance.now() - started, ...result };
    delete measurement.data;
    if (record) measurements.push(measurement);
    return result;
}
async function dashboard(player, record = true) {
    const response = await request(player, 'dashboard', '/dashboard', { component: 'Dashboard', record });
    if (response.error) return [response];
    return [response, ...await Promise.all([
        request(player, 'dashboard-care', '/dashboard', { partial: 'care', component: 'Dashboard', record }),
        request(player, 'dashboard-appearance', '/dashboard', { partial: 'appearance', component: 'Dashboard', record }),
    ])];
}
function quantile(sorted, percentile) {
    return sorted.length ? Math.round(sorted[Math.min(sorted.length - 1, Math.ceil(sorted.length * percentile) - 1)] * 100) / 100 : 0;
}
function summarize(rows, seconds) {
    const latencies = rows.map(row => row.milliseconds).sort((a, b) => a - b);
    const errors = rows.filter(row => row.error);
    const counts = key => rows.reduce((all, row) => { all[row[key] ?? 'none'] = (all[row[key] ?? 'none'] ?? 0) + 1; return all; }, {});
    const average = key => Math.round(rows.reduce((total, row) => total + (row[key] ?? 0), 0) / Math.max(rows.length, 1) * 100) / 100;
    return { requests: rows.length, errors: errors.length, errorPercent: Math.round(errors.length / Math.max(rows.length, 1) * 10000) / 100,
        requestsPerSecond: Math.round(rows.length / seconds * 100) / 100, p50Ms: quantile(latencies, 0.5),
        p95Ms: quantile(latencies, 0.95), p99Ms: quantile(latencies, 0.99), maxMs: latencies.at(-1) ?? 0,
        averageQueries: average('queries'), averageSqlMs: average('sqlMs'), averageAppMs: average('appMs'),
        statusCounts: counts('status'), errorCounts: errors.reduce((all, row) => { all[row.error] = (all[row.error] ?? 0) + 1; return all; }, {}) };
}
async function interaction(player, ordinal) {
    if (player.active && Date.now() >= player.active.endsAt) {
        const result = await request(player, 'care-complete', `/pets/${player.pet}/care/complete`, {
            method: 'POST', body: { token: player.active.token },
        });
        if (!result.error) player.active = null;
        await request(player, 'dashboard-poll', '/dashboard', { partial: 'pet,care', component: 'Dashboard' });
        return;
    }
    if (!player.started) {
        const training = player.id % 4 === 0;
        await request(player, 'care-items', `/care-items?category=${training ? 'sports' : 'food'}`);
        const token = randomUUID();
        const result = await request(player, training ? 'training-start' : 'water-start', `/pets/${player.pet}/care`, {
            method: 'POST', body: { token, variant: training ? `training:${fixture.training}` : 'water',
                items: training ? { sports: player.sports } : {} },
        });
        player.started = true;
        if (!result.error) player.active = { token, endsAt: Date.now() + ((training ? fixture.trainingDuration : fixture.waterDuration) + 1) * 1000 };
        await request(player, 'dashboard-poll', '/dashboard', { partial: 'pet,care', component: 'Dashboard' });
        return;
    }
    switch (ordinal % 5) {
        case 0: await dashboard(player); break;
        case 1: await request(player, 'shop', '/shop', { component: 'Shop' }); break;
        case 2: await request(player, 'inventory', '/inventory', { component: 'Inventory' }); break;
        case 3: await request(player, 'dashboard-poll', '/dashboard', { partial: 'pet,care', component: 'Dashboard' }); break;
        case 4: await request(player, 'care-items', '/care-items?category=sports'); break;
    }
}
const report = { createdAt: new Date().toISOString(), options: { ...options, stages },
    environment: { platform: os.platform(), release: os.release(), cpu: os.cpus()[0].model,
        logicalCpus: os.cpus().length, memoryGiB: Math.round(os.totalmem() / 2 ** 30 * 100) / 100,
        node: process.version, database: 'PostgreSQL', databaseSchema, sessions: 'redis', cache: 'redis',
        configCached: true, routesCached: true, debug: false, phpWorkers: options.workers },
    scope: 'Authenticated dynamic HTTP requests, full Laravel middleware including CSRF. 25% train, 75% water. '
        + 'Each player starts one activity and completes it after its real duration. Then paced dashboard/deferred props, shop, inventory, care selection and state polling. '
        + 'No login burst, static downloads, browser rendering or Internet latency. Generator and server share this Windows machine.',
    thresholds: { p95Ms: 2000, errorPercent: 1 }, stages: [] };
try {
    console.log(`Run directory: ${run}`);
    console.log(`Preparing ${options.users} players, isolated database and ${options.workers} PHP workers...`);
    await command(values.php, ['tests/Fixtures/load-fixture.php', 'prepare', String(options.users)], 'prepare');
    fixture = JSON.parse(await readFile(path.join(run, 'participants.json'), 'utf8'));
    report.fixture = JSON.parse(await readFile(path.join(run, 'prepare.log'), 'utf8'));
    await command(values.php, ['artisan', 'config:cache', '--no-interaction'], 'config-cache');
    await command(values.php, ['artisan', 'route:cache', '--no-interaction'], 'route-cache');
    const cgi = path.join(path.dirname(values.php), process.platform === 'win32' ? 'php-cgi.exe' : 'php-cgi');
    for (const [index, backendPort] of backendPorts.entries()) {
        const child = launch(cgi, ['-d', 'opcache.enable=1', '-d', 'opcache.memory_consumption=128', '-b', `127.0.0.1:${backendPort}`], `php-${index}`);
        await waitPort(backendPort, child);
    }
    const nginxConfig = `master_process off;
daemon off;
worker_processes 1;
pid "${slash(path.join(run, 'nginx.pid'))}";
error_log "${slash(path.join(run, 'nginx-error.log'))}" warn;
events { worker_connections 4096; }
http {
    access_log off;
    client_body_temp_path "${slash(path.join(run, 'nginx-temp'))}";
    fastcgi_temp_path "${slash(path.join(run, 'nginx-temp'))}";
    upstream dogload { least_conn; ${backendPorts.map(value => `server 127.0.0.1:${value};`).join(' ')} }
    server {
        listen 127.0.0.1:${port};
        server_name 127.0.0.1;
        location / {
            include "${slash(path.join(path.dirname(values.nginx), 'conf/fastcgi_params'))}";
            fastcgi_param SCRIPT_FILENAME "${slash(path.join(root, 'tests/Fixtures/load-entry.php'))}";
            fastcgi_param SCRIPT_NAME /index.php;
            fastcgi_param DOCUMENT_ROOT "${slash(path.join(root, 'public'))}";
            fastcgi_param HTTP_PROXY "";
            fastcgi_buffer_size 32k;
            fastcgi_buffers 8 32k;
            fastcgi_read_timeout 10s;
            fastcgi_next_upstream off;
            fastcgi_pass dogload;
        }
    }
}`;
    await writeFile(path.join(run, 'nginx.conf'), nginxConfig);
    const nginx = launch(values.nginx, ['-p', `${slash(run)}/`, '-c', slash(path.join(run, 'nginx.conf'))], 'nginx');
    await waitPort(port, nginx);
    const probe = await request(fixture.players[0], 'probe', '/dashboard', { component: 'Dashboard', record: false });
    if (probe.error) throw new Error(`Authenticated HTTP probe failed: ${probe.error}`);
    for (let index = 0; index < options.workers * 2; index++) {
        const responses = await dashboard(fixture.players[index % fixture.players.length], false);
        if (responses.some(response => response.error)) throw new Error('Deferred dashboard warm-up failed.');
    }
    console.log('Authentication, isolated HTTP target and deferred responses checked. Starting measured stages.');
    for (const [stageIndex, users] of stages.entries()) {
        const duration = stageIndex === stages.length - 1 ? options.hold : options.seconds;
        const start = performance.now();
        const end = start + duration * 1000;
        const from = measurements.length;
        const actionDurations = [];
        const lateness = [];
        let skippedSlots = 0;
        let actions = 0;
        maxInFlight = 0;
        const eventLoop = monitorEventLoopDelay({ resolution: 20 });
        eventLoop.enable();
        const progress = setInterval(() => {
            const rows = measurements.slice(from);
            console.log(`${users} online: ${Math.round((performance.now() - start) / 1000)}s, ${rows.length} requests, ${rows.filter(row => row.error).length} errors, ${inFlight} in flight`);
        }, 15000);
        try {
            await Promise.all(fixture.players.slice(0, users).map(async (player, index) => {
                let due = start + index / users * options.period * 1000;
                let ordinal = index;
                while (due < end && !stopping) {
                    await sleep(Math.max(0, due - performance.now()));
                    if (performance.now() >= end) break;
                    lateness.push(performance.now() - due);
                    const actionStart = performance.now();
                    await interaction(player, ordinal++);
                    actionDurations.push(performance.now() - actionStart);
                    actions++;
                    due += options.period * 1000;
                    while (due < performance.now()) { skippedSlots++; due += options.period * 1000; }
                }
            }));
        } finally { clearInterval(progress); eventLoop.disable(); }
        const elapsed = (performance.now() - start) / 1000;
        const rows = measurements.slice(from);
        const summary = summarize(rows, elapsed);
        const stage = { users, scheduledSeconds: duration, elapsedSeconds: elapsed, actions, skippedSlots,
            targetActionsPerSecond: users / options.period, achievedActionsPerSecond: actions / elapsed,
            maxConcurrentRequests: maxInFlight, actionP95Ms: quantile(actionDurations.sort((a, b) => a - b), 0.95),
            startDelayP95Ms: quantile(lateness.sort((a, b) => a - b), 0.95),
            generatorEventLoopP99Ms: eventLoop.percentile(99) / 1e6,
            freeMemoryGiB: os.freemem() / 2 ** 30, ...summary,
            routes: Object.fromEntries([...new Set(rows.map(row => row.label))].map(label => [label, summarize(rows.filter(row => row.label === label), elapsed)])),
            passed: summary.p95Ms <= report.thresholds.p95Ms && summary.errorPercent < report.thresholds.errorPercent && skippedSlots === 0 };
        report.stages.push(stage);
        await writeFile(path.join(run, 'report.json'), JSON.stringify(report, null, 2));
        console.log(`STAGE ${users}: ${JSON.stringify({ rps: stage.requestsPerSecond, p95Ms: stage.p95Ms, errors: stage.errors, skippedSlots, passed: stage.passed })}`);
    }
    await cleanup();
    await command(values.php, ['tests/Fixtures/load-fixture.php', 'inspect'], 'inspect');
    report.databaseAfter = JSON.parse(await readFile(path.join(run, 'inspect.log'), 'utf8'));
    report.mutationChecks = {
        acknowledgedStarts: measurements.filter(row => ['water-start', 'training-start'].includes(row.label) && !row.error).length,
        acknowledgedCompletions: measurements.filter(row => row.label === 'care-complete' && !row.error).length,
        startsMatch: report.databaseAfter.started === measurements.filter(row => ['water-start', 'training-start'].includes(row.label) && !row.error).length,
        completionsMatch: report.databaseAfter.completed === measurements.filter(row => row.label === 'care-complete' && !row.error).length,
        activePetsMatch: report.databaseAfter.activePets === report.databaseAfter.started - report.databaseAfter.completed,
        trainingItemsMatch: report.databaseAfter.itemsUsed === report.databaseAfter.trainingsStarted,
        trainingGainsMatch: report.databaseAfter.trainedPets === report.databaseAfter.trainingsCompleted,
    };
    report.passed = report.stages.every(stage => stage.passed)
        && report.databaseAfter.invalidPetEnergy === 0 && report.databaseAfter.invalidInventoryUses === 0
        && Object.entries(report.mutationChecks).filter(([key]) => key.endsWith('Match')).every(([, value]) => value);
    console.log(`Database checks: ${JSON.stringify(report.databaseAfter)}`);
    console.log(`Result: ${report.passed ? 'PASS' : 'FAIL'}; report: ${path.join(run, 'report.json')}`);
    process.exitCode = report.passed ? 0 : 2;
} catch (error) {
    report.fatalError = error.message;
    process.exitCode = 1;
    console.error(error.message);
} finally {
    await cleanup();
    report.finishedAt = new Date().toISOString();
    await writeFile(path.join(run, 'report.json'), JSON.stringify(report, null, 2));
}
