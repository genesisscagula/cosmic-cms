<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cosmic_chat_conversations', function (Blueprint $table) {
            $table->boolean('ai_paused')->default(false)->after('lead_status')->index();
            $table->timestamp('taken_over_at')->nullable()->after('ai_paused');
            $table->timestamp('lead_captured_at')->nullable()->after('taken_over_at');
        });
    }

    public function down(): void
    {
        Schema::table('cosmic_chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['ai_paused', 'taken_over_at', 'lead_captured_at']);
        });
    }
};
