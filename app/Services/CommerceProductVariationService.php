<?php

namespace App\Services;

use App\Models\CommerceProduct;
use App\Models\CommerceProductOption;
use App\Models\CommerceProductOptionValue;
use App\Models\CommerceProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CommerceProductVariationService
{
    public const MAX_AUTOMATIC_COMBINATIONS = 500;

    /**
     * Build all active option-value combinations for a variable product.
     * Existing matching variants are preserved so merchant-entered SKU/price/stock
     * overrides are never destroyed by a regenerate action. Stale combinations are
     * disabled instead of deleted to protect historical references.
     *
     * @return Collection<int, CommerceProductVariant>
     */
    public function syncAutomaticCombinations(CommerceProduct $product): Collection
    {
        if (! $product->isVariable()) {
            throw new InvalidArgumentException('Automatic combinations are only available for variable products.');
        }

        $options = $product->options()
            ->with(['activeValues'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($options->isEmpty()) {
            $product->variants()->update(['is_enabled' => false, 'is_default' => false]);

            return collect();
        }

        foreach ($options as $option) {
            if ($option->activeValues->isEmpty()) {
                $product->variants()->update(['is_enabled' => false, 'is_default' => false]);

                return collect();
            }
        }

        $combinationCount = $options->reduce(
            fn (int $carry, CommerceProductOption $option): int => $carry * $option->activeValues->count(),
            1
        );

        if ($combinationCount > self::MAX_AUTOMATIC_COMBINATIONS) {
            throw new RuntimeException(sprintf(
                'This product would create %d variants. The automatic combination limit is %d.',
                $combinationCount,
                self::MAX_AUTOMATIC_COMBINATIONS
            ));
        }

        $combinations = $this->cartesianProduct($options);

        return DB::transaction(function () use ($product, $combinations): Collection {
            $activeSignatures = [];
            $result = collect();

            foreach ($combinations as $sortOrder => $combination) {
                $signature = $this->signatureFor($combination);
                $activeSignatures[] = $signature;

                $variant = CommerceProductVariant::query()->firstOrNew([
                    'product_id' => $product->id,
                    'combination_signature' => $signature,
                ]);

                if (! $variant->exists) {
                    $variant->website_id = $product->website_id;
                    $variant->is_enabled = true;
                    $variant->track_inventory = false;
                    $variant->stock_status = CommerceProduct::STOCK_IN_STOCK;
                }

                $variant->sort_order = $sortOrder;
                $variant->save();

                $pivot = [];
                foreach ($combination as $value) {
                    $pivot[$value->id] = ['option_id' => $value->option_id];
                }
                $variant->values()->sync($pivot);

                $result->push($variant->fresh(['values.option']));
            }

            $stale = $product->variants();
            if ($activeSignatures !== []) {
                $stale->whereNotIn('combination_signature', $activeSignatures);
            }
            $stale->update(['is_enabled' => false, 'is_default' => false]);

            $this->normalizeDefaultVariant($product);

            return $result->map(fn (CommerceProductVariant $variant) => $variant->refresh());
        });
    }

    /**
     * Safely change a variant combination without allowing cross-product values,
     * duplicate options, or duplicate combinations.
     *
     * @param array<int, int> $optionValueIds
     */
    public function syncVariantValues(CommerceProductVariant $variant, array $optionValueIds): CommerceProductVariant
    {
        $product = $variant->product()->firstOrFail();
        $valueIds = collect($optionValueIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($valueIds->count() !== count($optionValueIds)) {
            throw new InvalidArgumentException('A variant must contain unique valid option values.');
        }

        $values = CommerceProductOptionValue::query()
            ->whereIn('commerce_product_option_values.id', $valueIds)
            ->whereHas('option', fn ($query) => $query->where('product_id', $product->id))
            ->with('option')
            ->get();

        if ($values->count() !== $valueIds->count()) {
            throw new InvalidArgumentException('Variant values must belong to options on the same product.');
        }

        if ($values->pluck('option_id')->unique()->count() !== $values->count()) {
            throw new InvalidArgumentException('A variant can only select one value from each option.');
        }

        $signature = $this->signatureFor($values);
        $duplicateExists = CommerceProductVariant::query()
            ->where('product_id', $product->id)
            ->where('combination_signature', $signature)
            ->where('id', '!=', $variant->id)
            ->exists();

        if ($duplicateExists) {
            throw new InvalidArgumentException('That variation combination already exists.');
        }

        DB::transaction(function () use ($variant, $values, $signature): void {
            $variant->combination_signature = $signature;
            $variant->save();

            $pivot = [];
            foreach ($values as $value) {
                $pivot[$value->id] = ['option_id' => $value->option_id];
            }
            $variant->values()->sync($pivot);
        });

        return $variant->fresh(['values.option']);
    }

    public function setDefaultVariant(CommerceProduct $product, CommerceProductVariant $variant): void
    {
        if ((int) $variant->product_id !== (int) $product->id || (int) $variant->website_id !== (int) $product->website_id) {
            throw new InvalidArgumentException('The default variant must belong to the same product and website.');
        }

        if (! $variant->is_enabled) {
            throw new InvalidArgumentException('A disabled variant cannot be the default variant.');
        }

        DB::transaction(function () use ($product, $variant): void {
            $product->variants()->update(['is_default' => false]);
            $variant->forceFill(['is_default' => true])->save();
        });
    }

    public function signatureFor(iterable $values): string
    {
        $pairs = collect($values)
            ->map(fn (CommerceProductOptionValue $value): string => $value->option_id.':'.$value->id)
            ->sort()
            ->values()
            ->implode('|');

        if ($pairs === '') {
            throw new InvalidArgumentException('A variation combination must contain at least one option value.');
        }

        return hash('sha256', $pairs);
    }

    /**
     * @return array<int, array<int, CommerceProductOptionValue>>
     */
    private function cartesianProduct(Collection $options): array
    {
        $combinations = [[]];

        foreach ($options as $option) {
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($option->activeValues as $value) {
                    $next[] = [...$combination, $value];
                }
            }
            $combinations = $next;
        }

        return $combinations;
    }

    private function normalizeDefaultVariant(CommerceProduct $product): void
    {
        $enabled = $product->variants()
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($enabled->isEmpty()) {
            return;
        }

        $default = $enabled->firstWhere('is_default', true) ?: $enabled->first();
        $product->variants()->where('id', '!=', $default->id)->update(['is_default' => false]);

        if (! $default->is_default) {
            $default->forceFill(['is_default' => true])->save();
        }
    }
}
