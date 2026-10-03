<?php

namespace App\Modules\Pets\Queries;

use App\Models\Training;
use App\Modules\Pets\DTO\CareOption;

final class GetExecutableTraining
{
    public function __construct(private TrainingOptionMapper $mapper) {}

    public function handle(string $variant): ?CareOption
    {
        if (preg_match('/\Atraining:([1-9][0-9]*)\z/', $variant, $matches) !== 1) {
            return null;
        }

        $id = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return null;
        }

        $training = Training::query()->where('is_active', true)->with('statusEffect')->find($id);

        return $training === null ? null : $this->mapper->map($training, 'en');
    }
}
