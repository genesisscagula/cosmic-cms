<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trial_generations', 'guest_credits')) {
            Schema::table('trial_generations', function (Blueprint $table) {
                $table->unsignedInteger('guest_credits')->default(500)->after('preview_theme');
            });
        }

        if (! Schema::hasTable('trial_credit_transactions')) {
            Schema::create('trial_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trial_generation_id')->constrained('trial_generations')->cascadeOnDelete();
            $table->string('action', 80);
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->text('metadata')->nullable();
            $table->timestamps();
                $table->index(['trial_generation_id', 'created_at']);
            });
        }

        DB::table('trial_generations')
            ->whereNull('guest_credits')
            ->update(['guest_credits' => 500]);
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_credit_transactions');
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropColumn('guest_credits');
        });
    }
};
