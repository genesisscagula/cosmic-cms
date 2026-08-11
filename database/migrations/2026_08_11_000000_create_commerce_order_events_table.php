<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('actor_type', 30)->default('system');
            $table->string('source', 50)->default('system');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['commerce_order_id', 'created_at'], 'commerce_order_events_order_created_idx');
            $table->index(['website_id', 'event_type', 'created_at'], 'commerce_order_events_site_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_order_events');
    }
};
