<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_provisioning_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_provisioning_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 20)->default('info');
            $table->string('event', 80);
            $table->string('stage', 80)->nullable();
            $table->text('message')->nullable();
            $table->unsignedInteger('attempt')->default(0);
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_provisioning_id', 'occurred_at'], 'provisioning_log_timeline');
            $table->index(['event', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_provisioning_logs');
    }
};
