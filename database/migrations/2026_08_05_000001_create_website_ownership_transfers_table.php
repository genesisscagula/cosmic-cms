<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_ownership_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->foreignId('initiated_by_user_id')->constrained('users');
            $table->foreignId('from_workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignId('to_workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('status', 30)->default('completed');
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['website_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_ownership_transfers');
    }
};
