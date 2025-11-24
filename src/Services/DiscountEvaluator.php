<?php

namespace Ingenius\Discounts\Services;

use Illuminate\Support\Collection;
use Ingenius\Discounts\DataTransferObjects\DiscountContext;
use Ingenius\Discounts\Models\DiscountCampaign;
use Ingenius\Discounts\Enums\ConditionType;
use Ingenius\Discounts\Enums\DiscountScope;
use Ingenius\Discounts\Enums\TargetAction;

class DiscountEvaluator
{
    /**
     * Find all applicable discount campaigns for the given context
     *
     * @param DiscountContext $context The discount context (cart or order data)
     * @param DiscountScope $scope Which types of discounts to find
     * @return Collection<DiscountCampaign>
     */
    public function findApplicableDiscounts(
        DiscountContext $context,
        DiscountScope $scope = DiscountScope::ALL
    ): Collection {
        $now = now();

        // Get active campaigns ordered by priority
        $campaigns = DiscountCampaign::query()
            ->where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->orderBy('priority', 'desc') // Higher priority first
            ->get();

        // Filter campaigns through all validation steps
        return $campaigns
            ->filter(fn($campaign) => $this->checkUsageLimits($campaign, $context))
            ->filter(fn($campaign) => $this->evaluateConditions($campaign, $context))
            ->filter(fn($campaign) => $this->checkTargets($campaign, $context))
            ->filter(fn($campaign) => $this->matchesScope($campaign, $scope));
    }

    /**
     * Check if campaign matches the requested scope
     */
    protected function matchesScope(DiscountCampaign $campaign, DiscountScope $scope): bool
    {
        if ($scope === DiscountScope::ALL) {
            return true;
        }

        $isCartLevel = $this->isCartLevelCampaign($campaign);
        $isShipping = $this->isShippingCampaign($campaign);

        return match ($scope) {
            DiscountScope::PRODUCTS => !$isCartLevel && !$isShipping,
            DiscountScope::CART => $isCartLevel,
            DiscountScope::SHIPPING => $isShipping,
            default => true,
        };
    }

    /**
     * Check if campaign targets cart level (ShopCart with null ID)
     */
    protected function isCartLevelCampaign(DiscountCampaign $campaign): bool
    {
        $targets = $campaign->targets;

        foreach ($targets as $target) {
            if ($target->targetable_type === config('discounts.shop_cart_model')
                && $target->targetable_id === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if campaign targets shipping
     */
    protected function isShippingCampaign(DiscountCampaign $campaign): bool
    {
        $targets = $campaign->targets;

        foreach ($targets as $target) {
            if ($target->targetable_type === config('discounts.shipment_model')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if campaign has reached its usage limits
     */
    protected function checkUsageLimits(DiscountCampaign $campaign, DiscountContext $context): bool
    {
        // Check total usage limit
        if ($campaign->hasReachedLimit()) {
            return false;
        }

        // Check per-customer usage limit
        if ($context->customerId && $campaign->customerHasReachedLimit($context->customerId)) {
            return false;
        }

        return true;
    }

    /**
     * Evaluate all conditions for a campaign
     */
    protected function evaluateConditions(DiscountCampaign $campaign, DiscountContext $context): bool
    {
        $conditions = $campaign->conditions()->orderBy('priority')->get();

        if ($conditions->isEmpty()) {
            return true; // No conditions = always applicable
        }

        $result = true;

        foreach ($conditions as $condition) {
            $conditionMet = $this->evaluateCondition($condition, $context);

            // Apply logic operator for combining with previous result
            $result = $condition->logic_operator === 'OR'
                ? $result || $conditionMet
                : $result && $conditionMet;
        }

        return $result;
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition($condition, DiscountContext $context): bool
    {
        $value = $condition->value; // Already cast to array

        switch ($condition->condition_type) {
            case ConditionType::MIN_CART_VALUE->value:
                return $this->compareValues(
                    $context->cartTotal,
                    $condition->operator,
                    $value['amount']
                );

            case ConditionType::MIN_QUANTITY->value:
                return $this->compareValues(
                    $context->getTotalQuantity(),
                    $condition->operator,
                    $value['quantity']
                );

            case ConditionType::CUSTOMER_SEGMENT->value:
                // Check if customer is in specific segment
                $customerIds = $value['customer_ids'] ?? [];
                return in_array($context->customerId, $customerIds);

            case ConditionType::HAS_PRODUCT->value:
                // Check if cart/order contains specific product(s)
                $productIds = $value['product_ids'] ?? [];
                return $context->hasAnyProduct($productIds);

            case ConditionType::FIRST_ORDER->value:
                // Check if this is customer's first order
                return $this->isFirstOrder($context);

            default:
                return false;
        }
    }

    /**
     * Compare values based on operator
     */
    protected function compareValues($actual, string $operator, $expected): bool
    {
        return match($operator) {
            '>=' => $actual >= $expected,
            '>' => $actual > $expected,
            '<=' => $actual <= $expected,
            '<' => $actual < $expected,
            '==' => $actual == $expected,
            '!=' => $actual != $expected,
            'in' => in_array($actual, (array)$expected),
            'not_in' => !in_array($actual, (array)$expected),
            default => false,
        };
    }

    /**
     * Check if this is the customer's first order
     */
    protected function isFirstOrder(DiscountContext $context): bool
    {
        if (!$context->customerId) {
            return false;
        }

        // Check if customer has any previous orders
        $orderModel = config('discounts.order_model');

        if (!class_exists($orderModel)) {
            return false;
        }

        $previousOrders = $orderModel::query()
            ->where('userable_id', $context->customerId)
            ->where('userable_type', $context->customerType)
            ->count();

        return $previousOrders === 0;
    }

    /**
     * Check if campaign targets are met
     */
    protected function checkTargets(DiscountCampaign $campaign, DiscountContext $context): bool
    {
        $targets = $campaign->targets()
            ->where('target_action', TargetAction::APPLY_TO->value)
            ->get();

        if ($targets->isEmpty()) {
            return true; // No targets = applies to everything
        }

        foreach ($targets as $target) {
            if($target->targetable_type === config('discounts.shop_cart_model')) {
                return true;
            }

            // For shipping targets
            if ($target->targetable_type === config('discounts.shipment_model')) {
                return true;
            }

            // For product targets
            if ($target->targetable_type === config('discounts.product_model')) {
                if ($target->targetable_id === null) {
                    // Applies to all products
                    return true;
                }

                // Check if this specific product is in the context
                if ($context->hasProduct($target->targetable_id)) {
                    return true;
                }
            }

            // For category targets
            if ($target->targetable_type === config('discounts.category_model')) {
                if ($this->hasProductsFromCategory($target->targetable_id, $context)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if context has products from a specific category
     */
    protected function hasProductsFromCategory(int $categoryId, DiscountContext $context): bool
    {
        $productModel = config('discounts.product_model');

        if (!class_exists($productModel)) {
            return false;
        }

        $query = $productModel::query();

        // Check if product model has 'categories' relationship (many-to-many)
        if (method_exists($productModel, 'categories')) {
            // Many-to-many relationship
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        } else {
            // Single category_id column (belongsTo relationship)
            $query->where('category_id', $categoryId);
        }

        $categoryProductIds = $query->pluck('id')->toArray();

        return $context->hasAnyProduct($categoryProductIds);
    }
}