<?php

namespace App\Modules\Pets\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

/** Public operation that settles elapsed lifetimes before gameplay and slot checks. */
final class PetLifecycle
{
    public function __construct(private PetLifecycleSynchronization $synchronization) {}

    /** Lifecycle transitions commit independently of the subsequent gameplay operation. */
    public function synchronizeOwner(User $user, ?CarbonImmutable $at = null): void
    {
        $this->synchronization->synchronizeOwner($user, $at);
    }

    /** Recheck chronology while the caller holds the owner lock in its gameplay transaction. */
    public function assertCanAdvance(User $user, ?CarbonImmutable $at = null): void
    {
        $this->synchronization->assertCanAdvance($user, $at);
    }
}
