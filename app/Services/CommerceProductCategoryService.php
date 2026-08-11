<?php

namespace App\Services;

use App\Models\CommerceProduct;
use App\Models\CommerceProductCategory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommerceProductCategoryService
{
    /**
     * Sync category assignments while enforcing website isolation and one primary category.
     *
     * @param array<int, array{category_id:int, is_primary?:bool, sort_order?:int}> $assignments
     */
    public function sync(CommerceProduct $product, array $assignments): void
    {
        $categoryIds = collect($assignments)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->count() !== count($assignments)) {
            throw new InvalidArgumentException('Category assignments must contain unique valid category IDs.');
        }

        $validCount = CommerceProductCategory::query()
            ->where('website_id', $product->website_id)
            ->whereIn('id', $categoryIds)
            ->count();

        if ($validCount !== $categoryIds->count()) {
            throw new InvalidArgumentException('A product can only be assigned to categories from the same website.');
        }

        $primaryCount = collect($assignments)->filter(fn (array $assignment): bool => (bool) ($assignment['is_primary'] ?? false))->count();
        if ($primaryCount > 1) {
            throw new InvalidArgumentException('A product can only have one primary category.');
        }

        $pivot = [];
        foreach ($assignments as $index => $assignment) {
            $pivot[(int) $assignment['category_id']] = [
                'is_primary' => (bool) ($assignment['is_primary'] ?? false),
                'sort_order' => max(0, (int) ($assignment['sort_order'] ?? $index)),
            ];
        }

        DB::transaction(function () use ($product, $pivot): void {
            $product->categories()->sync($pivot);
        });
    }

    public function setPrimary(CommerceProduct $product, CommerceProductCategory $category): void
    {
        if ((int) $product->website_id !== (int) $category->website_id) {
            throw new InvalidArgumentException('A product can only use a primary category from the same website.');
        }

        if (! $product->categories()->whereKey($category->id)->exists()) {
            throw new InvalidArgumentException('The primary category must already be assigned to the product.');
        }

        DB::transaction(function () use ($product, $category): void {
            DB::table('commerce_product_category_assignments')
                ->where('product_id', $product->id)
                ->update(['is_primary' => false]);

            DB::table('commerce_product_category_assignments')
                ->where('product_id', $product->id)
                ->where('category_id', $category->id)
                ->update(['is_primary' => true]);
        });
    }
}
