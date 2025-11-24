<?php

namespace Ingenius\Discounts\DataTransferObjects;

/**
 * DiscountResult - The result of applying a discount
 */
class DiscountResult
{
    public function __construct(
        public readonly int $campaignId,
        public readonly string $campaignName,
        public readonly string $discountType,
        public readonly int $amountSaved,           // Amount saved in cents
        public readonly array $affectedItems,       // Items that were discounted
        public readonly array $metadata = [],       // Additional data (e.g., for free shipping)
    ) {}

    /**
     * Convert to array for storage or API response
     */
    public function toArray(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'campaign_name' => $this->campaignName,
            'discount_type' => $this->discountType,
            'amount_saved' => $this->amountSaved,
            'affected_items' => $this->affectedItems,
            'metadata' => $this->metadata,
        ];
    }
}
