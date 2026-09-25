<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pets')->whereNotNull('traits')->eachById(function (object $pet): void {
            $this->traitCodes($pet->traits);
        });

        foreach (['diseases', 'skills', 'character_traits'] as $catalogue) {
            Schema::create($catalogue, function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->json('name');
                $table->json('description')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('pet_diseases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disease_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['pet_id', 'ended_at']);
        });

        Schema::create('pet_skill', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('experience')->default(0);
            $table->timestamps();
            $table->unique(['pet_id', 'skill_id']);
        });

        Schema::create('character_trait_pet', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_trait_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['pet_id', 'character_trait_id']);
        });

        DB::table('pets')->whereNotNull('traits')->eachById(function (object $pet): void {
            foreach ($this->traitCodes($pet->traits) as $code) {
                $traitId = DB::table('character_traits')->where('code', $code)->value('id');

                if ($traitId === null) {
                    $traitId = DB::table('character_traits')->insertGetId([
                        'code' => $code,
                        'name' => json_encode(['ru' => $code, 'en' => $code], JSON_THROW_ON_ERROR),
                        'created_at' => $pet->created_at,
                        'updated_at' => $pet->updated_at,
                    ]);
                }

                DB::table('character_trait_pet')->insert([
                    'pet_id' => $pet->id,
                    'character_trait_id' => $traitId,
                    'created_at' => $pet->created_at,
                    'updated_at' => $pet->updated_at,
                ]);
            }
        });

        Schema::table('pets', function (Blueprint $table): void {
            $table->dropColumn('traits');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->json('traits')->nullable();
        });

        DB::table('pets')->eachById(function (object $pet): void {
            $codes = DB::table('character_trait_pet')
                ->join('character_traits', 'character_traits.id', '=', 'character_trait_pet.character_trait_id')
                ->where('pet_id', $pet->id)->orderBy('character_trait_pet.id')->pluck('code')->all();

            DB::table('pets')->where('id', $pet->id)->update([
                'traits' => $codes === [] ? null : json_encode($codes, JSON_THROW_ON_ERROR),
            ]);
        });

        foreach (['character_trait_pet', 'pet_skill', 'pet_diseases', 'character_traits', 'skills', 'diseases'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /** @return list<string> */
    private function traitCodes(string $json): array
    {
        $codes = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if ($codes === null) {
            return [];
        }

        if (! is_array($codes) || ! array_is_list($codes)) {
            throw new RuntimeException('Pet traits must contain a JSON list of codes.');
        }

        foreach ($codes as $code) {
            if (! is_string($code) || $code === '' || mb_strlen($code) > 64) {
                throw new RuntimeException('Pet trait codes must be nonempty strings up to 64 characters.');
            }
        }

        return array_values(array_unique($codes));
    }
};
