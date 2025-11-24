<?php

namespace Ingenius\Discounts\Services;

use Illuminate\Support\Collection;
use Ingenius\Discounts\DataTransferObjects\DiscountContext;
use Ingenius\Discounts\DataTransferObjects\DiscountResult;
use Ingenius\Discounts\Models\DiscountCampaign;
use Ingenius\Discounts\Enums\DiscountScope;

class DiscountApplicationService
{
    public function __construct(
        protected DiscountEvaluator $evaluator,
        protected DiscountApplicatorFactory $applicatorFactory,
    ) {}

    /**
     * Find and apply all applicable discounts to the given context
     *
     * Logic for stackable discounts:
     * - Each product gets the BEST non-stackable discount
     * - Plus ALL applicable stackable discounts on top
     * - Cart-level discounts are applied AFTER all product discounts
     *
     * @param DiscountContext $context
     * @param DiscountScope $scope Which types of discounts to calculate
     * @return Collection<DiscountResult>
     */
    public function applyDiscounts(
        DiscountContext $context,
        DiscountScope $scope = DiscountScope::ALL
    ): Collection {
        // Find applicable campaigns filtered by scope
        $applicableCampaigns = $this->evaluator->findApplicableDiscounts($context, $scope);

        $finalResults = collect();

        // Process product-level discounts if scope includes products
        if ($scope->includesProducts()) {
            $productResults = $this->applyProductDiscounts($applicableCampaigns, $context);
            $finalResults = $finalResults->merge($productResults['results']);
        }

        // Process shipping discounts if scope includes shipping
        if ($scope->includesShipping()) {
            $shippingResults = $this->applyShippingDiscounts($applicableCampaigns, $context);
            $finalResults = $finalResults->merge($shippingResults);
        }

        // Process cart-level discounts if scope includes cart
        if ($scope->includesCart()) {
            // When scope is CART only, we use cartTotal from context directly
            // (caller should have already adjusted it with product discounts)
            $adjustedCartTotal = $context->cartTotal;

            // When scope is ALL, we need to subtract product discounts first
            if ($scope === DiscountScope::ALL) {
                $totalProductDiscount = $finalResults
                    ->filter(fn($result) => empty($result->metadata['shipping_discount']) && empty($result->metadata['cart_level']))
                    ->sum('amountSaved');
                $adjustedCartTotal = $context->cartTotal - $totalProductDiscount;
            }

            $cartResults = $this->applyCartDiscounts($applicableCampaigns, $context, $adjustedCartTotal);
            $finalResults = $finalResults->merge($cartResults);
        }

        return $finalResults;
    }

