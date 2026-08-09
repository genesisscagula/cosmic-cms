<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->timestamp('welcome_email_sent_at')->nullable()->after('email_captured_at');
            $table->string('welcome_email_address')->nullable()->after('welcome_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropColumn(['welcome_email_sent_at', 'welcome_email_address']);
        });
    }
};
