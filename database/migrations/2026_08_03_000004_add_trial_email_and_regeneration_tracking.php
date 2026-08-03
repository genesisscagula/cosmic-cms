<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->timestamp('email_captured_at')->nullable()->after('email');
            $table->timestamp('last_saved_at')->nullable()->after('email_captured_at');
        });

        Schema::create('trial_regenerations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trial_generation_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->text('prompt');
            $table->timestamps();
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_regenerations');
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropColumn(['email_captured_at', 'last_saved_at']);
        });
    }
};
