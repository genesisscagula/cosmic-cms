<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Kinsa tag-iya nga client
            $table->string('name');
            $table->string('domain')->nullable(); // Live site URL sa client
            $table->string('api_token')->unique(); // Ang token para sa iyang live site API
            $table->json('theme_settings')->nullable(); // Dire isulod sa AI ang color palettes (Primary, Secondary)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
