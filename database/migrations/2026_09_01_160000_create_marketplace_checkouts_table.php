<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_checkouts')) {
            return;
        }

        Schema::create('marketplace_checkouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('marketplace_template_id')->constrained('marketplace_templates')->cascadeOnDelete();
            $table->foreignId('pending_onboarding_id')->nullable()->constrained('pending_onboardings')->nullOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained('payment_orders')->nullOnDelete();
            $table->string('selected_plan', 24)->index();
            $table->string('status', 32)->default('selected')->index();
            $table->unsignedInteger('amount_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('source', 40)->default('marketplace');
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'marketplace_checkout_user_status_idx');
            $table->index(['marketplace_template_id', 'status'], 'marketplace_checkout_template_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_checkouts');
    }
};
