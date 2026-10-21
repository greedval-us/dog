<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $litters = DB::table('breeding_litters')->whereColumn('own_pet_id', 'mother_id')->whereNotNull('initiator_id')
            ->whereNull('snapshots->breeding_origin')->select(['id', 'initiator_id', 'snapshots'])->lazyById(200);

        foreach ($litters as $litter) {
            $snapshots = json_decode($litter->snapshots, true, 512, JSON_THROW_ON_ERROR);
            $key = 'player:'.$litter->initiator_id;
            $snapshots['breeding_origin'] = ['breeder_key' => $key, 'kennel_key' => $key, 'kennel_name' => 'DogLive #'.$litter->initiator_id];
            DB::table('breeding_litters')->where('id', $litter->id)->whereNull('snapshots->breeding_origin')->update([
                'snapshots' => json_encode($snapshots, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * Historical breeder identities must survive account deletion and cannot be reconstructed afterward.
     */
    public function down(): void {}
};
