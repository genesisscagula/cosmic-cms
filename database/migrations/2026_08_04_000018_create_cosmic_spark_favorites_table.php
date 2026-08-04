<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cosmic_spark_favorites')) return;
        Schema::create('cosmic_spark_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('spark_key', 120);
            $table->timestamps();
            $table->unique(['user_id', 'spark_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cosmic_spark_favorites');
    }
};
