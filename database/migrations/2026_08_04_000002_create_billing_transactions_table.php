<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20);
            $table->string('type', 24); // initial, renewal, failed
            $table->string('status', 20); // completed, failed
            $table->string('external_id')->nullable();
            $table->string('subscription_id')->nullable()->index();
            $table->unsignedInteger('amount_minor')->default(0);
            $table->char('currency', 3);
            $table->unsignedInteger('credits_granted')->default(0);
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_transactions');
    }
};
