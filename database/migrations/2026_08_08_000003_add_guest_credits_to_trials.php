<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->unsignedInteger('guest_credits')->default(500)->after('preview_theme');
        });

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

    public function down(): void
    {
        Schema::dropIfExists('trial_credit_transactions');
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropColumn('guest_credits');
        });
    }
};
