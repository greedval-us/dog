<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\Actions\TrainPetSkill;
use App\Modules\Pets\DTO\TrainPetSkillData;
use App\Modules\Pets\Queries\GetPetSkills;
use App\Modules\Telegram\DTO\TelegramScreen;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class SkillMenu
{
    public function __construct(
        private MenuView $view,
        private GetPetSkills $skills,
        private TrainPetSkill $trainSkill,
    ) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command, ?string $notice = null): TelegramScreen
    {
        $petId = (int) $command['petId'];
        $data = $this->skills->handle($user, $petId, $locale);
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $lines = array_values(array_filter([$notice, $this->view->translate('skill.title', $locale)]));
        $rows = [];
        foreach (array_slice($data['skills'], $offset, MenuView::PAGE_SIZE) as $skill) {
            $label = $skill['name'].' · '.$this->view->translate('skill.level', $locale, ['level' => $skill['level']]);
            $rows[] = [$this->view->commandButton($label, ['action' => 'skills.lesson', 'petId' => $petId, 'skillId' => $skill['id'], 'offset' => $offset])];
        }
        if ($data['skills'] === []) {
            $lines[] = $this->view->translate('skill.empty', $locale);
        }
        $rows = [...$rows, ...$this->view->offsetNavigation(count($data['skills']), $offset, ['action' => 'skills', 'petId' => $petId], $locale)];

        return $this->view->screen(implode("\n", $lines), $rows, $locale, ['action' => 'pet', 'petId' => $petId]);
    }

    /** @param Command $command */
    public function lesson(User $user, string $locale, array $command): TelegramScreen
    {
        $petId = (int) $command['petId'];
        $data = $this->skills->handle($user, $petId, $locale);
        $skill = collect($data['skills'])->firstWhere('id', (int) $command['skillId']);
        if ($skill === null) {
            throw new ModelNotFoundException;
        }

        $name = Str::limit($skill['name'], 120);
        $lines = [$name, Str::limit($skill['description'], 350), $this->view->translate('skill.level', $locale, ['level' => $skill['level']])];
        if ($skill['level'] > 0) {
            $lines[] = $this->view->translate($skill['active'] ? 'skill.active' : 'skill.inactive', $locale);
        }
        $nextLevel = $skill['levels'][$skill['level']] ?? null;
        if ($nextLevel !== null) {
            $lines[] = $this->view->translate('skill.next_level', $locale, ['level' => $skill['level'] + 1]);
            $lines[] = $this->view->translate('price', $locale).': '.$nextLevel['price'].' '.$this->view->translate('coins', $locale);
            $lines[] = $this->view->translate('skill.requirements', $locale);
            foreach ($nextLevel['requirements'] as $stat => $minimum) {
                $lines[] = $this->view->translate('stats.'.$stat, $locale).': '.$minimum.' ('.$nextLevel['requirementPercentages'][$stat].'%)';
            }
        }
        if ($skill['cooldownUntil'] !== null && CarbonImmutable::parse($skill['cooldownUntil'])->isFuture()) {
            $lines[] = $this->view->translate('skill.cooldown_until', $locale, ['time' => $this->view->dateTime($skill['cooldownUntil'], $user)]);
        }
        if ($skill['reason'] !== null) {
            $lines[] = $this->view->reason($skill['reason'], $locale);
        }

        $rows = [];
        $state = [];
        if ($skill['canTrain'] && $nextLevel !== null) {
            $lines[] = $this->view->translate('skill.confirm', $locale, ['name' => $name, 'level' => $skill['level'] + 1, 'price' => $nextLevel['price']]);
            $rows[] = [$this->view->button('skill.train', 'skills.train', $locale)];
            $state['skillLesson'] = ['petId' => $petId, 'skillId' => $skill['id'], 'level' => $skill['level'] + 1,
                'expectedPrice' => $nextLevel['price'], 'token' => $data['token'], 'name' => $name, 'offset' => (int) ($command['offset'] ?? 0)];
        }
        $rows[] = [$this->view->button('refresh', 'skills.lesson', $locale, $command)];

        return $this->view->screen(implode("\n", array_filter($lines, fn (string $line): bool => $line !== '')), $rows, $locale,
            ['action' => 'skills', 'petId' => $petId, 'offset' => (int) ($command['offset'] ?? 0)], $state);
    }

    /** @param State $state */
    public function train(User $user, string $locale, array $state): TelegramScreen
    {
        $quote = new SavedMenuState($state, 'skillLesson');
        $petId = $quote->integer('petId');
        $level = $quote->integer('level');
        $name = $quote->string('name');
        $offset = $quote->integer('offset', minimum: 0, default: 0);
        $this->trainSkill->handle($user, $petId, new TrainPetSkillData(
            $quote->integer('skillId'), $level, $quote->integer('expectedPrice', minimum: 0), $quote->uuid(),
        ));

        return $this->index($user->refresh(), $locale, ['petId' => $petId, 'offset' => $offset],
            $this->view->translate('skill.trained', $locale, ['name' => Str::limit($name, 120), 'level' => $level]));
    }
}
