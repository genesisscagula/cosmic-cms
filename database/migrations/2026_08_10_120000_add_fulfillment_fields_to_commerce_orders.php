<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('metadata');
            $table->string('tracking_carrier', 120)->nullable()->after('admin_note');
            $table->string('tracking_number', 180)->nullable()->after('tracking_carrier');
            $table->timestamp('fulfilled_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            $table->dropColumn(['admin_note', 'tracking_carrier', 'tracking_number', 'fulfilled_at']);
        });
    }
};
