<?php

namespace App\Modules\Pets\Queries;

use App\Models\BreedingLitter;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\DTO\PuppyCardData;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/** @phpstan-import-type Card from PuppyCardData */
final class GetPlayerPuppies
{
    /** @return array{puppies: list<Card>, pregnancies: list<array{id: int, fatherName: string, motherName: string, dueAt: string}>, pagination: array{nextCursor: string|null, previousCursor: string|null}} */
    public function handle(User $user, string $locale, ?int $parentId = null, int $perPage = 12): array
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Page size must be between 1 and 100.');
        }
        $query = Puppy::query()->where('user_id', $user->id)->whereIn('status', ['pending', 'listed'])
            ->where('expires_at', '>', now())->with(['dog', 'user:id,name,username']);
        $pregnancies = BreedingLitter::query()->whereNull('delivered_at')->whereHas('puppies',
            fn (Builder $puppies): Builder => $puppies->where('user_id', $user->id)->where('status', 'unborn')
        )->with(['father:id,name', 'mother:id,name']);
        if ($parentId !== null) {
            $query->where(fn (Builder $puppies): Builder => $puppies->where('father_id', $parentId)->orWhere('mother_id', $parentId));
            $pregnancies->where(fn (Builder $litters): Builder => $litters->where('father_id', $parentId)->orWhere('mother_id', $parentId));
        }
        $page = $query->orderBy('id')->cursorPaginate($perPage);

        return [
            'puppies' => array_values($page->getCollection()->map(fn (Puppy $puppy): array => PuppyCardData::fromModel($puppy, $locale))->all()),
            'pregnancies' => array_values($pregnancies->orderBy('born_at')->orderBy('id')->get()->map(fn (BreedingLitter $litter): array => [
                'id' => $litter->id, 'fatherName' => $litter->father->name, 'motherName' => $litter->mother->name,
                'dueAt' => $litter->born_at->toIso8601String(),
            ])->all()),
            'pagination' => ['nextCursor' => $page->nextCursor()?->encode(), 'previousCursor' => $page->previousCursor()?->encode()],
        ];
    }
}
