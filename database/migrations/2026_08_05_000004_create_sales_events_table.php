<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('type', 32)->default('sale');
            $table->string('status', 24)->default('completed');
            $table->string('source', 48)->default('manual');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('product')->nullable();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'occurred_at']);
            $table->index(['website_id', 'status']);
            $table->index(['source', 'occurred_at']);
            $table->unique(['website_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_events');
    }
};
