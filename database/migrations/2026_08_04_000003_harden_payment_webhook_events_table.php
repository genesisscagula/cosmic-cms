<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->unsignedInteger('duplicate_count')->default(0)->after('attempts');
            $table->timestamp('occurred_at')->nullable()->after('received_at')->index();
            $table->timestamp('last_attempted_at')->nullable()->after('processing_started_at');
            $table->timestamp('next_retry_at')->nullable()->after('last_attempted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->dropIndex(['occurred_at']);
            $table->dropIndex(['next_retry_at']);
            $table->dropColumn(['duplicate_count', 'occurred_at', 'last_attempted_at', 'next_retry_at']);
        });
    }
};
