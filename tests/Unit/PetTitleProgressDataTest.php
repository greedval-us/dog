<?php

use App\Models\GameEventEntry;
use App\Models\PetTitle;
use App\Modules\Pets\DTO\PetTitleProgressData;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

beforeEach(function () {
    $this->originalProgressContainer = Container::getInstance();
    $container = new Container;
    $container->instance('translator', new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), 'en'));
    Container::setInstance($container);
});

afterEach(function () {
    Container::setInstance($this->originalProgressContainer);
});

function progressDataTitle(int $id, string $code = 'CAC'): PetTitle
{
    $title = (new PetTitle)->setDateFormat('Y-m-d H:i:s')->forceFill([
        'id' => $id, 'pet_id' => 7, 'game_event_entry_id' => $id, 'game_event_id' => 100 + $id,
        'code' => $code, 'kind' => 'certificate', 'discipline' => 'conformation', 'frequency' => 'daily',
        'class_key' => 'open', 'awarded_at' => '2026-10-19 12:00:00', 'details' => [],
    ]);
    $title->setRelation('entry', (new GameEventEntry)->forceFill(['game_event_id' => 100 + $id]));

    return $title;
}

function progressDataGoal(array $changes = []): array
{
    return array_replace([
        'code' => 'CH_RUS', 'discipline' => 'conformation', 'scope_key' => '', 'repeatable' => false,
        'achieved' => false, 'earnedCount' => 0,
        'paths' => [[
            'key' => 'standard', 'ready' => false,
            'requirements' => [
                ['key' => 'certificates', 'kind' => 'count', 'codes' => ['CAC'], 'current' => 2, 'required' => 4, 'met' => false],
                ['key' => 'judges', 'kind' => 'distinct', 'current' => 2, 'required' => 4, 'met' => false],
            ],
            'proof_title_ids' => [1], 'available_title_ids' => [1, 2],
            'excluded' => [['title_id' => 3, 'reason' => 'used'], ['title_id' => 4, 'reason' => 'expired'], ['title_id' => 5, 'reason' => 'ineligible']],
            'events' => [], 'notes' => [],
        ]],
    ], $changes);
}

test('title progress localizes goals and preserves the actual document status and result links', function (string $locale, string $titleName, string $pathName) {
    $titles = [];
    foreach (range(1, 5) as $id) {
        $titles[$id] = progressDataTitle($id);
    }

    $data = PetTitleProgressData::fromGoals([progressDataGoal()], $titles, $locale, 1792497600);

    expect($data['generatedAt'])->toBe('2026-10-20T12:00:00+00:00');
    $goal = $data['goals'][0];
    expect($goal['name'])->toBe($titleName);
    expect($goal['status'])->toBe('in_progress');
    expect($goal['paths'][0]['label'])->toBe($pathName);
    expect(array_column($goal['paths'][0]['credentials'], 'status'))->toBe(['counted', 'available', 'used', 'expired', 'ineligible']);
    expect(array_column($goal['paths'][0]['credentials'], 'eventId'))->toBe([101, 102, 103, 104, 105]);
    expect($goal['paths'][0]['credentials'][2]['reason'])->not->toBeNull();
    expect($goal['paths'][0]['requirements'][0])->toMatchArray(['current' => 2, 'target' => 4, 'met' => false]);
})->with([
    'English' => ['en', 'Russian Beauty Champion', 'Standard certificate route'],
    'Russian' => ['ru', 'Чемпион России по красоте', 'Основная схема по сертификатам'],
]);

test('interval progress is displayed in game months and requires a new certificate after its date', function () {
    $goal = progressDataGoal();
    $goal['paths'][0]['requirements'] = [[
        'key' => 'interval', 'kind' => 'interval', 'current' => 86400, 'required' => 1036800, 'met' => false, 'ready_at' => 1792670400,
    ]];
    $goal['paths'][0]['proof_title_ids'] = [];
    $goal['paths'][0]['available_title_ids'] = [];
    $goal['paths'][0]['excluded'] = [];

    $data = PetTitleProgressData::fromGoals([$goal], [], 'en', 1792497600);

    $requirement = $data['goals'][0]['paths'][0]['requirements'][0];
    expect($requirement['current'])->toEqual(1);
    expect($requirement['target'])->toEqual(12);
    expect($requirement['label'])->toContain('game months');
    expect($requirement['note'])->toContain('new certificate')->toContain('22.10.2026 15:00')->toContain('Waiting alone');
});

