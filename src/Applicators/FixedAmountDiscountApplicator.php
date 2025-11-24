<?php

namespace Ingenius\Discounts\Applicators;

use Ingenius\Discounts\DataTransferObjects\DiscountContext;
use Ingenius\Discounts\DataTransferObjects\DiscountResult;
use Ingenius\Discounts\Enums\DiscountType;
use Ingenius\Discounts\Interfaces\IDiscountApplicator;
use Ingenius\Discounts\Models\DiscountCampaign;

class FixedAmountDiscountApplicator implements IDiscountApplicator
{
    public function apply(DiscountCampaign $campaign, DiscountContext $context): DiscountResult
    {
        // Check if this is a cart-level discount
        if ($this->isCartLevelDiscount($campaign)) {
            return $this->applyToCartTotal($campaign, $context);
        }

        // Product-level discount logic
        $eligibleItems = $this->getEligibleItems($campaign, $context);
        $fixedAmount = $campaign->discount_value; // Amount in cents per item
        $totalDiscount = 0;
        $affectedItems = [];

        foreach ($eligibleItems as $item) {
            $pricePerUnit = $item['base_price_per_unit_in_cents'];
            $quantity = $item['quantity'] ?? 1;
            $itemTotal = $item['base_total_in_cents'] ?? ($pricePerUnit * $quantity);

            // Calculate discount: fixed amount per unit, multiplied by quantity
            // But cap at item price per unit (can't discount more than the price)
            $discountPerUnit = min($fixedAmount, $pricePerUnit);
            $itemDiscount = $discountPerUnit * $quantity;

            $totalDiscount += $itemDiscount;

            $affectedItems[] = [
                'productible_id' => $item['productible_id'],
                'productible_type' => $item['productible_type'],
                'quantity' => $quantity,
                'price_per_unit' => $pricePerUnit,
                'discount_per_unit' => $discountPerUnit,
                'original_amount' => $itemTotal,
                'discount_amount' => $itemDiscount,
                'final_amount' => $itemTotal - $itemDiscount,
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
        return $discountType === DiscountType::FIXED_AMOUNT->value;
    }

    public function getType(): string
    {
        return DiscountType::FIXED_AMOUNT->value;
    }

    /**
     * Get items eligible for this discount based on targets
     */
    protected function getEligibleItems(DiscountCampaign $campaign, DiscountContext $context): array
    {
        $targets = $campaign->targets;

        if ($targets->isEmpty()) {
            return $context->items;
        }

        $eligibleProductIds = [];

        foreach ($targets as $target) {
            if ($target->targetable_type === config('discounts.product_model')) {
                if ($target->targetable_id === null) {
                    return $context->items;
                }
                $eligibleProductIds[] = $target->targetable_id;
            }

            if ($target->targetable_type === config('discounts.category_model')) {
                $categoryProductIds = $this->getProductsFromCategory($target->targetable_id);
                $eligibleProductIds = array_merge($eligibleProductIds, $categoryProductIds);
            }
        }

        return array_filter($context->items, function ($item) use ($eligibleProductIds) {
            return in_array($item['productible_id'], $eligibleProductIds);
        });
    }

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
     * Apply fixed amount discount to the entire cart total
     */
    protected function applyToCartTotal(DiscountCampaign $campaign, DiscountContext $context): DiscountResult
    {
        $cartTotal = $context->cartTotal;
        $fixedAmount = $campaign->discount_value; // Amount in cents

        // Cap discount at cart total (can't discount more than the cart is worth)
        $discountAmount = min($fixedAmount, $cartTotal);

        return new DiscountResult(
            campaignId: $campaign->id,
            campaignName: $campaign->name,
            discountType: $campaign->discount_type,
            amountSaved: $discountAmount,
            affectedItems: [],
            metadata: [
                'cart_level' => true,
                'cart_total' => $cartTotal,
                'discount_amount' => $fixedAmount,
            ]
        );
    }
}