    /**
     * Apply product-level discounts (non-stackable best + all stackable)
     */
    protected function applyProductDiscounts(Collection $campaigns, DiscountContext $context): array
    {
        // Reject both cart-level and shipping campaigns from product-level processing
        $productLevelCampaigns = $campaigns
            ->reject(fn($campaign) => $this->isCartLevelCampaign($campaign))
            ->reject(fn($campaign) => $this->isShippingCampaign($campaign));

        $stackableCampaigns = $productLevelCampaigns->where('is_stackable', true);
        $nonStackableCampaigns = $productLevelCampaigns->where('is_stackable', false);

        $productBestNonStackable = [];
        $productStackableDiscounts = [];

        // Process non-stackable campaigns - keep only the best per product
        foreach ($nonStackableCampaigns as $campaign) {
            $applicator = $this->applicatorFactory->getApplicator($campaign->discount_type);

            if (!$applicator) {
                continue;
            }

            $result = $applicator->apply($campaign, $context);

            // Skip shipping and cart-level discounts
            if (!empty($result->metadata['shipping_discount']) || !empty($result->metadata['cart_level'])) {
                continue;
            }

            if ($result->amountSaved > 0) {
                foreach ($result->affectedItems as $affectedItem) {
                    $productId = $affectedItem['productible_id'];
                    $discountAmount = $affectedItem['discount_amount'] ?? 0;

                    if (!isset($productBestNonStackable[$productId]) ||
                        $discountAmount > $productBestNonStackable[$productId]['discount_amount']) {

                        $productBestNonStackable[$productId] = [
                            'campaign' => $campaign,
                            'discount_amount' => $discountAmount,
                            'affected_item' => $affectedItem,
                            'discount_type' => $result->discountType,
                            'campaign_name' => $result->campaignName,
                        ];
                    }
                }
            }
        }

        // Calculate remaining prices after best non-stackable discounts
        $productRemainingPrices = [];
        foreach ($context->items as $item) {
            $productId = $item['productible_id'];
            $basePrice = $item['base_total_in_cents'];

            if (isset($productBestNonStackable[$productId])) {
                $productRemainingPrices[$productId] = $basePrice - $productBestNonStackable[$productId]['discount_amount'];
            } else {
                $productRemainingPrices[$productId] = $basePrice;
            }
        }

        // Process stackable campaigns
        foreach ($stackableCampaigns as $campaign) {
            $applicator = $this->applicatorFactory->getApplicator($campaign->discount_type);

            if (!$applicator) {
                continue;
            }

            $adjustedItems = [];
            foreach ($context->items as $item) {
                $productId = $item['productible_id'];
                $remainingPrice = $productRemainingPrices[$productId] ?? $item['base_total_in_cents'];

                $adjustedItems[] = [
                    ...$item,
                    'base_price_per_unit_in_cents' => $remainingPrice,
                    'base_total_in_cents' => $remainingPrice,
                ];
            }

            $adjustedContext = new DiscountContext(
                cartTotal: array_sum(array_column($adjustedItems, 'base_total_in_cents')),
                items: $adjustedItems,
                customerId: $context->customerId,
                customerType: $context->customerType,
                requestData: $context->requestData,
                orderableEntity: $context->orderableEntity
            );

            $result = $applicator->apply($campaign, $adjustedContext);

            if (!empty($result->metadata['shipping_discount']) || !empty($result->metadata['cart_level'])) {
                continue;
            }

            if ($result->amountSaved > 0) {
                foreach ($result->affectedItems as $affectedItem) {
                    $productId = $affectedItem['productible_id'];
                    $discountAmount = $affectedItem['discount_amount'] ?? 0;

                    if (!isset($productStackableDiscounts[$productId])) {
                        $productStackableDiscounts[$productId] = [];
                    }

                    $productStackableDiscounts[$productId][] = [
                        'campaign' => $campaign,
                        'discount_amount' => $discountAmount,
                        'affected_item' => $affectedItem,
                        'discount_type' => $result->discountType,
                        'campaign_name' => $result->campaignName,
                    ];

                    $productRemainingPrices[$productId] -= $discountAmount;
                }
            }
        }

        // Build results
        $results = collect();
        $campaignResults = [];

        foreach ($productBestNonStackable as $productId => $data) {
            $campaignId = $data['campaign']->id;

            if (!isset($campaignResults[$campaignId])) {
                $campaignResults[$campaignId] = [
                    'items' => [],
                    'total' => 0,
                    'campaign_name' => $data['campaign_name'],
                    'discount_type' => $data['discount_type'],
                    'campaign_id' => $campaignId,
                ];
            }

            $campaignResults[$campaignId]['items'][] = $data['affected_item'];
            $campaignResults[$campaignId]['total'] += $data['discount_amount'];
        }

        foreach ($productStackableDiscounts as $productId => $discounts) {
            foreach ($discounts as $data) {
                $campaignId = $data['campaign']->id;

                if (!isset($campaignResults[$campaignId])) {
                    $campaignResults[$campaignId] = [
                        'items' => [],
                        'total' => 0,
                        'campaign_name' => $data['campaign_name'],
                        'discount_type' => $data['discount_type'],
                        'campaign_id' => $campaignId,
                    ];
                }

                $campaignResults[$campaignId]['items'][] = $data['affected_item'];
                $campaignResults[$campaignId]['total'] += $data['discount_amount'];
            }
        }

        foreach ($campaignResults as $campaignId => $data) {
            $results->push(new DiscountResult(
                campaignId: $data['campaign_id'],
                campaignName: $data['campaign_name'],
                discountType: $data['discount_type'],
                amountSaved: $data['total'],
                affectedItems: $data['items'],
            ));
        }

        return [
            'results' => $results,
            'remaining_prices' => $productRemainingPrices,
        ];
    }