test('progress does not infer that individually fulfilled requirements are a compatible ready proof', function () {
    $goal = progressDataGoal();
    $goal['paths'][0]['requirements'] = [['key' => 'judges', 'kind' => 'distinct', 'current' => 4, 'required' => 4, 'met' => true]];
    $goal['paths'][0]['proof_title_ids'] = [];
    $goal['paths'][0]['available_title_ids'] = [];
    $goal['paths'][0]['excluded'] = [];

    $data = PetTitleProgressData::fromGoals([$goal], [], 'en', 1792497600);

    expect($data['goals'][0]['status'])->toBe('in_progress');
    expect($data['goals'][0]['paths'][0]['ready'])->toBeFalse();
});

test('an already earned repeatable title retains its fresh requirements instead of becoming completed', function () {
    $goal = progressDataGoal(['code' => 'SPORT_REGIONAL_CH', 'discipline' => 'agility', 'repeatable' => true, 'earnedCount' => 2, 'scope_key' => 'region:moscow']);
    $goal['paths'][0]['proof_title_ids'] = [];
    $goal['paths'][0]['available_title_ids'] = [];
    $goal['paths'][0]['excluded'] = [];

    $data = PetTitleProgressData::fromGoals([$goal], [], 'ru', 1792497600);

    expect($data['goals'][0])->toMatchArray(['status' => 'in_progress', 'earnedCount' => 2, 'repeatable' => true, 'scopeLabel' => 'Москва']);
    expect($data['goals'][0]['paths'][0]['requirements'])->not->toBeEmpty();
});

test('only a document barrier on every route marks the goal as blocked', function () {
    $goal = progressDataGoal();
    $goal['paths'] = [
        ['key' => 'first', 'ready' => false, 'requirements' => [['key' => 'registered', 'kind' => 'document', 'current' => 0, 'required' => 1, 'met' => false]]],
        ['key' => 'second', 'ready' => false, 'requirements' => [['key' => 'certificates', 'kind' => 'count', 'current' => 0, 'required' => 2, 'met' => false]]],
    ];
    $open = PetTitleProgressData::fromGoals([$goal], [], 'en', 1792497600);
    $goal['paths'][1]['requirements'][] = ['key' => 'pedigree_complete', 'kind' => 'document', 'current' => 0, 'required' => 1, 'met' => false];
    $blocked = PetTitleProgressData::fromGoals([$goal], [], 'en', 1792497600);

    expect($open['goals'][0]['status'])->toBe('in_progress');
    expect($blocked['goals'][0]['status'])->toBe('blocked');
});

test('the closest route is recommended without changing its readiness or hiding other routes', function () {
    $goal = progressDataGoal();
    $goal['paths'] = [
        ['key' => 'first', 'ready' => false, 'requirements' => [
            ['key' => 'judges', 'kind' => 'distinct', 'current' => 0, 'required' => 4, 'met' => false],
            ['key' => 'certificates', 'kind' => 'count', 'current' => 0, 'required' => 4, 'met' => false],
        ]],
        ['key' => 'second', 'ready' => false, 'requirements' => [['key' => 'judges', 'kind' => 'distinct', 'current' => 1, 'required' => 2, 'met' => false]]],
    ];

    $data = PetTitleProgressData::fromGoals([$goal], [], 'en', 1792497600);

    expect(array_column($data['goals'][0]['paths'], 'recommended'))->toBe([false, true]);
    expect(array_column($data['goals'][0]['paths'], 'ready'))->toBe([false, false]);
});

test('repeated certificate requirement keys remain distinct and their show legs are readable', function () {
    $goal = progressDataGoal(['code' => 'EAAW', 'repeatable' => true, 'scope_key' => 'edition:2050-05']);
    $goal['paths'] = [['key' => 'paired_show_winners', 'ready' => false, 'requirements' => [
        ['key' => 'certificates', 'kind' => 'count', 'codes' => ['EAW'], 'showLeg' => 'president', 'current' => 1, 'required' => 1, 'met' => true],
        ['key' => 'certificates', 'kind' => 'count', 'codes' => ['EAW'], 'showLeg' => 'cis', 'current' => 0, 'required' => 1, 'met' => false],
    ]]];

    $data = PetTitleProgressData::fromGoals([$goal], [], 'ru', 1792497600);

    $requirements = $data['goals'][0]['paths'][0]['requirements'];
    expect(array_unique(array_column($requirements, 'key')))->toHaveCount(2);
    expect($requirements[0]['label'])->toContain('Кубок Президента РКФ');
    expect($requirements[1]['label'])->toContain('Победитель СНГ');
    expect($data['goals'][0]['scopeLabel'])->toBe('Игровой выпуск: 2050-05');
});

test('missing document models are explained rather than silently omitted', function () {
    $data = PetTitleProgressData::fromGoals([progressDataGoal()], [], 'en', 1792497600);

    expect($data['goals'][0]['paths'][0]['credentials'])->toBeEmpty();
    expect($data['goals'][0]['paths'][0]['notes'])->toContain('Some supporting documents could not be loaded. Refresh your dog’s career.');
});
