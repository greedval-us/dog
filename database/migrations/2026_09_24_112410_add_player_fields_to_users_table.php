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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 32)->nullable();
            $table->string('avatar_path')->nullable();
            $table->text('bio')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('coins')->default(0);
            $table->unsignedBigInteger('gems')->default(0);
            $table->unsignedBigInteger('experience')->default(0);
            $table->string('locale', 10)->default('ru');
            $table->string('timezone', 64)->default('UTC');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('tutorial_completed_at')->nullable();
        });

        DB::table('users')->select('id')->orderBy('id')->lazyById()->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'username' => 'player_'.$user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 32)->nullable(false)->change();
            $table->unique('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username', 'avatar_path', 'bio', 'status', 'coins', 'gems',
                'experience', 'locale', 'timezone', 'last_login_at',
                'last_seen_at', 'tutorial_completed_at',
            ]);
        });
    }
};
