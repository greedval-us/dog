<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->timestamp('breeding_available_at')->nullable();
        });

        Schema::create('breeding_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->unique()->constrained('pets')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'id']);
            $table->index(['user_id', 'id']);
        });

        Schema::create('breeding_partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->unique()->constrained('pets')->restrictOnDelete();
            $table->string('code')->unique();
            $table->unsignedInteger('price')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('breeding_litters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_token')->unique();
            $table->foreignId('initiator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('own_pet_id')->constrained('pets')->restrictOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('breeding_listings')->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('breeding_partners')->restrictOnDelete();
            $table->foreignId('father_id')->constrained('pets')->restrictOnDelete();
            $table->foreignId('mother_id')->constrained('pets')->restrictOnDelete();
            $table->unsignedInteger('price');
            $table->jsonb('snapshots');
            $table->timestamp('born_at');
            $table->timestamp('expires_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['initiator_id', 'id']);
            $table->index(['delivered_at', 'born_at', 'id']);
        });

        Schema::create('puppies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('litter_id')->constrained('breeding_litters')->restrictOnDelete();
            $table->foreignId('dog_id')->constrained('dog')->restrictOnDelete();
            $table->foreignId('father_id')->constrained('pets')->restrictOnDelete();
            $table->foreignId('mother_id')->constrained('pets')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pet_id')->nullable()->unique()->constrained('pets')->restrictOnDelete();
            $table->string('status', 16)->default('unborn');
            $table->string('name', 64);
            $table->enum('sex', ['male', 'female']);
            $table->string('coat_color', 64);
            $table->unsignedInteger('generation');

            foreach (['endurance', 'speed', 'strength', 'agility', 'obedience', 'intelligence'] as $stat) {
                $table->unsignedInteger($stat.'_potential');
            }

            $table->unsignedInteger('sale_price')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
            $table->index(['litter_id', 'status']);
            $table->index(['user_id', 'status', 'id']);
            $table->index(['status', 'expires_at', 'id']);
            $table->index('father_id');
            $table->index('mother_id');
        });

        Schema::create('puppy_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('puppy_id')->constrained('puppies')->restrictOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained('pets')->restrictOnDelete();
            $table->uuid('operation_token')->unique();
            $table->string('kind', 16);
            $table->unsignedInteger('price');
            $table->string('name', 64);
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'id']);
            $table->index('puppy_id');
        });

        Schema::create('coat_inheritance_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dog_id')->constrained('dog')->restrictOnDelete();
            $table->string('first_color', 64);
            $table->string('second_color', 64);
            $table->string('offspring_color', 64);
            $table->unsignedInteger('weight');
            $table->timestamps();
            $table->unique(['dog_id', 'first_color', 'second_color', 'offspring_color'], 'coat_inheritance_combination_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coat_inheritance_rules');
        Schema::dropIfExists('puppy_placements');
        Schema::dropIfExists('puppies');
        Schema::dropIfExists('breeding_litters');
        Schema::dropIfExists('breeding_partners');
        Schema::dropIfExists('breeding_listings');
        Schema::table('pets', fn (Blueprint $table) => $table->dropColumn('breeding_available_at'));
    }
};
