<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moonshine_user_roles', function (Blueprint $table): void {
            $table->string('code', 32)->nullable()->unique();
        });

        DB::table('moonshine_user_roles')->where('id', 1)->update(['code' => 'administrator']);

        foreach (['analyst' => 'Analyst', 'moderator' => 'Moderator'] as $code => $name) {
            DB::table('moonshine_user_roles')->insert([
                'code' => $code, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('moonshine_users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('action', 32);
            $table->text('reason')->nullable();
            $table->json('changes');
            $table->timestamp('created_at');
            $table->index(['target_type', 'target_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::table('moonshine_user_roles', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