    /**
     * Apply shipping discounts (percentage or fixed_amount discounts targeting Shipment)
     *
     * If context has 'calculated_cost' in requestData, calculates actual discount amounts.
     * Otherwise, returns metadata for ShipmentExtension to calculate later.
     */
    protected function applyShippingDiscounts(Collection $campaigns, DiscountContext $context): Collection
    {
        $results = collect();

        // Filter to only shipping-targeted campaigns
        $shippingCampaigns = $campaigns->filter(fn($campaign) => $this->isShippingCampaign($campaign));

        if ($shippingCampaigns->isEmpty()) {
            return $results;
        }

        // Check if we have shipping cost available for calculation
        $shippingCost = $context->requestData['calculated_cost'] ?? null;
        $canCalculate = $shippingCost !== null && $shippingCost > 0;

        // Separate stackable and non-stackable
        $stackable = $shippingCampaigns->where('is_stackable', true);
        $nonStackable = $shippingCampaigns->where('is_stackable', false);

        // Find best non-stackable shipping discount
        $bestNonStackable = null;
        foreach ($nonStackable as $campaign) {
            // For shipping, we compare by discount_value (higher = better)
            // 100% is better than 50%, $10 is better than $5
            if (!$bestNonStackable || $campaign->discount_value > $bestNonStackable->discount_value) {
                $bestNonStackable = $campaign;
            }
        }

        $remainingCost = $shippingCost ?? 0;

        // Add best non-stackable shipping discount
        if ($bestNonStackable) {
            $amountSaved = 0;

            if ($canCalculate) {
                $amountSaved = $this->calculateShippingDiscountAmount(
                    $bestNonStackable->discount_type,
                    $bestNonStackable->discount_value,
                    $remainingCost
                );
                $remainingCost -= $amountSaved;
            }

            $results->push(new DiscountResult(
                campaignId: $bestNonStackable->id,
                campaignName: $bestNonStackable->name,
                discountType: $bestNonStackable->discount_type,
                amountSaved: $amountSaved,
                affectedItems: [],
                metadata: [
                    'shipping_discount' => true,
                    'discount_type' => $bestNonStackable->discount_type,
                    'discount_value' => $bestNonStackable->discount_value,
                ],
            ));
        }

        // Add all stackable shipping discounts
        foreach ($stackable as $campaign) {
            $amountSaved = 0;

            if ($canCalculate && $remainingCost > 0) {
                $amountSaved = $this->calculateShippingDiscountAmount(
                    $campaign->discount_type,
                    $campaign->discount_value,
                    $remainingCost
                );
                $remainingCost -= $amountSaved;
            }

            $results->push(new DiscountResult(
                campaignId: $campaign->id,
                campaignName: $campaign->name,
                discountType: $campaign->discount_type,
                amountSaved: $amountSaved,
                affectedItems: [],
                metadata: [
                    'shipping_discount' => true,
                    'discount_type' => $campaign->discount_type,
                    'discount_value' => $campaign->discount_value,
                ],
            ));
        }

        return $results;
    }

    /**
     * Calculate the actual shipping discount amount
     */
    protected function calculateShippingDiscountAmount(string $discountType, int $discountValue, int $remainingCost): int
    {
        if ($discountType === 'percentage') {
            return (int) floor($remainingCost * ($discountValue / 100));
        } elseif ($discountType === 'fixed_amount') {
            return min($discountValue, $remainingCost);
        }

        return 0;
    }

    /**
     * Check if campaign targets shipping (Shipment model)
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
     * Apply cart-level discounts
     */
    protected function applyCartDiscounts(Collection $campaigns, DiscountContext $context, int $adjustedCartTotal): Collection
    {
        $cartLevelCampaigns = $campaigns->filter(fn($campaign) => $this->isCartLevelCampaign($campaign));
        $stackableCartCampaigns = $cartLevelCampaigns->where('is_stackable', true);
        $nonStackableCartCampaigns = $cartLevelCampaigns->where('is_stackable', false);

        $results = collect();
        $bestCartDiscount = null;

        // Process non-stackable cart campaigns - find the best one
        foreach ($nonStackableCartCampaigns as $campaign) {
            $applicator = $this->applicatorFactory->getApplicator($campaign->discount_type);

            if (!$applicator) {
                continue;
            }

            $adjustedContext = new DiscountContext(
                cartTotal: $adjustedCartTotal,
                items: $context->items,
                customerId: $context->customerId,
                customerType: $context->customerType,
                requestData: $context->requestData,
                orderableEntity: $context->orderableEntity
            );

            $result = $applicator->apply($campaign, $adjustedContext);

            if ($result->amountSaved > 0) {
                if (!$bestCartDiscount || $result->amountSaved > $bestCartDiscount->amountSaved) {
                    $bestCartDiscount = $result;
                }
            }
        }

        if ($bestCartDiscount) {
            $results->push($bestCartDiscount);
            $adjustedCartTotal -= $bestCartDiscount->amountSaved;
        }

        // Process stackable cart campaigns
        foreach ($stackableCartCampaigns as $campaign) {
            $applicator = $this->applicatorFactory->getApplicator($campaign->discount_type);

            if (!$applicator) {
                continue;
            }

            $adjustedContext = new DiscountContext(
                cartTotal: $adjustedCartTotal,
                items: $context->items,
                customerId: $context->customerId,
                customerType: $context->customerType,
                requestData: $context->requestData,
                orderableEntity: $context->orderableEntity
            );

            $result = $applicator->apply($campaign, $adjustedContext);

            if ($result->amountSaved > 0) {
                $results->push($result);
                $adjustedCartTotal -= $result->amountSaved;
            }
        }

        return $results;
    }

    /**
     * Check if campaign targets cart level (ShopCart with null ID)
     */
    protected function isCartLevelCampaign(DiscountCampaign $campaign): bool
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
     * Apply a specific campaign to the context
     */
    public function applyCampaign(DiscountCampaign $campaign, DiscountContext $context): ?DiscountResult
    {
        $applicator = $this->applicatorFactory->getApplicator($campaign->discount_type);

        if (!$applicator) {
            return null;
        }

        return $applicator->apply($campaign, $context);
    }
}
