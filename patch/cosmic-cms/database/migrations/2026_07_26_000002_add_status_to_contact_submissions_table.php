<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->string('status', 20)->default('unread')->after('fields');
            $table->timestamp('read_at')->nullable()->after('received_at');
            $table->timestamp('archived_at')->nullable()->after('read_at');
            $table->index(['website_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropIndex(['website_id', 'status']);
            $table->dropColumn(['status', 'read_at', 'archived_at']);
        });
    }
};
