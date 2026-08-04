<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('business_name')->nullable()->after('name');
            $table->string('phone', 40)->nullable()->after('location');
            $table->string('industry', 120)->nullable()->after('phone');
            $table->string('timezone', 80)->default('Asia/Manila')->after('industry');
            $table->string('locale', 20)->default('en')->after('timezone');
            $table->json('profile_settings')->nullable()->after('locale');
            $table->timestamp('profile_completed_at')->nullable()->after('profile_settings');
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->json('settings')->nullable()->after('slug');
        });

        Schema::table('websites', function (Blueprint $table): void {
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
            $table->string('timezone', 80)->default('Asia/Manila')->after('contact_phone');
            $table->string('locale', 20)->default('en')->after('timezone');
            $table->json('settings')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('websites', fn (Blueprint $table) => $table->dropColumn(['contact_phone', 'timezone', 'locale', 'settings']));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('settings'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'business_name', 'phone', 'industry', 'timezone', 'locale', 'profile_settings', 'profile_completed_at',
        ]));
    }
};
