<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_user', function (Blueprint $table) {
            $table->string('role')->default('website_editor')->after('assigned_by_user_id');
            $table->index(['website_id', 'role']);
        });

        DB::table('website_user')->orderBy('id')->get()->each(function ($assignment) {
            $workspaceId = DB::table('websites')->where('id', $assignment->website_id)->value('workspace_id');
            $workspaceRole = $workspaceId
                ? DB::table('workspace_user')->where('workspace_id', $workspaceId)->where('user_id', $assignment->user_id)->value('role')
                : null;
            DB::table('website_user')->where('id', $assignment->id)->update([
                'role' => $workspaceRole === 'admin' ? 'website_admin' : 'website_editor',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('website_user', function (Blueprint $table) {
            $table->dropIndex(['website_id', 'role']);
            $table->dropColumn('role');
        });
    }
};
