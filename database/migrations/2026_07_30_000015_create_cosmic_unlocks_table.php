<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cosmic_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('unlock_type', 30);
            $table->string('unlock_key', 120);
            $table->unsignedInteger('credits_paid')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'unlock_type', 'unlock_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cosmic_unlocks');
    }
};
