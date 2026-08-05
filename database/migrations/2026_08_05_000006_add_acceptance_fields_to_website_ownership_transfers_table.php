<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('website_ownership_transfers', function (Blueprint $table) {
            $table->string('token', 80)->nullable()->unique()->after('recipient_email');
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('expires_at');
            $table->timestamp('cancelled_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('website_ownership_transfers', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'expires_at', 'accepted_at', 'cancelled_at']);
        });
    }
};
