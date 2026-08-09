<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cosmic_chat_conversations', function (Blueprint $table) {
            $table->timestamp('admin_read_at')->nullable()->after('last_message_at')->index();
            $table->string('lead_status', 30)->default('new')->after('status')->index();
            $table->timestamp('archived_at')->nullable()->after('admin_read_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('cosmic_chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['admin_read_at', 'lead_status', 'archived_at']);
        });
    }
};
