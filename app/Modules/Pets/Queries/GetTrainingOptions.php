<?php

namespace App\Modules\Pets\Queries;

use App\Models\Training;
use App\Modules\Pets\DTO\CareOption;

final class GetTrainingOptions
{
    public function __construct(private TrainingOptionMapper $mapper) {}

    /** @return array<string, CareOption> */
    public function handle(string $locale): array
    {
        $options = [];
        foreach (Training::query()->where('is_active', true)->with('statusEffect')->orderBy('id')->get() as $training) {
            $option = $this->mapper->map($training, $locale);
            if ($option !== null) {
                $options['training:'.$training->id] = $option;
            }
        }

        return $options;
    }
}
