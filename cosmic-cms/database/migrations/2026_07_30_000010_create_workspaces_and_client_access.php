<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('client');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->default('customer')->after('password');
        });

        Schema::table('websites', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        $platformOwnerEmail = strtolower((string) env('COSMIC_PLATFORM_OWNER_EMAIL', 'genesisscagula@gmail.com'));
        $users = DB::table('users')->orderBy('id')->get();

        foreach ($users as $user) {
            $isPlatformOwner = strtolower((string) $user->email) === $platformOwnerEmail;
            $workspaceName = $isPlatformOwner
                ? (string) env('COSMIC_AGENCY_WORKSPACE_NAME', 'CosmicReact')
                : $user->name.' Workspace';

            $workspaceId = DB::table('workspaces')->insertGetId([
                'owner_user_id' => $user->id,
                'name' => $workspaceName,
                'slug' => Str::slug($workspaceName).'-'.$user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('workspace_user')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('websites')->where('user_id', $user->id)->update(['workspace_id' => $workspaceId]);
            DB::table('users')->where('id', $user->id)->update([
                'account_type' => $isPlatformOwner ? 'platform_owner' : 'customer',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });

        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
