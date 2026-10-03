<?php

namespace App\Console\Commands;

use App\Modules\Pets\Services\PuppyLifecycle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('puppies:transfer-expired')]
#[Description('Deliver due litters and transfer expired or orphaned puppies to the kennel')]
class TransferExpiredPuppies extends Command
{
    public function handle(PuppyLifecycle $lifecycle): int
    {
        $settled = $lifecycle->synchronize();
        $this->info('Settled '.$settled.' puppies.');

        return self::SUCCESS;
    }
}
