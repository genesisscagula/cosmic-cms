<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_sparks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('spark_key', 120);
            $table->timestamps();
            $table->unique(['workspace_id', 'spark_key']);
            $table->index(['shared_by_user_id', 'spark_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_sparks');
    }
};
