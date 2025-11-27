<?php

namespace Ingenius\Discounts\Services;

use Ingenius\Core\Helpers\AuthHelper;
use Ingenius\Discounts\DataTransferObjects\DiscountContext;
use Ingenius\Discounts\Enums\DiscountScope;
use Ingenius\Discounts\Models\DiscountCampaign;
use Ingenius\Discounts\Enums\TargetAction;

/**
 * Service for calculating discounts on individual products
 */
class ProductDiscountService
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
     * Find the most favorable discount for a single product
     * Returns the discount amount that provides the maximum savings to the customer
     *
     * This method considers stackable discounts:
     * - Finds the best non-stackable discount
     * - Adds all stackable discounts on top
     *
     * @param int $productId
     * @param string $productClass
     * @param int $basePrice Price in cents
     * @param bool $onlyUnconditional Only consider discounts without conditions (for display purposes)
     * @return int The discount amount in cents (0 if no discount applies)
     */
    public function getMostFavorableDiscount(int $productId, string $productClass, int $basePrice, bool $onlyUnconditional = false): int
    {
        // Get current user
        $user = AuthHelper::getUser();

        // Build a minimal context for a single product
        $context = DiscountContext::fromCart(
            cartTotal: $basePrice,
            cartItems: [
                [
                    'productible_id' => $productId,
                    'productible_type' => $productClass,
                    'quantity' => 1,
                    'base_price_per_unit_in_cents' => $basePrice,
                    'base_total_in_cents' => $basePrice,
                ]
            ],
            customerId: $user?->id,
            customerType: $user ? get_class($user) : null,
            requestData: []
        );

        // If only unconditional, filter campaigns first
        if ($onlyUnconditional) {
            $applicableCampaigns = $this->getUnconditionalCampaignsForProduct($productId, $productClass);

            if ($applicableCampaigns->isEmpty()) {
                return 0;
            }

            return $this->calculateStackableDiscounts($applicableCampaigns, $context, $basePrice);
        }

        // Apply all applicable discounts
        $results = $this->discountService->applyDiscounts($context, DiscountScope::PRODUCTS);

        if ($results->isEmpty()) {
            return 0;
        }
        
        // Find the discount that saves the most money (most favorable to customer)
        $maxSavings = $results->sum('amountSaved');

        return $maxSavings ?? 0;
    }

    /**
     * Calculate total savings from stackable and non-stackable discounts
     *
     * Logic:
     * 1. Find the best non-stackable discount
     * 2. Sum all stackable discounts
     * 3. Return combined total
     *
     * @param \Illuminate\Support\Collection $campaigns
     * @param DiscountContext $context
     * @param int $basePrice
     * @return int Total discount amount in cents
     */
    protected function calculateStackableDiscounts(\Illuminate\Support\Collection $campaigns, DiscountContext $context, int $basePrice): int
    {
        $bestNonStackable = 0;
        $stackableTotal = 0;
        $remainingPrice = $basePrice;

        // Separate stackable and non-stackable campaigns
        $stackableCampaigns = $campaigns->where('is_stackable', true);
        $nonStackableCampaigns = $campaigns->where('is_stackable', false);

        // Find the best non-stackable discount
        foreach ($nonStackableCampaigns as $campaign) {
            $result = $this->discountService->applyCampaign($campaign, $context);
            if ($result && $result->amountSaved > $bestNonStackable) {
                $bestNonStackable = $result->amountSaved;
            }
        }

        // Apply best non-stackable first
        $remainingPrice -= $bestNonStackable;

        // Apply all stackable discounts on the remaining price
        foreach ($stackableCampaigns as $campaign) {
            // Update context with remaining price for stackable calculations
            $stackableContext = DiscountContext::fromCart(
                cartTotal: $remainingPrice,
                cartItems: [
                    [
                        'productible_id' => $context->items[0]['productible_id'],
                        'productible_type' => $context->items[0]['productible_type'],
                        'quantity' => 1,
                        'base_price_per_unit_in_cents' => $remainingPrice,
                        'base_total_in_cents' => $remainingPrice,
                    ]
                ],
                customerId: $context->customerId,
                customerType: $context->customerType,
                requestData: $context->requestData
            );

            $result = $this->discountService->applyCampaign($campaign, $stackableContext);
            if ($result && $result->amountSaved > 0) {
                $stackableTotal += $result->amountSaved;
                $remainingPrice -= $result->amountSaved;
            }
        }

        return $bestNonStackable + $stackableTotal;
    }

    /**
     * Get the most favorable (best) discount for a product
     *
     * Returns only the single best non-stackable discount
     *
     * @param \Illuminate\Support\Collection $campaigns
     * @param DiscountContext $context
     * @return array|null Best discount details or null if no discount applies
     */
    protected function getBestNonStackableDiscount(\Illuminate\Support\Collection $campaigns, DiscountContext $context): ?array
    {
        $nonStackableCampaigns = $campaigns->where('is_stackable', false);

        if ($nonStackableCampaigns->isEmpty()) {
            return null;
        }

        $bestNonStackableResult = null;
        $bestNonStackableSavings = 0;

        // Find the best non-stackable discount
        foreach ($nonStackableCampaigns as $campaign) {
            $result = $this->discountService->applyCampaign($campaign, $context);
            if ($result && $result->amountSaved > $bestNonStackableSavings) {
                $bestNonStackableSavings = $result->amountSaved;
                $bestNonStackableResult = $result;
            }
        }

        if (!$bestNonStackableResult) {
            return null;
        }

        return [
            'campaign_id' => $bestNonStackableResult->campaignId,
            'campaign_name' => $bestNonStackableResult->campaignName,
            'discount_type' => $bestNonStackableResult->discountType,
            'amount_saved' => $bestNonStackableResult->amountSaved,
            'is_stackable' => false,
        ];
    }

    /**
     * Get all applicable stackable discounts for a product
     *
     * Applies stackable discounts sequentially on the remaining price
     * after the best non-stackable discount has been applied
     *
     * @param \Illuminate\Support\Collection $campaigns
     * @param DiscountContext $context
     * @param int $remainingPrice Price after best non-stackable discount
     * @return array Array of stackable discount details
     */
    protected function getApplicableStackableDiscounts(\Illuminate\Support\Collection $campaigns, DiscountContext $context, int $remainingPrice): array
    {
        $stackableCampaigns = $campaigns->where('is_stackable', true);

        if ($stackableCampaigns->isEmpty()) {
            return [];
        }

        $stackableDiscounts = [];

        foreach ($stackableCampaigns as $campaign) {
            $stackableContext = DiscountContext::fromCart(
                cartTotal: $remainingPrice,
                cartItems: [
                    [
                        'productible_id' => $context->items[0]['productible_id'],
                        'productible_type' => $context->items[0]['productible_type'],
                        'quantity' => 1,
                        'base_price_per_unit_in_cents' => $remainingPrice,
                        'base_total_in_cents' => $remainingPrice,
                    ]
                ],
                customerId: $context->customerId,
                customerType: $context->customerType,
                requestData: $context->requestData
            );

            $result = $this->discountService->applyCampaign($campaign, $stackableContext);
            if ($result && $result->amountSaved > 0) {
                $stackableDiscounts[] = [
                    'campaign_id' => $result->campaignId,
                    'campaign_name' => $result->campaignName,
                    'discount_type' => $result->discountType,
                    'amount_saved' => $result->amountSaved,
                    'is_stackable' => true,
                ];

                // Update remaining price for next stackable discount
                $remainingPrice -= $result->amountSaved;
            }
        }

        return $stackableDiscounts;
    }

    /**
     * Get active discount campaigns without conditions that target a specific product
     * These are discounts that always apply when the product is added to cart
     *
     * @param int $productId
     * @param string $productClass
     * @return \Illuminate\Support\Collection<DiscountCampaign>
     */
    protected function getUnconditionalCampaignsForProduct(int $productId, string $productClass): \Illuminate\Support\Collection
    {
        $now = now();

        // Get the product's category IDs for category-based discount matching
        $categoryIds = $this->getProductCategoryIds($productId, $productClass);
        $categoryModel = config('discounts.category_model');

        return DiscountCampaign::query()
            ->where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->whereDoesntHave('conditions') // Only campaigns without conditions
            ->where(function ($query) use ($productId, $productClass, $categoryIds, $categoryModel) {
                // Campaign has no targets (applies to all products)
                $query->whereDoesntHave('targets', function ($q) {
                    $q->where('target_action', TargetAction::APPLY_TO->value);
                })
                // OR has targets that include this product
                ->orWhereHas('targets', function ($q) use ($productId, $productClass, $categoryIds, $categoryModel) {
                    $q->where('target_action', TargetAction::APPLY_TO->value)
                        ->where(function ($targetQuery) use ($productId, $productClass, $categoryIds, $categoryModel) {
                            // Targets this specific product
                            $targetQuery->where('targetable_type', $productClass)
                                ->where('targetable_id', $productId);
                        })
                        ->orWhere(function ($targetQuery) use ($productClass) {
                            // OR targets all products of this type (targetable_id is null)
                            $targetQuery->where('targetable_type', $productClass)
                                ->whereNull('targetable_id');
                        })
                        ->orWhere(function ($targetQuery) use ($categoryIds, $categoryModel) {
                            // OR targets a category that this product belongs to
                            if (!empty($categoryIds) && $categoryModel) {
                                $targetQuery->where('targetable_type', $categoryModel)
                                    ->whereIn('targetable_id', $categoryIds);
                            }
                        });
                });
            })
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Get all possible discount campaigns for a product
     *
     * This method returns campaigns that COULD apply to a product, checking:
     * - Active status and date range
     * - Usage limits (total and per-customer)
     * - Target matching (if product is targeted)
     *
     * DOES NOT check conditions - useful for showing "available discounts"
     * even if the customer doesn't currently meet the conditions
     *
     * @param int $productId
     * @param string $productClass
     * @param int|null $customerId Optional customer ID to check per-customer usage limits
     * @return \Illuminate\Support\Collection<DiscountCampaign> Collection of possible campaigns
     */
    public function getPossibleDiscountsForProduct(int $productId, string $productClass, ?int $customerId = null): \Illuminate\Support\Collection
    {
        $now = now();

        // Get the product's category IDs for category-based discount matching
        $categoryIds = $this->getProductCategoryIds($productId, $productClass);
        $categoryModel = config('discounts.category_model');

        // Get active campaigns within date range that target this product
        $campaigns = DiscountCampaign::query()
            ->with(['conditions', 'targets']) // Eager load for client-side inspection
            ->where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->where(function ($query) use ($productId, $productClass, $categoryIds, $categoryModel) {
                // Campaign has no targets (applies to all products)
                $query->whereDoesntHave('targets', function ($q) {
                    $q->where('target_action', TargetAction::APPLY_TO->value);
                })
                // OR has targets that include this product
                ->orWhereHas('targets', function ($q) use ($productId, $productClass, $categoryIds, $categoryModel) {
                    $q->where('target_action', TargetAction::APPLY_TO->value)
                        ->where(function ($targetQuery) use ($productId, $productClass, $categoryIds, $categoryModel) {
                            // Targets this specific product
                            $targetQuery->where('targetable_type', $productClass)
                                ->where('targetable_id', $productId);
                        })
                        ->orWhere(function ($targetQuery) use ($productClass) {
                            // OR targets all products of this type (targetable_id is null)
                            $targetQuery->where('targetable_type', $productClass)
                                ->whereNull('targetable_id');
                        })
                        ->orWhere(function ($targetQuery) use ($categoryIds, $categoryModel) {
                            // OR targets a category that this product belongs to
                            if (!empty($categoryIds) && $categoryModel) {
                                $targetQuery->where('targetable_type', $categoryModel)
                                    ->whereIn('targetable_id', $categoryIds);
                            }
                        });
                });
            })
            ->orderBy('priority', 'desc')
            ->get();

        // Filter out campaigns that have reached usage limits
        return $campaigns->filter(function ($campaign) use ($customerId) {
            // Check total usage limit
            if ($campaign->hasReachedLimit()) {
                return false;
            }

            // Check per-customer usage limit if customer is provided
            if ($customerId && $campaign->customerHasReachedLimit($customerId)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Get category IDs for a product
     *
     * @param int $productId
     * @param string $productClass
     * @return array Array of category IDs
     */
    protected function getProductCategoryIds(int $productId, string $productClass): array
    {
        if (!class_exists($productClass)) {
            return [];
        }

        $product = $productClass::find($productId);

        if (!$product) {
            return [];
        }

        // Check if product has a many-to-many 'categories' relationship
        if (method_exists($product, 'categories')) {
            return $product->categories()->pluck('categories.id')->toArray();
        }

        // Fallback to single category_id column (belongsTo relationship)
        if (isset($product->category_id)) {
            return [$product->category_id];
        }

        return [];
    }

    /**
     * Get detailed information about the most favorable (best) discount ONLY
     *
     * Returns only the single best non-stackable discount for a product.
     * Does NOT include stackable discounts - use getApplicableStackableDiscounts() for those.
     *
     * @param int $productId
     * @param string $productClass
     * @param int $basePrice Price in cents
     * @param bool $onlyUnconditional Only consider discounts without conditions (for display purposes)
     * @return array|null Best discount details or null if no discount applies
     */
    public function getMostFavorableDiscountDetails(int $productId, string $productClass, int $basePrice, bool $onlyUnconditional = false): ?array
    {
        $user = AuthHelper::getUser();

        $context = DiscountContext::fromCart(
            cartTotal: $basePrice,
            cartItems: [
                [
                    'productible_id' => $productId,
                    'productible_type' => $productClass,
                    'quantity' => 1,
                    'base_price_per_unit_in_cents' => $basePrice,
                    'base_total_in_cents' => $basePrice,
                ]
            ],
            customerId: $user?->id,
            customerType: $user ? get_class($user) : null,
            requestData: []
        );

        // If only unconditional, filter campaigns first
        if ($onlyUnconditional) {
            $applicableCampaigns = $this->getUnconditionalCampaignsForProduct($productId, $productClass);

            if ($applicableCampaigns->isEmpty()) {
                return null;
            }

            return $this->getBestNonStackableDiscount($applicableCampaigns, $context);
        }

        $results = $this->discountService->applyDiscounts($context);

        if ($results->isEmpty()) {
            return null;
        }

        // Find the discount with maximum savings
        $bestDiscount = $results->sortByDesc('amountSaved')->first();

        return [
            'campaign_id' => $bestDiscount->campaignId,
            'campaign_name' => $bestDiscount->campaignName,
            'discount_type' => $bestDiscount->discountType,
            'amount_saved' => $bestDiscount->amountSaved,
            'is_stackable' => false, // From applyDiscounts, this is the best overall
        ];
    }

    /**
     * Get all applicable stackable discounts for a product (public interface)
     *
     * @param int $productId
     * @param string $productClass
     * @param int $basePrice Price in cents
     * @param bool $onlyUnconditional Only consider discounts without conditions
     * @return array Array of stackable discount details
     */
    public function getStackableDiscountsForProduct(int $productId, string $productClass, int $basePrice, bool $onlyUnconditional = false): array
    {
        $user = AuthHelper::getUser();

        $context = DiscountContext::fromCart(
            cartTotal: $basePrice,
            cartItems: [
                [
                    'productible_id' => $productId,
                    'productible_type' => $productClass,
                    'quantity' => 1,
                    'base_price_per_unit_in_cents' => $basePrice,
                    'base_total_in_cents' => $basePrice,
                ]
            ],
            customerId: $user?->id,
            customerType: $user ? get_class($user) : null,
            requestData: []
        );

        if ($onlyUnconditional) {
            $applicableCampaigns = $this->getUnconditionalCampaignsForProduct($productId, $productClass);

            if ($applicableCampaigns->isEmpty()) {
                return [];
            }

            // Get best non-stackable first to calculate remaining price
            $bestNonStackable = $this->getBestNonStackableDiscount($applicableCampaigns, $context);
            $remainingPrice = $basePrice;

            if ($bestNonStackable) {
                $remainingPrice -= $bestNonStackable['amount_saved'];
            }

            return $this->getApplicableStackableDiscounts($applicableCampaigns, $context, $remainingPrice);
        }

        // For conditional discounts, use the full discount application service
        $results = $this->discountService->applyDiscounts($context);

        // Filter to get only stackable results
        return $results
            ->filter(fn($result) => isset($result->metadata['is_stackable']) && $result->metadata['is_stackable'])
            ->map(fn($result) => [
                'campaign_id' => $result->campaignId,
                'campaign_name' => $result->campaignName,
                'discount_type' => $result->discountType,
                'amount_saved' => $result->amountSaved,
                'is_stackable' => true,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Calculate final price after applying the most favorable discount
     * This method is designed to be used as a hook handler for product.final_price
     *
     * IMPORTANT: Only applies discounts WITHOUT conditions, as discounts with conditions
     * may not actually apply when the product is added to cart (depends on cart state, user, etc.)
     *
     * @param int $basePrice The base price in cents
     * @param array $context Context data containing product_id and product_class
     * @return int The final price after discount in cents
     */
    public function calculateFinalPrice(int $basePrice, array $context): int
    {
        if (!$this->isFeatureEnabled()) {
            return $basePrice;
        }

        $productId = $context['product_id'] ?? null;
        $productClass = $context['product_class'] ?? null;

        if (!$productId || !$productClass) {
            return $basePrice;
        }

        // Get the most favorable UNCONDITIONAL discount amount
        // This ensures we only show discounts that will definitely apply
        $discountAmount = $this->getMostFavorableDiscount(
            $productId,
            $productClass,
            $basePrice,
            onlyUnconditional: false
        );

        // Return the price after applying the best discount
        return $basePrice - $discountAmount;
    }

    public function calculateShowcasePrice(int $basePrice, array $context): int
    {
        if (!$this->isFeatureEnabled()) {
            return $basePrice;
        }

        $productId = $context['product_id'] ?? null;
        $productClass = $context['product_class'] ?? null;

        if (!$productId || !$productClass) {
            return $basePrice;
        }

        // Get the most favorable UNCONDITIONAL discount amount
        // This ensures we only show discounts that will definitely apply
        $discountAmount = $this->getMostFavorableDiscount(
            $productId,
            $productClass,
            $basePrice,
            onlyUnconditional: true
        );

        // Return the price after applying the best discount
        return $basePrice - $discountAmount;
    }

    /**
     * Extend product array with discount information
     * This method is designed to be used as a hook handler for product.array.extend
     *
     * Returns three types of discounts:
     * 1. no_conditional_no_stackable: Most favorable unconditional non-stackable discount
     * 2. conditionals_no_stackable: All conditional non-stackable discounts
     * 3. stackable_discounts: All stackable discounts (both conditional and unconditional)
     *
     * For price calculation: Uses unconditional discounts only (via calculateFinalPrice hook)
     * since those are guaranteed to apply when added to cart.
     *
     * @param array $data The product array to extend
     * @param array $context Context data containing product_id, product_class, and base_price
     * @return array Extended product array with discount data
     */
    public function extendProductArray(array $data, array $context): array
    {
        if (!$this->isFeatureEnabled()) {
            return $data;
        }

        $productId = $context['product_id'] ?? null;
        $productClass = $context['product_class'] ?? null;
        $basePrice = $context['base_price'] ?? null;

        if (!$productId || !$productClass || !$basePrice) {
            return $data;
        }

        // Get current user for usage limit checks
        $user = AuthHelper::getUser();

        // Get all POSSIBLE campaigns (checking targets and usage limits only)
        $possibleCampaigns = $this->getPossibleDiscountsForProduct(
            $productId,
            $productClass,
            $user?->id
        );

        if ($possibleCampaigns->isEmpty()) {
            return $data;
        }

        // Build context for discount calculations
        $discountContext = DiscountContext::fromCart(
            cartTotal: $basePrice,
            cartItems: [
                [
                    'productible_id' => $productId,
                    'productible_type' => $productClass,
                    'quantity' => 1,
                    'base_price_per_unit_in_cents' => $basePrice,
                    'base_total_in_cents' => $basePrice,
                ]
            ],
            customerId: $user?->id,
            customerType: $user ? get_class($user) : null,
            requestData: []
        );

        // Separate campaigns by type
        $stackableCampaigns = $possibleCampaigns->where('is_stackable', true);
        $nonStackableCampaigns = $possibleCampaigns->where('is_stackable', false);

        // Further separate non-stackable by conditional status
        $unconditionalNonStackable = $nonStackableCampaigns->filter(function ($campaign) {
            return $campaign->conditions->isEmpty();
        });
        $conditionalNonStackable = $nonStackableCampaigns->filter(function ($campaign) {
            return $campaign->conditions->isNotEmpty();
        });

        // 1. Find the best UNCONDITIONAL non-stackable discount
        $bestUnconditionalNonStackable = null;
        $bestUnconditionalSavings = 0;

        foreach ($unconditionalNonStackable as $campaign) {
            $result = $this->discountService->applyCampaign($campaign, $discountContext);
            if ($result && $result->amountSaved > $bestUnconditionalSavings) {
                $bestUnconditionalSavings = $result->amountSaved;
                $bestUnconditionalNonStackable = [
                    'campaign_id' => $result->campaignId,
                    'campaign_name' => $result->campaignName,
                    'discount_type' => $result->discountType,
                    'amount_saved' => $result->amountSaved,
                    'amount_saved_converted' => convert_currency($result->amountSaved),
                ];
            }
        }

        // 2. Get all CONDITIONAL non-stackable discounts
        $conditionalNonStackableDiscounts = [];
        foreach ($conditionalNonStackable as $campaign) {
            $result = $this->discountService->applyCampaign($campaign, $discountContext);
            if ($result && $result->amountSaved > 0) {
                $conditionalNonStackableDiscounts[] = [
                    'campaign_id' => $result->campaignId,
                    'campaign_name' => $result->campaignName,
                    'discount_type' => $result->discountType,
                    'amount_saved' => $result->amountSaved,
                    'amount_saved_converted' => convert_currency($result->amountSaved),
                ];
            }
        }

        // 3. Get all STACKABLE discounts (both conditional and unconditional)
        // Calculate on remaining price after best unconditional non-stackable
        $remainingPrice = $basePrice;
        if ($bestUnconditionalNonStackable) {
            $remainingPrice -= $bestUnconditionalNonStackable['amount_saved'];
        }

        $stackableDiscounts = [];
        foreach ($stackableCampaigns as $campaign) {
            $stackableContext = DiscountContext::fromCart(
                cartTotal: $remainingPrice,
                cartItems: [
                    [
                        'productible_id' => $productId,
                        'productible_type' => $productClass,
                        'quantity' => 1,
                        'base_price_per_unit_in_cents' => $remainingPrice,
                        'base_total_in_cents' => $remainingPrice,
                    ]
                ],
                customerId: $user?->id,
                customerType: $user ? get_class($user) : null,
                requestData: []
            );

            $result = $this->discountService->applyCampaign($campaign, $stackableContext);
            if ($result && $result->amountSaved > 0) {
                $stackableDiscounts[] = [
                    'campaign_id' => $result->campaignId,
                    'campaign_name' => $result->campaignName,
                    'discount_type' => $result->discountType,
                    'amount_saved' => $result->amountSaved,
                    'amount_saved_converted' => convert_currency($result->amountSaved),
                ];
                $remainingPrice -= $result->amountSaved;
            }
        }

        // Add discount information to product array
        $discounts = [];

        if ($bestUnconditionalNonStackable) {
            $discounts['no_conditional_no_stackable'] = $bestUnconditionalNonStackable;
        }

        if (!empty($conditionalNonStackableDiscounts)) {
            $discounts['conditionals_no_stackable'] = $conditionalNonStackableDiscounts;
        }

        if (!empty($stackableDiscounts)) {
            $discounts['stackable_discounts'] = $stackableDiscounts;
        }

        if (!empty($discounts)) {
            $data['discounts'] = $discounts;
        }

        return $data;
    }

    /**
     * Extend product cart array with APPLIED discount information
     * This method is designed to be used as a hook handler for product.cart.array.extend
     *
     * Unlike extendProductArray (which shows all POSSIBLE discounts),
     * this method returns only the APPLIED discounts:
     * 1. The most favorable non-stackable discount (if any)
     * 2. All stackable discounts that apply on top
     *
     * This represents the actual discounts that would be applied when checking out.
     *
     * @param array $data The product cart array to extend
     * @param array $context Context data containing product_id, product_class, and base_price
     * @return array Extended product cart array with applied discount data
     */
    public function extendProductCartArray(array $data, array $context): array
    {
        if (!$this->isFeatureEnabled()) {
            return $data;
        }

        $productId = $context['product_id'] ?? null;
        $productClass = $context['product_class'] ?? null;
        $basePrice = $context['base_price'] ?? null;
        $quantity = $context['quantity'] ?? 1;
        $totalPrice = $basePrice * $quantity;

        if (!$productId || !$productClass || !$basePrice) {
            return $data;
        }

        // Get current user
        $user = AuthHelper::getUser();
        $userId = $user ? ($user->id ?? null) : null;

        $discountContext = DiscountContext::fromCart(
            cartTotal: $totalPrice,
            cartItems: [
                [
                    'productible_id' => $productId,
                    'productible_type' => $productClass,
                    'quantity' => $quantity,
                    'base_price_per_unit_in_cents' => $basePrice,
                    'base_total_in_cents' => $basePrice * $quantity,
                ]
            ],
            customerId: $userId,
            customerType: $user ? get_class($user) : null,
            requestData: []
        );

        // Apply all discounts through the discount application service
        // This will automatically find the best non-stackable + all stackable discounts
        $results = $this->discountService->applyDiscounts($discountContext, DiscountScope::PRODUCTS);

        if ($results->isEmpty()) {
            return $data;
        }

        // Build discount data structure
        // The DiscountApplicationService already handles the logic:
        // - Best non-stackable discount per product
        // - All stackable discounts applied sequentially
        $appliedDiscounts = [];
        $totalSavings = 0;

        foreach ($results as $result) {
            $appliedDiscounts[] = [
                'campaign_id' => $result->campaignId,
                'campaign_name' => $result->campaignName,
                'discount_type' => $result->discountType,
                'amount_saved' => $result->amountSaved,
                'amount_saved_converted' => convert_currency($result->amountSaved),
            ];
            $totalSavings += $result->amountSaved;
        }

        // Add applied discounts to the product data
        if (!empty($appliedDiscounts)) {
            $data['applied_discounts'] = [
                'discounts' => $appliedDiscounts,
                'total_savings' => $totalSavings,
                'total_savings_converted' => convert_currency($totalSavings),
                'final_price' => $totalPrice - $totalSavings,
                'final_price_converted' => convert_currency($totalPrice - $totalSavings),
            ];
        }

        return $data;
    }
}
