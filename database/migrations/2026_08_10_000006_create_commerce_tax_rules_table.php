<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_tax_rules')) {
            Schema::create('commerce_tax_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->char('country_code', 2)->nullable(); // null = rest of world fallback
                $table->string('region_code', 120)->nullable(); // optional state/province/region code or name
                $table->string('tax_class', 120)->nullable(); // null = standard class
                $table->unsignedInteger('rate_basis_points')->default(0); // 750 = 7.50%
                $table->boolean('tax_shipping')->default(false);
                $table->boolean('is_enabled')->default(true);
                $table->unsignedInteger('priority')->default(100);
                $table->timestamps();

                $table->index(['website_id', 'is_enabled', 'priority']);
                $table->index(['website_id', 'country_code', 'region_code']);
                $table->index(['website_id', 'tax_class']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_tax_rules');
    }
};
