<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_status', 32)->default('complete')->after('account_type');
        });

        Schema::create('pending_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('trial_generation_id')->nullable()->constrained('trial_generations')->nullOnDelete();
            $table->string('selected_plan', 32);
            $table->string('website_name');
            $table->string('website_slug', 80)->unique();
            $table->string('industry', 120);
            $table->text('business_description');
            $table->string('location', 255);
            $table->string('status', 32)->default('pending_payment')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_onboardings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_status');
        });
    }
};
