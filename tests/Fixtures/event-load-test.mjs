import { spawn } from 'node:child_process';
import { createHash, randomBytes, randomUUID } from 'node:crypto';
import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';

const { values } = parseArgs({
    options: {
        php: { type: 'string' },
        groups: { type: 'string', default: '1,10,50,100' },
        'humans-per-group': { type: 'string', default: '8,1' },
        repeats: { type: 'string', default: '3' },
        'transaction-budget-ms': { type: 'string', default: '5000' },
        'memory-limit': { type: 'string', default: '256M' },
        help: { type: 'boolean', default: false },
    },
});
if (values.help) {
    console.log(
        'Isolated event-processing benchmark on dog_loadtest; never accepts an external target.\n' +
            'node tests/Fixtures/event-load-test.mjs --php <absolute php.exe path>\n' +
            'Optional: --groups 1,10,50,100 --humans-per-group 8,1 --repeats 3 --transaction-budget-ms 5000 --memory-limit 256M\n' +
            'Measures freeze, settle and overdue combined processing; 8 humans and 1 human per group.\n' +
            'Setup and correctness checks are excluded. A fresh PHP process measures each phase.\n' +
            'The budget is an assessment threshold, not an application limit. No concurrency is simulated.\n' +
            'Includes the seeded achievement catalogue, one fresh pet per owner and no gear or unfinished care.\n' +
            'Retains the private schema, logs and JSON report in ignored storage/framework/testing.',
    );
    process.exit(0);
}
const groups = values.groups.split(',').map(Number);
const humanCounts = values['humans-per-group'].split(',').map(Number);
const repeats = Number(values.repeats);
const transactionBudgetMs = Number(values['transaction-budget-ms']);
if (
    !values.php ||
    !path.isAbsolute(values.php) ||
    !Number.isInteger(repeats) ||
    repeats < 1 ||
    repeats > 5 ||
    !Number.isInteger(transactionBudgetMs) ||
    transactionBudgetMs < 1 ||
    !/^(128|256|512)M$/.test(values['memory-limit']) ||
    humanCounts.some((count) => ![1, 8].includes(count)) ||
    new Set(humanCounts).size !== humanCounts.length ||
    groups.length === 0 ||
    groups.some((size) => !Number.isInteger(size) || size < 1 || size > 500)
) {
    throw new Error('Supply valid bounded benchmark options. See --help.');
}
const root = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '../..',
);
const run = path.join(
    root,
    'storage/framework/testing',
    `loadtest-events-${new Date().toISOString().replaceAll(/[:.]/g, '-')}-${randomUUID().slice(0, 8)}`,
);
await mkdir(run);
for (const directory of ['logs', 'framework/views', 'temp']) {
    await mkdir(path.join(run, directory), { recursive: true });
}
const schema = `loadtest_${randomBytes(8).toString('hex')}`;
await writeFile(path.join(run, 'database-schema'), schema);
const slash = (value) => value.replaceAll('\\', '/');
const cachePath = (filename) =>
    slash(path.relative(root, path.join(run, filename)));
