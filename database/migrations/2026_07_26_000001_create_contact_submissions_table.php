<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('phone', 80)->nullable();
            $table->text('message');
            $table->json('fields')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['website_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
