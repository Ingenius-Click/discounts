<?php

namespace Ingenius\Discounts\Services;

use Ingenius\Auth\Helpers\AuthHelper;
use Ingenius\Discounts\DataTransferObjects\DiscountContext;
use Ingenius\Discounts\Enums\DiscountScope;

class ShipmentDiscountService
{
    public function __construct(
        protected DiscountApplicationService $discountService
    ) {}

    /**
     * Check if the calculate-discounts feature is enabled for the current tenant
     */
    protected function isFeatureEnabled(): bool
    {
        $tenant = tenant();
        return $tenant && $tenant->hasFeature('calculate-discounts');
    }

    /**
     * Apply shipping discounts and return extended data for the shipping cost endpoint
     *
     * @param array $data The original shipping data (contains 'price')
     * @param array $context Hook context with shipping_method and calculated_cost
     * @return array Extended data with shipping discount information
     */
    public function applyDiscountsToShipment(array $data, array $context): array
    {
        if (!$this->isFeatureEnabled()) {
            return $data;
        }

        $shopCartClass = config('discounts.shop_cart_model');

        if (!$shopCartClass || !class_exists($shopCartClass)) {
            return $data;
        }

        $shopCart = app($shopCartClass);
        $cartItems = $shopCart->getCartItems();

        if ($cartItems->isEmpty()) {
            return $data;
        }

        // Get current user
        $user = AuthHelper::getUser();
        $userId = $user ? ($user->id ?? null) : null;

        $cartTotal = method_exists($shopCart, 'calculateFinalSubtotal')
            ? $shopCart->calculateFinalSubtotal()
            : $cartItems->sum(function ($item) {
                $finalPrice = $item->productible?->getFinalPrice() ?? 0;
                return $finalPrice * $item->quantity;
            });

        // Get the shipping cost from context (passed from ShippingMethodsController)
        $calculatedCost = $context['calculated_cost']->price ?? 0;

        $discountContext = DiscountContext::fromCart(
            cartTotal: $cartTotal,
            cartItems: $cartItems->map(function ($item) {
                $finalPrice = $item->productible?->getFinalPrice() ?? 0;

                return [
                    'productible_id' => $item->productible_id,
                    'productible_type' => $item->productible_type,
                    'base_total_in_cents' => $finalPrice * $item->quantity,
                    'base_price_per_unit_in_cents' => $finalPrice,
                    'quantity' => $item->quantity,
                ];
            })->toArray(),
            customerId: $userId,
            customerType: $user ? get_class($user) : null,
            requestData: [
                'shipping_method' => $context['shipping_method'] ?? null,
                'calculated_cost' => $calculatedCost,
            ]
        );

        $results = $this->discountService->applyDiscounts(
            $discountContext,
            DiscountScope::SHIPPING
        );

        if ($results->isEmpty()) {
            return $data;
        }

        // Build applied discounts array
        $appliedDiscounts = [];
        $totalDiscount = 0;

        foreach ($results as $result) {
            if (!empty($result->metadata['shipping_discount'])) {
                $appliedDiscounts[] = [
                    'campaign_id' => $result->campaignId,
                    'campaign_name' => $result->campaignName,
                    'discount_type' => $result->metadata['discount_type'] ?? $result->discountType,
                    'discount_value' => $result->metadata['discount_value'] ?? 0,
                    'amount_saved' => $result->amountSaved,
                    'amount_saved_converted' => convert_currency($result->amountSaved),
                ];
                $totalDiscount += $result->amountSaved;
            }
        }

        if (empty($appliedDiscounts)) {
            return $data;
        }

        return [
            ...$data,
            'original_price' => $calculatedCost,
            'original_price_converted' => convert_currency($calculatedCost),
            'discounted_price' => $calculatedCost - $totalDiscount,
            'discounted_price_converted' => convert_currency($calculatedCost - $totalDiscount),
            'shipping_discount_applied' => true,
            'shipping_discount_amount' => $totalDiscount,
            'shipping_discount_amount_converted' => convert_currency($totalDiscount),
            'shipping_discounts' => $appliedDiscounts,
        ];
    }
}