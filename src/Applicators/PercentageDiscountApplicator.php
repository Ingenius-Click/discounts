<?php

namespace Ingenius\Discounts\Applicators;

use Ingenius\Discounts\DataTransferObjects\DiscountContext;
use Ingenius\Discounts\DataTransferObjects\DiscountResult;
use Ingenius\Discounts\Enums\DiscountType;
use Ingenius\Discounts\Interfaces\IDiscountApplicator;
use Ingenius\Discounts\Models\DiscountCampaign;

class PercentageDiscountApplicator implements IDiscountApplicator
{
    public function apply(DiscountCampaign $campaign, DiscountContext $context): DiscountResult
    {
        // Check if this is a cart-level discount
        if ($this->isCartLevelDiscount($campaign)) {
            return $this->applyToCartTotal($campaign, $context);
        }

        // Product-level discount logic
        $eligibleItems = $this->getEligibleItems($campaign, $context);
        $totalDiscount = 0;
        $affectedItems = [];

        foreach ($eligibleItems as $item) {
            $itemTotal = $item['base_total_in_cents'] ??
                        ($item['base_price_per_unit_in_cents'] * $item['quantity']);

            // Calculate percentage discount for this item
            $discountAmount = (int) round(($itemTotal * $campaign->discount_value) / 100);
            $totalDiscount += $discountAmount;

            $affectedItems[] = [
                'productible_id' => $item['productible_id'],
                'productible_type' => $item['productible_type'],
                'quantity' => $item['quantity'],
                'original_amount' => $itemTotal,
                'discount_amount' => $discountAmount,
                'final_amount' => $itemTotal - $discountAmount,
                'discount_percentage' => $campaign->discount_value,
            ];
        }

        return new DiscountResult(
            campaignId: $campaign->id,
            campaignName: $campaign->name,
            discountType: $campaign->discount_type,
            amountSaved: $totalDiscount,
            affectedItems: $affectedItems,
        );
    }

    public function supports(string $discountType): bool
    {
        return $discountType === DiscountType::PERCENTAGE->value;
    }

    public function getType(): string
    {
        return DiscountType::PERCENTAGE->value;
    }

    /**
     * Get items eligible for this discount based on targets
     */
    protected function getEligibleItems(DiscountCampaign $campaign, DiscountContext $context): array
    {
        $targets = $campaign->targets;

        if ($targets->isEmpty()) {
            // No targets = applies to all items
            return $context->items;
        }

        $eligibleProductIds = [];
        $eligibleVariantIds = [];
        $eligibleParentProductIds = [];
        $variantModel = config('discounts.variant_model', 'Ingenius\Products\Models\ProductVariant');

        foreach ($targets as $target) {
            if ($target->targetable_type === config('discounts.product_model')) {
                if ($target->targetable_id === null) {
                    return $context->items;
                }
                $eligibleProductIds[] = $target->targetable_id;
                $eligibleParentProductIds[] = $target->targetable_id;
            }

            if ($target->targetable_type === $variantModel) {
                if ($target->targetable_id === null) {
                    return $context->items;
                }
                $eligibleVariantIds[] = $target->targetable_id;
            }

            if ($target->targetable_type === config('discounts.category_model')) {
                $categoryProductIds = $this->getProductsFromCategory($target->targetable_id);
                $eligibleProductIds = array_merge($eligibleProductIds, $categoryProductIds);
                $eligibleParentProductIds = array_merge($eligibleParentProductIds, $categoryProductIds);
            }
        }

        // Resolve variant IDs from parent product IDs
        if (!empty($eligibleParentProductIds) && class_exists($variantModel)) {
            $variantIdsFromParents = $variantModel::whereIn('product_id', $eligibleParentProductIds)
                ->pluck('id')
                ->toArray();
            $eligibleVariantIds = array_merge($eligibleVariantIds, $variantIdsFromParents);
        }

        // Filter context items to only eligible ones
        return array_filter($context->items, function ($item) use ($eligibleProductIds, $eligibleVariantIds, $variantModel) {
            if ($item['productible_type'] === $variantModel) {
                return in_array($item['productible_id'], $eligibleVariantIds);
            }
            return in_array($item['productible_id'], $eligibleProductIds);
        });
    }

    /**
     * Get product IDs from a category
     */
    protected function getProductsFromCategory(int $categoryId): array
    {
        $productModel = config('discounts.product_model');

        if (!class_exists($productModel)) {
            return [];
        }

        $query = $productModel::query();

        if (method_exists($productModel, 'categories')) {
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        } else {
            $query->where('category_id', $categoryId);
        }

        return $query->pluck('id')->toArray();
    }

    /**
     * Check if this campaign targets the entire cart (not specific products)
     */
    protected function isCartLevelDiscount(DiscountCampaign $campaign): bool
    {
        $targets = $campaign->targets;

        if ($targets->isEmpty()) {
            return false;
        }

        // Check if any target is ShopCart with null ID
        foreach ($targets as $target) {
            if ($target->targetable_type === config('discounts.shop_cart_model')
                && $target->targetable_id === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply percentage discount to the entire cart total
     */
    protected function applyToCartTotal(DiscountCampaign $campaign, DiscountContext $context): DiscountResult
    {
        $cartTotal = $context->cartTotal;

        // Calculate percentage discount on cart total
        $discountAmount = (int) round(($cartTotal * $campaign->discount_value) / 100);

        return new DiscountResult(
            campaignId: $campaign->id,
            campaignName: $campaign->name,
            discountType: $campaign->discount_type,
            amountSaved: $discountAmount,
            affectedItems: [],
            metadata: [
                'cart_level' => true,
                'cart_total' => $cartTotal,
                'discount_percentage' => $campaign->discount_value,
            ]
        );
    }
}
