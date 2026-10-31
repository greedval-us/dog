<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\Actions\PurchaseVeterinaryService;
use App\Modules\Pets\DTO\PurchaseVeterinaryServiceData;
use App\Modules\Pets\Enums\VeterinaryService;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetVeterinarian;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class VeterinarianMenu
{
    public function __construct(private MenuView $view, private GetVeterinarian $clinic, private PurchaseVeterinaryService $purchase) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command, ?string $notice = null): TelegramScreen
    {
        $petId = (int) $command['petId'];
        $clinic = $this->clinic->handle($user, $petId, $locale);
        $dog = collect($clinic['dogs'])->firstWhere('id', $petId);
        $lines = array_values(array_filter([$notice, $this->view->translate('clinic.title', $locale).' · '.Str::limit($dog['name'], 120),
            $this->view->translate('stats.health', $locale).': '.round($clinic['health']).'%',
            $clinic['reason'] === null ? null : $this->view->reason($clinic['reason'], $locale),
        ]));
        $rows = [];
        foreach ($clinic['services'] as $service) {
            $label = $this->view->translate('clinic.'.$service['code'], $locale).' · '.$service['price'].' '.$this->view->translate('coins', $locale);
            $rows[] = [$this->view->commandButton($label, ['action' => $service['code'] === 'treatment' ? 'clinic.diseases' : 'clinic.service', 'petId' => $petId, 'service' => $service['code']])];
        }
        $rows[] = [$this->view->button('clinic.history', 'clinic.history', $locale, ['petId' => $petId]), $this->view->button('refresh', 'clinic', $locale, ['petId' => $petId])];

        return $this->view->screen(implode("\n", $lines), $rows, $locale, ['action' => 'pet', 'petId' => $petId]);
    }

    /** @param Command $command */
    public function diseases(User $user, string $locale, array $command): TelegramScreen
    {
        $petId = (int) $command['petId'];
        $diseases = $this->clinic->handle($user, $petId, $locale)['diseases'];
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $rows = [];
        foreach (array_slice($diseases, $offset, MenuView::PAGE_SIZE) as $episode) {
            $rows[] = [$this->view->commandButton($episode['name'], ['action' => 'clinic.service', 'petId' => $petId, 'service' => 'treatment', 'episodeId' => $episode['id']])];
        }
        $rows = [...$rows, ...$this->view->offsetNavigation(count($diseases), $offset, ['action' => 'clinic.diseases', 'petId' => $petId], $locale)];

        return $this->view->screen($this->view->translate($diseases === [] ? 'clinic.healthy' : 'clinic.choose_disease', $locale), $rows, $locale, ['action' => 'clinic', 'petId' => $petId]);
    }

    /** @param Command $command */
    public function service(User $user, string $locale, array $command): TelegramScreen
    {
        $petId = (int) $command['petId'];
        $clinic = $this->clinic->handle($user, $petId, $locale);
        $service = collect($clinic['services'])->firstWhere('code', $command['service']);
        if ($service === null) {
            throw new ModelNotFoundException;
        }
        $episode = null;
        if ($service['code'] === 'treatment') {
            $episode = collect($clinic['diseases'])->firstWhere('id', (int) ($command['episodeId'] ?? 0));
            if ($episode === null) {
                throw new ModelNotFoundException;
            }
        }
        $text = $this->view->translate('clinic.'.$service['code'], $locale)
            .($episode === null ? '' : ' · '.Str::limit($episode['name'], 120))
            ."\n".$this->view->translate('price', $locale).': '.$service['price'].' '.$this->view->translate('coins', $locale)
            ."\n".$this->view->translate('clinic.'.$service['code'].'_description', $locale, [
                'health' => $clinic['checkupHealth'], 'days' => $service['code'] === 'checkup' ? $clinic['checkupDays'] : $clinic['vaccinationDays'], 'bonus' => $clinic['vaccinationBonus'],
            ]);
        if ($service['availableAt'] !== null) {
            $text .= "\n".$this->view->translate('clinic.available_at', $locale, ['time' => $this->view->dateTime($service['availableAt'], $user)]);
        }
        $reason = $clinic['reason'] ?? $service['reason'];
        $state = [];
        $rows = [];
        if ($reason === null) {
            $text .= "\n\n".$this->view->translate('clinic.confirmation', $locale);
            $rows[] = [$this->view->button('clinic.confirm', 'clinic.perform', $locale)];
            $state['veterinary'] = ['petId' => $petId, 'service' => $service['code'], 'episodeId' => $episode['id'] ?? null, 'expectedPrice' => $service['price'], 'token' => $clinic['token']];
        } else {
            $text .= "\n\n".$this->view->reason($reason, $locale);
        }

        return $this->view->screen($text, $rows, $locale, ['action' => $episode === null ? 'clinic' : 'clinic.diseases', 'petId' => $petId], $state);
    }

    /** @param State $state */
    public function perform(User $user, string $locale, array $state): TelegramScreen
    {
        $quote = new SavedMenuState($state, 'veterinary');
        $service = VeterinaryService::tryFrom($quote->string('service')) ?? throw new PetUnavailable('telegram.errors.stale');
        $visit = $this->purchase->handle($user, new PurchaseVeterinaryServiceData(
            $quote->integer('petId'), $service, $quote->nullableInteger('episodeId'), $quote->integer('expectedPrice', minimum: 0), $quote->uuid(),
        ));

        return $this->index($user->refresh(), $locale, ['petId' => $visit->pet_id], $this->view->translate('clinic.completed', $locale));
    }

    /** @param Command $command */
    public function history(User $user, string $locale, array $command): TelegramScreen
    {
        $petId = (int) $command['petId'];
        $history = $this->clinic->handle($user, $petId, $locale)['history'];
        $lines = [$this->view->translate($history === [] ? 'clinic.history_empty' : 'clinic.history', $locale)];
        foreach ($history as $visit) {
            $lines[] = $this->view->dateTime($visit['performedAt'], $user).' · '.$this->view->translate('clinic.'.$visit['service'], $locale)
                .($visit['diseaseName'] === null ? '' : ' · '.Str::limit($visit['diseaseName'], 100)).' · '.$visit['price'].' '.$this->view->translate('coins', $locale);
        }

        return $this->view->screen(implode("\n\n", $lines), [], $locale, ['action' => 'clinic', 'petId' => $petId]);
    }
}
