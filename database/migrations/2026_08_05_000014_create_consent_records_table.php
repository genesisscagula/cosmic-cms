<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 40)->default('visitor');
            $table->string('subject_key', 64)->nullable()->index();
            $table->string('consent_type', 50);
            $table->string('document_version', 40);
            $table->boolean('granted');
            $table->json('metadata')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['consent_type', 'document_version', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
