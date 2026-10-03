<?php

namespace App\Modules\Pets\Queries;

use App\Models\Puppy;
use App\Modules\Pets\DTO\PuppyCardData;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/** @phpstan-import-type Card from PuppyCardData */
final class GetPuppyMarket
{
    /** @return array{puppies: list<Card>, pagination: array{nextCursor: string|null, previousCursor: string|null}} */
    public function handle(string $locale, string $source = 'players', int $perPage = 12): array
    {
        if (! in_array($source, ['players', 'kennel'], true) || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid puppy market filter.');
        }
        $query = Puppy::query()->with(['dog', 'user:id,name,username'])->where('status', $source === 'kennel' ? 'kennel' : 'listed');
        if ($source === 'players') {
            $query->where('expires_at', '>', now())->whereHas('user', fn (Builder $owner): Builder => $owner->where('status', PlayerStatus::Active));
        }
        $page = $query->orderBy('id')->cursorPaginate($perPage);

        return [
            'puppies' => array_values($page->getCollection()->map(fn (Puppy $puppy): array => PuppyCardData::fromModel($puppy, $locale))->all()),
            'pagination' => ['nextCursor' => $page->nextCursor()?->encode(), 'previousCursor' => $page->previousCursor()?->encode()],
        ];
    }
}
