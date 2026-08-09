<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->unsignedSmallInteger('welcome_email_attempts')->default(0)->after('welcome_email_address');
            $table->timestamp('welcome_email_last_attempt_at')->nullable()->after('welcome_email_attempts');
            $table->text('welcome_email_last_error')->nullable()->after('welcome_email_last_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropColumn([
                'welcome_email_attempts',
                'welcome_email_last_attempt_at',
                'welcome_email_last_error',
            ]);
        });
    }
};
