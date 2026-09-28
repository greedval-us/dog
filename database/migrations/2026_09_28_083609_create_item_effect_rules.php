<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_effect_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_effect_id')->constrained()->restrictOnDelete();
            $table->decimal('chance_percent', 5, 2)->default(100);
            $table->json('chance_by_quality')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('duration_by_quality')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['item_id', 'status_effect_id']);
        });
        Schema::table('inventory_items', fn (Blueprint $table) => $table->json('effect_rules')->nullable());
        Schema::table('status_effects', function (Blueprint $table): void {
            $table->renameColumn('condition_below', 'condition_threshold');
            $table->string('condition_operator', 8)->default('lt');
        });

        $effects = DB::table('status_effects')->get();
        $categories = DB::table('item_categories')->pluck('code', 'id');
        DB::table('items')->orderBy('id')->chunkById(200, function ($items) use ($effects, $categories): void {
            foreach ($items as $item) {
                foreach ($this->legacyRules($effects->all(), json_decode($item->granted_effects ?? '[]', true), $categories[$item->item_category_id]) as $rule) {
                    DB::table('item_effect_rules')->insert([
                        'item_id' => $item->id, 'status_effect_id' => $rule['effect_id'],
                        'chance_percent' => $rule['chance_percent'], 'chance_by_quality' => json_encode($rule['chance_by_quality']),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });

        foreach (['inventory_items', 'item_purchases'] as $table) {
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $effects, $categories): void {
                $items = DB::table('items')->whereIn('id', $rows->pluck('item_id'))->pluck('item_category_id', 'id');
                foreach ($rows as $row) {
                    $snapshot = $table === 'item_purchases' ? json_decode($row->item_snapshot, true) : [];
                    $codes = $table === 'item_purchases' ? ($snapshot['granted_effects'] ?? []) : json_decode($row->granted_effects ?? '[]', true);
                    $rules = $this->legacyRules($effects->all(), $codes, $categories[$items[$row->item_id]]);
                    $rules = array_values(array_filter($rules, fn (array $rule): bool => $rule['active']));
                    foreach ($rules as &$rule) {
                        unset($rule['effect_id'], $rule['active']);
                    }
                    unset($rule);
                    if ($table === 'item_purchases') {
                        unset($snapshot['granted_effects']);
                        $snapshot['effect_rules'] = $rules;
                        DB::table($table)->where('id', $row->id)->update(['item_snapshot' => json_encode($snapshot)]);
                    } else {
                        DB::table($table)->where('id', $row->id)->update(['effect_rules' => json_encode($rules)]);
                    }
                }
            });
        }

        foreach (['items', 'inventory_items'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('granted_effects'));
        }
        Schema::table('status_effects', fn (Blueprint $table) => $table->dropColumn(['risk_categories', 'risk_chance_by_quality']));
    }

    /** @param array<int, stdClass> $effects
     * @param  list<string>  $codes
     * @return list<array<string, mixed>>
     */
    private function legacyRules(array $effects, array $codes, string $category): array
    {
        $rules = [];
        foreach ($effects as $effect) {
            $guaranteed = $effect->kind === 'buff' && in_array($effect->code, $codes, true);
            $risk = $effect->kind === 'debuff' && in_array($category, json_decode($effect->risk_categories ?? '[]', true), true);
            if ((! $guaranteed && ! $risk) || $effect->duration_seconds <= 0 || $effect->condition_state !== null) {
                continue;
            }
            $rules[] = [
                'effect_id' => $effect->id, 'active' => (bool) $effect->is_active,
                'effect' => [
                    'code' => $effect->code, 'kind' => $effect->kind, 'name' => json_decode($effect->name, true),
                    'description' => json_decode($effect->description, true), 'modifiers' => json_decode($effect->modifiers, true),
                    'duration_seconds' => $effect->duration_seconds, 'condition_state' => null,
                    'condition_threshold' => null, 'condition_operator' => 'lt',
                ],
                'chance_percent' => $guaranteed ? 100 : 0,
                'chance_by_quality' => $guaranteed ? [] : json_decode($effect->risk_chance_by_quality ?? '[]', true),
                'duration_seconds' => null, 'duration_by_quality' => [],
            ];
        }

        return $rules;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['items', 'inventory_items'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->json('granted_effects')->nullable());
        }
        Schema::table('status_effects', function (Blueprint $table): void {
            $table->renameColumn('condition_threshold', 'condition_below');
            $table->dropColumn('condition_operator');
            $table->json('risk_categories')->nullable();
            $table->json('risk_chance_by_quality')->nullable();
        });
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropColumn('effect_rules'));
        Schema::dropIfExists('item_effect_rules');
    }
};
