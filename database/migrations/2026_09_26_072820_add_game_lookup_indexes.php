<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->index(['user_id', 'retired_at', 'id']);
            $table->dropIndex(['user_id', 'retired_at']);
            $table->index('portrait_asset_id');
            $table->index('background_asset_id');
        });

        foreach ($this->catalogueReferences() as $name => $column) {
            Schema::table($name, fn (Blueprint $table) => $table->index($column));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->catalogueReferences() as $name => $column) {
            Schema::table($name, fn (Blueprint $table) => $table->dropIndex([$column]));
        }

        Schema::table('pets', function (Blueprint $table): void {
            $table->index(['user_id', 'retired_at']);
            $table->dropIndex(['user_id', 'retired_at', 'id']);
            $table->dropIndex(['portrait_asset_id']);
            $table->dropIndex(['background_asset_id']);
        });
    }

    /** @return array<string, string> */
    private function catalogueReferences(): array
    {
        return [
            'game_assets' => 'dog_id',
            'asset_unlocks' => 'game_asset_id',
            'pet_diseases' => 'disease_id',
            'pet_skill' => 'skill_id',
            'character_trait_pet' => 'character_trait_id',
        ];
    }
};
