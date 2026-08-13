<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function foreignKeyExists(string $table, string $column): bool
    {
        return ! empty(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [$table, $column]
        ));
    }

    public function up(): void
    {
        if (! Schema::hasTable('commerce_inventory_adjustments')) {
            Schema::create('commerce_inventory_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('website_id');
                $table->unsignedBigInteger('commerce_product_id');
                $table->unsignedBigInteger('commerce_product_variant_id')->nullable();
                $table->unsignedBigInteger('commerce_order_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('reason', 40); // manual | sale | restock | correction | import
                $table->integer('quantity_delta');
                $table->unsignedInteger('quantity_before')->nullable();
                $table->unsignedInteger('quantity_after')->nullable();
                $table->string('note', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['website_id', 'created_at']);
                $table->index(['commerce_product_id', 'commerce_product_variant_id'], 'commerce_inv_adj_product_variant_idx');
                $table->index(['commerce_order_id', 'reason'], 'commerce_inv_adj_order_reason_idx');
            });
        }

        // Repair-safe: a previous failed migration may have created the table
        // before MariaDB rejected the auto-generated FK identifier (>64 chars).
        $foreignKeys = [
            ['website_id', 'websites', 'id', 'commerce_inv_adj_website_fk', 'cascade'],
            ['commerce_product_id', 'commerce_products', 'id', 'commerce_inv_adj_product_fk', 'cascade'],
            ['commerce_product_variant_id', 'commerce_product_variants', 'id', 'commerce_inv_adj_variant_fk', 'cascade'],
            ['commerce_order_id', 'commerce_orders', 'id', 'commerce_inv_adj_order_fk', 'null'],
            ['user_id', 'users', 'id', 'commerce_inv_adj_user_fk', 'null'],
        ];

        foreach ($foreignKeys as [$column, $referencesTable, $referencesColumn, $name, $onDelete]) {
            if (! $this->foreignKeyExists('commerce_inventory_adjustments', $column)) {
                Schema::table('commerce_inventory_adjustments', function (Blueprint $table) use ($column, $referencesTable, $referencesColumn, $name, $onDelete) {
                    $foreign = $table->foreign($column, $name)->references($referencesColumn)->on($referencesTable);

                    if ($onDelete === 'cascade') {
                        $foreign->cascadeOnDelete();
                    } else {
                        $foreign->nullOnDelete();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_inventory_adjustments');
    }
};
