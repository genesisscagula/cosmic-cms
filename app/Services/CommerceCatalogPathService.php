<?php

namespace App\Services;

use App\Models\CommerceProduct;
use App\Models\CommerceProductCategory;

class CommerceCatalogPathService
{
    public function shop(): string
    {
        return '/shop';
    }

    public function category(CommerceProductCategory|string $category): string
    {
        $slug = $category instanceof CommerceProductCategory ? $category->slug : $category;

        return '/shop/category/'.rawurlencode(trim($slug, '/'));
    }

    public function product(CommerceProduct|string $product): string
    {
        $slug = $product instanceof CommerceProduct ? $product->slug : $product;

        return '/product/'.rawurlencode(trim($slug, '/'));
    }
}
