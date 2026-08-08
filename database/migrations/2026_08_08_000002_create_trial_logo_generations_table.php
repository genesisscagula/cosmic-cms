<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trial_logo_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trial_generation_id')->constrained('trial_generations')->cascadeOnDelete();
            $table->string('company_name', 80);
            $table->string('action', 24)->default('generate');
            $table->timestamps();
            $table->index(['trial_generation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_logo_generations');
    }
};
