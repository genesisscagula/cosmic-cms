<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('product_type', 20);
            $table->string('product_key', 40);
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('credits')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('external_checkout_id')->nullable()->index();
            $table->string('external_payment_id')->nullable()->index();
            $table->string('external_subscription_id')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('plan_key', 32)->nullable()->after('credits');
            $table->string('plan_status', 24)->nullable()->after('plan_key');
            $table->string('plan_provider', 20)->nullable()->after('plan_status');
            $table->timestamp('plan_renews_at')->nullable()->after('plan_provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['plan_key', 'plan_status', 'plan_provider', 'plan_renews_at']));
    }
};