const env = {
    ...process.env,
    APP_ENV: 'loadtest',
    APP_DEBUG: 'false',
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    APP_PREVIOUS_KEYS: '',
    DB_CONNECTION: 'pgsql',
    DB_DATABASE: 'dog_loadtest',
    DB_SCHEMA: schema,
    DB_URL: '',
    REDIS_PREFIX: `${schema}-database-`,
    LARAVEL_STORAGE_PATH: slash(run),
    DOG_LOAD_RUN: run,
    SESSION_DRIVER: 'redis',
    CACHE_STORE: 'redis',
    CACHE_PREFIX: `${schema}-cache-`,
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    LOG_CHANNEL: 'single',
    LOG_LEVEL: 'error',
    BCRYPT_ROUNDS: '4',
    APP_CONFIG_CACHE: cachePath('config.php'),
    APP_ROUTES_CACHE: cachePath('routes.php'),
    APP_EVENTS_CACHE: cachePath('events.php'),
    APP_PACKAGES_CACHE: cachePath('packages.php'),
    APP_SERVICES_CACHE: cachePath('services.php'),
    VIEW_COMPILED_PATH: slash(path.join(run, 'framework/views')),
};
async function sourceFingerprint() {
    const files = ['composer.lock', 'tests/Fixtures/event-load-fixture.php'];
    for (const directory of ['app', 'config', 'database']) {
        files.push(
            ...(await readdir(path.join(root, directory), { recursive: true }))
                .filter((filename) => filename.endsWith('.php'))
                .map((filename) => path.join(directory, filename)),
        );
    }
    const hash = createHash('sha256');
    for (const filename of files.sort()) {
        hash.update(filename);
        hash.update(await readFile(path.join(root, filename)));
    }
    return hash.digest('hex');
}
const fingerprint = await sourceFingerprint();
let activeChild;
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.once(signal, () => {
        activeChild?.kill();
        process.exit(130);
    });
}
async function command(args, name) {
    if ((await sourceFingerprint()) !== fingerprint) {
        throw new Error(
            `Source changed during the benchmark; restart it. Partial report: ${run}`,
        );
    }
    const result = await new Promise((resolve, reject) => {
        const child = spawn(
            values.php,
            [
                '-d',
                `memory_limit=${values['memory-limit']}`,
                '-d',
                `sys_temp_dir=${path.join(run, 'temp')}`,
                'tests/Fixtures/event-load-fixture.php',
                ...args,
            ],
            {
                cwd: root,
                env,
                windowsHide: true,
                stdio: ['ignore', 'pipe', 'pipe'],
            },
        );
        activeChild = child;
        let stdout = '';
        let stderr = '';
        const timeout = setTimeout(() => child.kill(), 120_000);
        child.stdout.on('data', (data) => (stdout += data.toString()));
        child.stderr.on('data', (data) => (stderr += data.toString()));
        child.once('error', (error) => {
            clearTimeout(timeout);
            activeChild = undefined;
            reject(error);
        });
        child.once('close', (code) => {
            clearTimeout(timeout);
            activeChild = undefined;
            resolve({ code, stdout, stderr });
        });
    });
    await writeFile(
        path.join(run, `${name}.log`),
        result.stdout + result.stderr,
    );
    if ((await sourceFingerprint()) !== fingerprint) {
        throw new Error(
            `Source changed during ${name}; restart the benchmark. Partial report: ${run}`,
        );
    }
    if (result.code !== 0) {
        throw new Error(`${name} failed (${result.code}); inspect ${run}`);
    }
    try {
        return JSON.parse(result.stdout);
    } catch {
        throw new Error(`${name} did not return a JSON result; inspect ${run}`);
    }
}
const report = {
    run,
    schema,
    platform: os.platform(),
    cpus: os.cpus()[0]?.model,
    cpuCount: os.cpus().length,
    totalMemoryMiB: Math.round(os.totalmem() / 1048576),
    transactionBudgetMs,
    repeats,
    sourceFingerprint: fingerprint,
    workload: {
        discipline: 'agility',
        fieldSize: 8,
        petsPerOwner: 1,
        gearPerEntry: 0,
        priorActivities: 0,
        achievementCatalogue: 'AchievementSeeder',
        concurrentWorkers: 1,
        humanParticipantsPerGroup: humanCounts,
    },
    metadata: await command(['prepare-schema'], 'prepare-schema'),
    measurements: [],
};
await writeFile(path.join(run, 'report.json'), JSON.stringify(report, null, 2));
for (const groupCount of groups) {
    for (const humansPerGroup of humanCounts) {
        for (let repeat = 1; repeat <= repeats; repeat++) {
            const prefix = `${groupCount}-${humansPerGroup}-${repeat}`;
            let fixture = await command(
                [
                    'prepare-case',
                    String(groupCount),
                    String(humansPerGroup),
                    'split',
                ],
                `${prefix}-prepare-split`,
            );
            for (const phase of ['freeze', 'settle', 'backlog']) {
                if (phase === 'backlog') {
                    fixture = await command(
                        [
                            'prepare-case',
                            String(groupCount),
                            String(humansPerGroup),
                            'backlog',
                        ],
                        `${prefix}-prepare-backlog`,
                    );
                }
                const measurement = await command(
                    [phase, String(fixture.event)],
                    `${prefix}-${phase}`,
                );
                report.measurements.push({
                    ...measurement,
                    humansPerGroup,
                    repeat,
                    withinTransactionBudget:
                        measurement.transactionMs <= transactionBudgetMs,
                });
                await writeFile(
                    path.join(run, 'report.json'),
                    JSON.stringify(report, null, 2),
                );
                console.log(
                    `${prefix} ${phase}: ${measurement.transactionMs} ms transaction, ${measurement.queries} queries, ${measurement.peakMemoryMiB} MiB peak`,
                );
            }
        }
    }
}
report.summary = [];
for (const groupCount of groups) {
    for (const humansPerGroup of humanCounts) {
        for (const phase of ['freeze', 'settle', 'backlog']) {
            const samples = report.measurements.filter(
                (measurement) =>
                    measurement.groups === groupCount &&
                    measurement.humansPerGroup === humansPerGroup &&
                    measurement.phase === phase,
            );
            const times = samples
                .map((sample) => sample.transactionMs)
                .sort((a, b) => a - b);
            report.summary.push({
                groups: groupCount,
                humansPerGroup,
                phase,
                samples: samples.length,
                medianTransactionMs:
                    (times[Math.floor((times.length - 1) / 2)] +
                        times[Math.floor(times.length / 2)]) /
                    2,
                maxTransactionMs: Math.max(...times),
                maxQueries: Math.max(
                    ...samples.map((sample) => sample.queries),
                ),
                peakMemoryMiB: Math.max(
                    ...samples.map((sample) => sample.peakMemoryMiB),
                ),
                withinTransactionBudget: samples.every(
                    (sample) => sample.withinTransactionBudget,
                ),
            });
        }
    }
}
report.complete = true;
await writeFile(path.join(run, 'report.json'), JSON.stringify(report, null, 2));
console.log(
    `Verified event-processing report: ${path.join(run, 'report.json')}`,
);
