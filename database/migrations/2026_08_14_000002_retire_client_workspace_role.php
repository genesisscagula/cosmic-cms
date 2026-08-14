<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workspace_user')) {
            DB::table('workspace_user')->where('role', 'client')->update(['role' => 'editor']);
        }

        if (Schema::hasTable('workspace_invitations')) {
            DB::table('workspace_invitations')->where('role', 'client')->update(['role' => 'editor']);
        }

        if (Schema::hasTable('website_user') && Schema::hasColumn('website_user', 'role')) {
            DB::table('website_user')->whereNotIn('role', ['website_admin', 'website_editor'])->update(['role' => 'website_editor']);
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'account_type')) {
            $clientUserIds = DB::table('workspace_user')->whereIn('role', ['admin', 'editor'])->pluck('user_id');
            DB::table('users')->whereIn('id', $clientUserIds)->where('account_type', 'client')->update(['account_type' => 'customer']);
        }
    }

    public function down(): void
    {
        // Client was intentionally retired; role restoration is ambiguous and unsafe.
    }
};
