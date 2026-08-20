<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('website_type', 20)->default('builder')->after('business_description')->index();
            $table->json('design_system')->nullable()->after('website_type');
        });

        Schema::create('custom_sparks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->string('name');
            $table->json('schema')->nullable();
            $table->json('block');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['website_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_sparks');
        Schema::table('websites', function (Blueprint $table) {
            $table->dropIndex(['website_type']);
            $table->dropColumn(['website_type', 'design_system']);
        });
    }
};
