<?php

namespace App\Console\Commands;

use App\Modules\Inventory\Actions\RestockAmmunition as Restock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shop:restock-ammunition')]
#[Description('Restock due ammunition deliveries without accumulating missed supplies')]
class RestockAmmunition extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Restock $restock): int
    {
        $this->info('Restocked '.$restock->handle().' ammunition offers.');

        return self::SUCCESS;
    }
}
