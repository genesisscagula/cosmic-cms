<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->string('lead_status', 24)->default('new')->after('status');
            $table->string('source', 40)->default('contact_form')->after('lead_status');
            $table->text('notes')->nullable()->after('source');
            $table->timestamp('qualified_at')->nullable()->after('notes');
            $table->timestamp('converted_at')->nullable()->after('qualified_at');
            $table->index(['website_id', 'lead_status', 'received_at'], 'contact_submissions_lead_dashboard_index');
        });
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropIndex('contact_submissions_lead_dashboard_index');
            $table->dropColumn(['lead_status', 'source', 'notes', 'qualified_at', 'converted_at']);
        });
    }
};
