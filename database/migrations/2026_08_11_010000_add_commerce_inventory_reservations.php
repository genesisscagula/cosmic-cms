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
        if (! Schema::hasTable('commerce_inventory_reservations')) {
            Schema::create('commerce_inventory_reservations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('website_id');
                $table->unsignedBigInteger('commerce_order_id');
                $table->unsignedBigInteger('commerce_order_item_id');
                $table->unsignedBigInteger('commerce_product_id')->nullable();
                $table->unsignedBigInteger('commerce_product_variant_id')->nullable();
                $table->unsignedInteger('quantity');
                $table->string('status', 20)->default('active'); // active | consumed | released
                $table->timestamp('reserved_at');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->unique('commerce_order_item_id', 'commerce_inventory_reservations_item_unique');
                $table->index(['commerce_product_id', 'status', 'expires_at'], 'commerce_inventory_reservations_product_index');
                $table->index(['commerce_product_variant_id', 'status', 'expires_at'], 'commerce_inventory_reservations_variant_index');
                $table->index(['commerce_order_id', 'status'], 'commerce_inv_res_order_status_idx');
            });
        }

        $foreignKeys = [
            ['website_id', 'websites', 'id', 'commerce_inv_res_website_fk', 'cascade'],
            ['commerce_order_id', 'commerce_orders', 'id', 'commerce_inv_res_order_fk', 'cascade'],
            ['commerce_order_item_id', 'commerce_order_items', 'id', 'commerce_inv_res_item_fk', 'cascade'],
            ['commerce_product_id', 'commerce_products', 'id', 'commerce_inv_res_product_fk', 'null'],
            ['commerce_product_variant_id', 'commerce_product_variants', 'id', 'commerce_inv_res_variant_fk', 'null'],
        ];

        foreach ($foreignKeys as [$column, $referencesTable, $referencesColumn, $name, $onDelete]) {
            if (! $this->foreignKeyExists('commerce_inventory_reservations', $column)) {
                Schema::table('commerce_inventory_reservations', function (Blueprint $table) use ($column, $referencesTable, $referencesColumn, $name, $onDelete) {
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
        Schema::dropIfExists('commerce_inventory_reservations');
    }
};
