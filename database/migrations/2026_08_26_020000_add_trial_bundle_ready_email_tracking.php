<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('trial_generations', function (Blueprint $table): void {
            $table->timestamp('bundle_ready_email_sent_at')->nullable()->after('welcome_email_last_error');
            $table->text('bundle_ready_email_last_error')->nullable()->after('bundle_ready_email_sent_at');
        });
    }
    public function down(): void {
        Schema::table('trial_generations', function (Blueprint $table): void {
            $table->dropColumn(['bundle_ready_email_sent_at','bundle_ready_email_last_error']);
        });
    }
};
