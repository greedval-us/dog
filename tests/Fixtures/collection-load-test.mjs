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
        pets: { type: 'string', default: '10,100,500,1000' },
        career: { type: 'string', default: '100,1000,10000' },
        repeats: { type: 'string', default: '3' },
        'request-budget-ms': { type: 'string', default: '2000' },
        'memory-limit': { type: 'string', default: '256M' },
        help: { type: 'boolean', default: false },
    },
});
if (values.help) {
    console.log(
        'Isolated player-collection benchmark on dog_loadtest; never accepts an external target.\n' +
            'node tests/Fixtures/collection-load-test.mjs --php <absolute php.exe path>\n' +
            'Optional: --pets 10,100,500,1000 --career 100,1000,10000 --repeats 3 --request-budget-ms 2000 --memory-limit 256M\n' +
            'Measures GetPlayerDogs, the first and next GetPetCareer page; setup, cursor preparation, JSON encoding, validation and EXPLAIN are excluded.\n' +
            'Each measurement uses a fresh PHP process. Fixed appearance catalogue: 100 assets, 80 compatible per dog.\n' +
            'Career contains one winning result/title per event over five disciplines and daily/weekly frequencies.\n' +
            'Large dog counts are stress scenarios beyond the regular pet-slot allowance. No HTTP, lifecycle synchronization or concurrency is simulated.\n' +
            'The request budget is an assessment threshold, not an application limit.\n' +
            'Retains the private schema, logs, EXPLAIN plans and JSON report in ignored storage/framework/testing.',
    );
    process.exit(0);
}
const pets = values.pets.split(',').map(Number);
const career = values.career.split(',').map(Number);
const repeats = Number(values.repeats);
const requestBudgetMs = Number(values['request-budget-ms']);
if (
    !values.php ||
    !path.isAbsolute(values.php) ||
    !Number.isInteger(repeats) ||
    repeats < 1 ||
    repeats > 5 ||
    !Number.isInteger(requestBudgetMs) ||
    requestBudgetMs < 1 ||
    !/^(128|256|512)M$/.test(values['memory-limit']) ||
    pets.some((size) => !Number.isInteger(size) || size < 1 || size > 1000) ||
    career.some(
        (size) =>
            !Number.isInteger(size) ||
            size < 30 ||
            size > 10000 ||
            size % 10 !== 0,
    ) ||
    new Set(pets).size !== pets.length ||
    new Set(career).size !== career.length
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
    `loadtest-collections-${new Date().toISOString().replaceAll(/[:.]/g, '-')}-${randomUUID().slice(0, 8)}`,
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
    const files = [
        'composer.lock',
        'tests/Fixtures/collection-load-fixture.php',
        'tests/Fixtures/collection-load-test.mjs',
        'tests/Fixtures/load-bootstrap.php',
    ];
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
                'tests/Fixtures/collection-load-fixture.php',
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
            `Source changed during ${name}; restart it. Partial report: ${run}`,
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
    requestBudgetMs,
    repeats,
    sourceFingerprint: fingerprint,
    workload: {
        pets,
        careerResults: career,
        assetCatalogueSize: 100,
        compatibleAssetsPerPet: 80,
        breedsPerOwner: 1,
        disciplines: 5,
        frequencies: ['daily', 'weekly'],
        titleGroups: 10,
        careerPageSize: 20,
        concurrentWorkers: 1,
        includesHttpAndLifecycle: false,
        includesJsonEncoding: false,
    },
    metadata: await command(['prepare-schema'], 'prepare-schema'),
    measurements: [],
    plans: [],
};
const reportPath = path.join(run, 'report.json');
await writeFile(reportPath, JSON.stringify(report, null, 2));
for (const { kind, sizes, scenarios } of [
    { kind: 'dogs', sizes: pets, scenarios: ['dogs'] },
    {
        kind: 'career',
        sizes: career,
        scenarios: ['career-first', 'career-next'],
    },
]) {
    for (const size of sizes) {
        const fixture = await command(
            [`prepare-${kind}`, String(size)],
            `${kind}-${size}-prepare`,
        );
        for (let repeat = 1; repeat <= repeats; repeat++) {
            for (const scenario of scenarios) {
                const measurement = await command(
                    [scenario, fixture.fixture],
                    `${scenario}-${size}-${repeat}`,
                );
                report.measurements.push({
                    ...measurement,
                    repeat,
                    withinRequestBudget:
                        measurement.elapsedMs <= requestBudgetMs,
                });
                await writeFile(reportPath, JSON.stringify(report, null, 2));
                console.log(
                    `${scenario} ${size} #${repeat}: ${measurement.elapsedMs} ms, ${measurement.queries} queries, ${measurement.peakMemoryMiB} MiB peak, ${measurement.payloadBytes} bytes`,
                );
            }
        }
        const plans = await command(
            [`plans-${kind}`, fixture.fixture],
            `${kind}-${size}-plans`,
        );
        const plansFile = `${kind}-${size}-plans.json`;
        await writeFile(
            path.join(run, plansFile),
            JSON.stringify(plans, null, 2),
        );
        report.plans.push({ kind, size, file: plansFile });
        await writeFile(reportPath, JSON.stringify(report, null, 2));
    }
}
report.summary = [];
for (const { scenario, sizes } of [
    { scenario: 'dogs', sizes: pets },
    { scenario: 'career-first', sizes: career },
    { scenario: 'career-next', sizes: career },
]) {
    for (const size of sizes) {
        const samples = report.measurements.filter(
            (row) => row.scenario === scenario && row.size === size,
        );
        const times = samples.map((row) => row.elapsedMs).sort((a, b) => a - b);
        report.summary.push({
            scenario,
            size,
            samples: samples.length,
            medianElapsedMs:
                (times[Math.floor((times.length - 1) / 2)] +
                    times[Math.floor(times.length / 2)]) /
                2,
            maxElapsedMs: Math.max(...times),
            maxQueries: Math.max(...samples.map((row) => row.queries)),
            maxSqlMs: Math.max(...samples.map((row) => row.sqlMs)),
            peakMemoryMiB: Math.max(...samples.map((row) => row.peakMemoryMiB)),
            maxPayloadBytes: Math.max(
                ...samples.map((row) => row.payloadBytes),
            ),
            withinRequestBudget: samples.every(
                (row) => row.withinRequestBudget,
            ),
        });
    }
}
report.complete = true;
await writeFile(reportPath, JSON.stringify(report, null, 2));
console.log(`Verified collection report: ${reportPath}`);
