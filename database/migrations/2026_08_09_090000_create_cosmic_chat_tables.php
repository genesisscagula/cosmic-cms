<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cosmic_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('access_token_hash', 64);
            $table->string('status', 30)->default('open')->index();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable()->index();
            $table->string('started_page', 500)->nullable();
            $table->string('last_page', 500)->nullable();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('cosmic_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('cosmic_chat_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->string('model', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cosmic_chat_messages');
        Schema::dropIfExists('cosmic_chat_conversations');
    }
};
